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
    public function __construct(private ClientService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);
        $filters = $request->only(['search', 'type', 'is_active']);
        $clients = $this->service->search($filters, 15);

        // ── Indicateurs globaux (tous les clients de la société) ──
        $summary = [
            'total'      => Client::count(),
            'active'     => Client::where('is_active', true)->count(),
            'entreprise' => Client::where('type', 'entreprise')->count(),
            'particulier'=> Client::where('type', 'particulier')->count(),
        ];

        return view('clients.index', compact('clients', 'filters', 'summary'));
    }

    public function create()
    {
        $this->authorize('create', Client::class);
        $taxRates = TaxRate::where('is_active', true)->orderByDesc('is_default')->orderBy('rate')->get();
        return view('clients.create', compact('taxRates'));
    }

    public function store(StoreClientRequest $request)
    {
        $this->authorize('create', Client::class);
        $client = $this->service->create($request->validated());
        return redirect()->route('clients.show', $client)
            ->with('success', 'Client créé avec succès.');
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

        return view('clients.show', compact('client', 'totalInvoiced', 'totalPaid', 'balance'));
    }

    public function edit(Client $client)
    {
        $this->authorize('update', $client);
        $client->load(['contacts', 'addresses', 'taxRates']);
        $taxRates = TaxRate::where('is_active', true)->orderByDesc('is_default')->orderBy('rate')->get();
        return view('clients.edit', compact('client', 'taxRates'));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);
        $this->service->update($client, $request->validated());
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
