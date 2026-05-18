<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreOrderRequest;
use App\Http\Requests\Sale\UpdateOrderRequest;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $filters = $request->only(['client_id', 'status', 'search']);
        $orders  = $this->service->search($filters, 15);

        return view('ventes.commandes.index', compact('orders', 'filters'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Order::class);
        $clients        = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);
        $products       = Product::active()->sellable()->with('taxRate:id,rate')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id']);
        $selectedClient = $request->query('client_id');

        return view('ventes.commandes.create', compact('clients', 'products', 'selectedClient'));
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
        $order    = $this->service->repository->findWithDetails($commande->id);
        $clients  = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);
        $products = Product::active()->sellable()->with('taxRate:id,rate')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id']);

        return view('ventes.commandes.edit', compact('order', 'clients', 'products'));
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
}
