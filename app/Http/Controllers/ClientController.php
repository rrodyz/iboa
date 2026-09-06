<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Requests\Client\StoreInteractionRequest;
use App\Models\Client;
use App\Models\TaxRate;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct(private ClientService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        $filters = $request->only(['search', 'type', 'is_active']);
        $clients = $this->service->search($filters, 15);

        // ── Indicateurs globaux (tous les clients de la société) ──
        $summary = [
            'total'        => Client::count(),
            'active'       => Client::where('is_active', true)->count(),
            'entreprise'   => Client::where('type', 'entreprise')->count(),
            'particulier'  => Client::where('type', 'particulier')->count(),
            'distributeur' => Client::where('type', 'distributeur')->count(),
            'minier'       => Client::where('type', 'minier')->count(),
        ];

        return view('clients.index', compact('clients', 'filters', 'summary'));
    }

    public function create()
    {
        $this->authorize('create', Client::class);
        return view('clients.create', $this->formRefs());
    }

    public function store(StoreClientRequest $request)
    {
        $this->authorize('create', Client::class);
        $client = $this->service->create($request->validated());
        $this->uploadDocuments($client, $request);
        return redirect()->route('clients.show', $client)
            ->with('success', 'Client créé avec succès.');
    }

    /** Données de référence partagées create/edit (fiche SAGE). */
    private function formRefs(): array
    {
        return [
            'taxRates'   => TaxRate::where('is_active', true)->orderByDesc('is_default')->orderBy('rate')->get(),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'salesReps'  => \App\Models\SalesRep::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            // [Parité Sage X3] Tiers comptables (client facturé/payeur/groupe/risque, factor)
            'tiersClients' => Client::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    public function show(Client $client)
    {
        $this->authorize('view', $client);
        $client->load([
            'contacts',
            'addresses',
            'interactions' => fn ($q) => $q->latest('occurred_at')->limit(20),
            'interactions.user',
            'invoices'     => fn ($q) => $q->latest()->limit(5),
        ]);

        $client->loadCount(['invoices', 'interactions', 'contacts', 'addresses']);

        // 1 seule requête au lieu de 2 appels sum() séparés
        $stats = DB::selectOne(
            'SELECT
                COALESCE((SELECT SUM(total_ttc) FROM invoices   WHERE client_id = ? AND deleted_at IS NULL), 0) as total_invoiced,
                COALESCE((SELECT SUM(amount)    FROM client_payments WHERE client_id = ? AND deleted_at IS NULL), 0) as total_paid',
            [$client->id, $client->id]
        );
        $totalInvoiced = (float) ($stats->total_invoiced ?? 0);
        $totalPaid     = (float) ($stats->total_paid     ?? 0);
        $balance       = $totalInvoiced - $totalPaid;

        // [R4.13] Encours et disponible viennent du service canonique — jamais
        // d'un calcul refait en vue. Un client dont le mode n'est pas le crédit
        // n'a pas de plafond opposable : `limited` le dit, et la vue affiche
        // « N/A » plutôt qu'un zéro trompeur. Une indisponibilité du calcul ne
        // doit pas transformer une fiche client en erreur 500.
        try {
            $exposition = app(\App\Services\CustomerCreditExposureService::class)->assessClient($client);
        } catch (\Throwable $e) {
            report($e);
            $exposition = null;
        }

        // [R4.16/R4.17] Vue 360° : les derniers documents de chaque type, jamais
        // l'historique intégral. Les compteurs donnent le volume réel ; les
        // listes restent bornées pour qu'une fiche de gros client ne dégénère
        // pas en requête sans fin.
        $limite = 10;
        $documents = [
            'quotes' => $client->quotes()->latest('id')
                ->take($limite)->get(['id', 'number', 'issued_at', 'total_ttc', 'status']),
            'orders' => $client->orders()->latest('id')
                ->take($limite)->get(['id', 'number', 'issued_at', 'total_ttc', 'status', 'client_id']),
            'bonPreparations' => $client->bonPreparations()
                ->latest('bon_preparations.id')->take($limite)
                ->get(['bon_preparations.id', 'bon_preparations.number', 'bon_preparations.order_id',
                       'bon_preparations.created_at', 'bon_preparations.status']),
            'deliveryNotes' => $client->deliveryNotes()->latest('id')
                ->take($limite)->get(['id', 'number', 'issued_at', 'status']),
            'invoices' => $client->invoices()->latest('id')
                ->take($limite)->get(['id', 'number', 'issued_at', 'total_ttc', 'paid_amount', 'remaining_amount', 'status']),
            'payments' => $client->payments()->with('paymentMethod:id,name')->latest('id')
                ->take($limite)->get(['id', 'number', 'payment_date', 'amount', 'payment_method_id', 'reference']),
            'creditNotes' => $client->creditNotes()->latest('id')
                ->take($limite)->get(['id', 'number', 'issued_at', 'total_ttc', 'invoice_id']),
        ];

        $compteurs = [
            'quotes'          => $client->quotes()->count(),
            'orders'          => $client->orders()->count(),
            'bonPreparations' => $client->bonPreparations()->count(),
            'deliveryNotes'   => $client->deliveryNotes()->count(),
            'invoices'        => $client->invoices()->count(),
            'payments'        => $client->payments()->count(),
            'creditNotes'     => $client->creditNotes()->count(),
        ];

        $client->loadMissing('createdBy');

        return view('clients.show', compact(
            'client', 'totalInvoiced', 'totalPaid', 'balance',
            'exposition', 'documents', 'compteurs',
        ));
    }

    /**
     * [Dossier client] Synthèse commerciale bout-en-bout : chaque document porte
     * SON propre statut (devis / commande / OF / BL / facture / avoir), jamais un
     * statut « payé » global. Matérialise le parcours réel du dossier client.
     */
    public function dossier(Client $client)
    {
        $this->authorize('view', $client);

        $orders = $client->orders()
            ->with(['quote:id,number,status', 'productionOrders:id,order_id,number,status,quantity_produced', 'deliveryNotes:id,order_id,number,status', 'invoices:id,order_id,number,status,total_ttc,paid_amount,remaining_amount'])
            ->latest()->limit(50)->get();

        $invoices    = $client->invoices()->latest()->limit(100)->get();
        $creditNotes = $client->creditNotes()->latest()->limit(50)->get();
        $quotes      = $client->quotes()->latest()->limit(50)->get();

        // Synthèse financière — statuts corrects
        $totalInvoiced = (float) $invoices->whereNotIn('status', ['brouillon', 'annulee'])->where('type', '!=', 'avoir')->sum('total_ttc');
        $outstanding   = (float) $invoices->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])->sum('remaining_amount');
        $overdue       = (float) $invoices->where('status', 'en_retard')->sum('remaining_amount');

        return view('gestion.clients.dossier', compact('client', 'orders', 'invoices', 'creditNotes', 'quotes', 'totalInvoiced', 'outstanding', 'overdue'));
    }

    public function edit(Client $client)
    {
        $this->authorize('update', $client);
        $client->load(['contacts', 'addresses', 'taxRates', 'attachments']);

        // [CDC OA-12 r.7] Liste consolidée des documents liés au client (5 derniers par type)
        $clientDocs = [
            'devis' => \App\Models\Quote::where('client_id', $client->id)
                ->latest('issued_at')->latest('id')->limit(5)
                ->get(['id', 'number', 'issued_at', 'total_ttc', 'status']),
            'commandes' => \App\Models\Order::where('client_id', $client->id)
                ->latest('issued_at')->latest('id')->limit(5)
                ->get(['id', 'number', 'issued_at', 'total_ttc', 'status']),
            'factures' => \App\Models\Invoice::where('client_id', $client->id)
                ->latest('issued_at')->latest('id')->limit(5)
                ->get(['id', 'number', 'issued_at', 'total_ttc', 'remaining_amount', 'status']),
        ];

        return view('clients.edit', array_merge(['client' => $client, 'clientDocs' => $clientDocs], $this->formRefs()));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);
        $this->service->update($client, $request->validated());
        $this->uploadDocuments($client, $request);
        return redirect()->route('clients.show', $client)
            ->with('success', 'Client mis à jour.');
    }

    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);
        try {
            $this->service->delete($client);
            return redirect()->route('clients.index')
                ->with('success', 'Client archivé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function storeInteraction(StoreInteractionRequest $request, Client $client)
    {
        $data            = $request->validated();
        $data['user_id'] = auth()->id();
        $client->interactions()->create($data);
        return back()->with('success', 'Interaction enregistrée.');
    }
}
