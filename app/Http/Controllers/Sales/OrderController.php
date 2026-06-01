<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreOrderRequest;
use App\Http\Requests\Sale\UpdateOrderRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService                $service,
        private CommercialWorkflowService   $workflow,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $filters = $request->only(['client_id', 'status', 'search']);
        $orders  = $this->service->search($filters, 15);

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $company = currentCompany();
        $totalsQuery = Order::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'         => (int) $totalsQuery->sum('total_ttc'),
            'total_ht'          => (int) (clone $totalsQuery)->sum('subtotal_ht'),
            'count_confirmed'   => (int) (clone $totalsQuery)->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre'])->count(),
            'count_delivered'   => (int) (clone $totalsQuery)->where('status', 'livre')->count(),
            'count_invoiced'    => (int) (clone $totalsQuery)->where('status', 'facture')->count(),
        ];

        return view('ventes.commandes.index', compact('orders', 'filters', 'summary'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Order::class);
        $clients          = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name', 'is_tax_exempt']);
        $products         = Product::active()->sellable()->with('taxRate:id,rate')->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id', 'is_stockable']);
        $selectedClient   = $request->query('client_id');
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente    = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate']);

        return view('ventes.commandes.create', compact('clients', 'products', 'selectedClient', 'clientExemptions', 'taxRatesVente'));
    }

    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);
        $order = $this->service->create($request->validated());

        return redirect()
            ->route('ventes.commandes.show', $order)
            ->with('success', 'Commande ' . $order->number . ' créée avec succès.');
    }

    public function show(Order $commande)
    {
        $this->authorize('view', $commande);
        $order = $this->service->repository->findWithDetails($commande->id);

        return view('ventes.commandes.show', compact('order'));
    }

    public function edit(Order $commande)
    {
        $this->authorize('update', $commande);
        $order            = $this->service->repository->findWithDetails($commande->id);
        $clients          = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name', 'is_tax_exempt']);
        $products         = Product::active()->sellable()->with('taxRate:id,rate')->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id', 'is_stockable']);
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente    = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate']);

        return view('ventes.commandes.edit', compact('order', 'clients', 'products', 'clientExemptions', 'taxRatesVente'));
    }

    public function update(UpdateOrderRequest $request, Order $commande)
    {
        $this->authorize('update', $commande);
        $this->service->update($commande, $request->validated());

        return redirect()
            ->route('ventes.commandes.show', $commande)
            ->with('success', 'Commande mise à jour.');
    }

    public function destroy(Order $commande)
    {
        $this->authorize('delete', $commande);
        try {
            $this->service->delete($commande);
            return redirect()
                ->route('ventes.commandes.index')
                ->with('success', 'Commande supprimée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/commandes/{order}/confirm — confirm the order. */
    public function confirm(Order $commande)
    {
        $this->authorize('validate', $commande);
        try {
            $this->service->confirm($commande);
            return back()->with('success', 'Commande ' . $commande->number . ' confirmée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/commandes/{order}/cancel — cancel the order. */
    public function cancel(Order $commande)
    {
        $this->authorize('delete', $commande);
        try {
            $this->service->cancel($commande);
            return back()->with('success', 'Commande ' . $commande->number . ' annulée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/commandes/{order}/invoice — create invoice from order.
     */
    public function createInvoice(Order $commande)
    {
        $this->authorize('update', $commande);
        try {
            $invoice = $this->service->createInvoice($commande);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' créée depuis la commande.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/commandes/{order}/delivery-note — create delivery note from order.
     */
    public function createDeliveryNote(Order $commande)
    {
        $this->authorize('update', $commande);
        try {
            $dn = $this->service->createDeliveryNote($commande);
            return redirect()
                ->route('ventes.bons-livraison.show', $dn)
                ->with('success', 'Bon de livraison ' . $dn->number . ' créé depuis la commande.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Workflow de validation interne ────────────────────────────────────────

    public function submit(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->submit($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} soumise à validation.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateInternal(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->validateOrder($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} validée.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectInternal(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => 'Le motif est obligatoire.']);
        try {
            $this->workflow->reject($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} refusée — retour en brouillon.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInternal(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => "Le motif d'annulation est obligatoire."]);
        try {
            $this->workflow->cancel($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} annulée.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
