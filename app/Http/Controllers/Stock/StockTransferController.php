<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(private StockTransferService $service)
    {
        $this->middleware('can:stocks.view')->only(['index', 'show']);
        $this->middleware('can:stocks.adjust')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'createdBy'])
            ->whereNull('deleted_at')
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->input('from_warehouse_id')) {
            $query->where('from_warehouse_id', $from);
        }
        if ($to = $request->input('to_warehouse_id')) {
            $query->where('to_warehouse_id', $to);
        }

        $transfers  = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stocks.transfers.index', compact('transfers', 'warehouses'));
    }

    public function create(): View
    {
        $warehouses = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $products   = Product::where('is_active', 1)->orderBy('reference')->get(['id', 'reference', 'name']);
        return view('stocks.transfers.create', compact('warehouses', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTransferPayload($request);

        try {
            $transfer = $this->service->create($data);
            return redirect()->route('stocks.transfers.show', $transfer)
                ->with('success', "Transfert {$transfer->number} créé en brouillon.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(StockTransfer $transfer): View
    {
        $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse',
            'createdBy', 'shippedBy', 'receivedBy', 'cancelledBy']);
        return view('stocks.transfers.show', compact('transfer'));
    }

    public function edit(StockTransfer $transfer): View
    {
        if (!$transfer->canEdit()) {
            abort(403, "Ce transfert est {$transfer->statusLabel()} — modification interdite.");
        }
        $warehouses = Warehouse::where('is_active', 1)->orderBy('name')->get();
        $products   = Product::where('is_active', 1)->orderBy('reference')->get(['id', 'reference', 'name']);
        $transfer->load('items.product');
        return view('stocks.transfers.edit', compact('transfer', 'warehouses', 'products'));
    }

    public function update(Request $request, StockTransfer $transfer): RedirectResponse
    {
        if (!$transfer->canEdit()) {
            return back()->with('error', "Ce transfert est {$transfer->statusLabel()} — modification interdite.");
        }
        $data = $this->validateTransferPayload($request);
        try {
            $this->service->update($transfer, $data);
            return redirect()->route('stocks.transfers.show', $transfer)
                ->with('success', "Transfert {$transfer->number} mis à jour.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(StockTransfer $transfer): RedirectResponse
    {
        try {
            $this->service->delete($transfer);
            return redirect()->route('stocks.transfers.index')->with('success', 'Transfert supprimé.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function ship(StockTransfer $transfer): RedirectResponse
    {
        try {
            $this->service->ship($transfer);
            return back()->with('success', "Transfert {$transfer->number} expédié. Stock du dépôt source décrémenté.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $data = $request->validate([
            'received_quantities'   => ['nullable', 'array'],
            'received_quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->service->receive($transfer, $data['received_quantities'] ?? []);
            return back()->with('success', "Transfert {$transfer->number} réceptionné. Stock du dépôt destination incrémenté.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        try {
            $this->service->cancel($transfer, $data['reason']);
            return back()->with('success', "Transfert {$transfer->number} annulé.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function validateTransferPayload(Request $request): array
    {
        return $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id'   => ['required', 'integer', 'exists:warehouses,id'],
            'transfer_date'     => ['required', 'date'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'items.*.lot_number'    => ['nullable', 'string', 'max:100'],
            'items.*.serial_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date'   => ['nullable', 'date'],
            'items.*.label'         => ['nullable', 'string', 'max:255'],
        ], [
            'from_warehouse_id.different' => 'Le dépôt source doit être différent du dépôt destination.',
        ]);
    }
}
