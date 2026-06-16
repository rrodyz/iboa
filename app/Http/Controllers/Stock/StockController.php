<?php

namespace App\Http\Controllers\Stock;

use App\Exports\StockExport;
use App\Exports\StockMovementsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreMovementRequest;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * Display the stock levels (niveaux de stock).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);
        $filters = $request->only(['search', 'warehouse_id', 'low_stock']);

        $stocks     = $this->stockService->getStockSummary($filters);
        $warehouses = Warehouse::active()->orderBy('name')->get();

        // Count of products with available qty <= stock_min (proactive alert)
        $lowStockCount = ProductStock::whereHas('product', function ($q) {
            $q->where('is_active', true)->whereRaw('products.stock_min > 0');
        })->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) <= (SELECT stock_min FROM products WHERE id = product_stocks.product_id)')
          ->count();

        // Count products completely out of stock
        $ruptureCount = ProductStock::whereHas('product', fn($q) => $q->where('is_active', true))
            ->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) <= 0')
            ->count();

        return view('stocks.index', compact('stocks', 'warehouses', 'filters', 'lowStockCount', 'ruptureCount'));
    }

    /**
     * Export stock état complet en Excel.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $warehouseId  = $request->input('warehouse_id') ?: null;
        $search       = $request->input('search') ?: null;
        $lowStockOnly = $request->boolean('low_stock');

        $filename = 'etat-stock-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new StockExport($warehouseId, $search, $lowStockOnly),
            $filename
        );
    }

    public function exportPdf(Request $request): mixed
    {
        $company     = currentCompany();
        $warehouseId = $request->input('warehouse_id') ?: null;
        $search      = $request->input('search') ?: null;
        $lowStock    = $request->boolean('low_stock');

        $stocks = ProductStock::with(['product', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search, fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')
            ))
            ->when($lowStock, fn($q) => $q->whereHas('product', fn($p) =>
                $p->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) <= products.stock_min')
            ))
            ->orderBy('product_id')
            ->get();

        $warehouses = Warehouse::active()->orderBy('name')->get();
        $warehouseName = $warehouseId ? optional(Warehouse::find($warehouseId))->name : null;

        $pdf = Pdf::loadView('stocks.pdf.index', compact(
            'company', 'stocks', 'warehouseName', 'search', 'lowStock'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('etat_stock_' . now()->format('Ymd_His') . '.pdf');
    }

    public function movementsPdf(Request $request): mixed
    {
        $company = currentCompany();
        $filters = $request->only(['search', 'product_id', 'warehouse_id', 'type', 'date_from', 'date_to']);

        $movements = StockMovement::with(['product.family', 'warehouse', 'createdBy'])
            ->when(!empty($filters['product_id']),   fn($q) => $q->where('product_id', $filters['product_id']))
            ->when(!empty($filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $filters['warehouse_id']))
            ->when(!empty($filters['type']),         fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['date_from']),    fn($q) => $q->whereDate('occurred_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),      fn($q) => $q->whereDate('occurred_at', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),       fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', '%'.$filters['search'].'%')
                  ->orWhere('reference', 'like', '%'.$filters['search'].'%')
            ))
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->get();

        $typeLabels = [
            'entree'             => 'Entrée',
            'sortie'             => 'Sortie',
            'transfert'          => 'Transfert',
            'ajustement'         => 'Ajustement',
            'inventaire'         => 'Inventaire',
            'retour_client'      => 'Retour client',
            'retour_fournisseur' => 'Retour fournisseur',
        ];

        $pdf = Pdf::loadView('stocks.pdf.movements', compact(
            'company', 'movements', 'filters', 'typeLabels'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('mouvements_stock_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Display the stock movement history.
     */
    public function movements(Request $request): View|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filters = $request->only(['search', 'product_id', 'warehouse_id', 'type', 'date_from', 'date_to']);

        if ($request->boolean('export')) {
            return Excel::download(
                new StockMovementsExport($filters),
                'mouvements-stock-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $movements  = $this->stockService->getMovements($filters);
        $warehouses = Warehouse::active()->orderBy('name')->get();
        $products   = Product::active()->orderBy('name')->get(['id', 'name', 'reference']);

        return view('stocks.movements', compact('movements', 'warehouses', 'products', 'filters'));
    }

    /**
     * Show the form for creating a manual stock movement.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', StockMovement::class);
        $products   = Product::active()->where('is_stockable', true)->orderBy('name')
            ->get(['id', 'name', 'reference', 'purchase_price', 'type', 'has_lot_number', 'has_serial_number', 'has_expiry_date']);
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name', 'code']);

        $movementTypes = [
            'entree'             => 'Entrée en stock',
            'sortie'             => 'Sortie de stock',
            'transfert'          => 'Transfert',
            'ajustement'         => 'Ajustement de stock',
            'retour_client'      => 'Retour client',
            'retour_fournisseur' => 'Retour fournisseur',
        ];

        return view('stocks.movement-create', compact('products', 'warehouses', 'movementTypes'));
    }

    /**
     * Display stock lots (traceability: lot numbers, serial numbers, expiry dates).
     */
    public function lots(Request $request): View
    {
        $filters = $request->only(['search', 'warehouse_id', 'status', 'expiring_soon']);

        $query = StockLot::with(['product', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true));

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'disponible'); // default: show only available
        }
        if (!empty($filters['expiring_soon'])) {
            $query->expiringSoon(30);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(fn($q) =>
                $q->where('lot_number', 'like', $s)
                  ->orWhere('serial_number', 'like', $s)
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', $s)->orWhere('reference', 'like', $s))
            );
        }

        // Mark expired lots
        StockLot::expired()->update(['status' => 'expire']);

        $lots       = $query->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC')
                            ->paginate(25)->withQueryString();
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        return view('stocks.lots', compact('lots', 'warehouses', 'filters'));
    }

    /**
     * Display detailed stock view for a single product (all warehouses + movement history).
     */
    public function show(Product $product): View
    {
        // Stock across all warehouses for this product
        $stocks = ProductStock::where('product_id', $product->id)
            ->with('warehouse')
            ->orderByDesc('quantity')
            ->get();

        // Totals
        $totalQty      = $stocks->sum(fn($s) => (float) $s->quantity);
        $totalReserved = $stocks->sum(fn($s) => (float) $s->reserved_quantity);
        $totalAvailable = $totalQty - $totalReserved;
        $avgCost       = $stocks->where('quantity', '>', 0)->avg('avg_cost') ?? 0;

        // Recent movement history (paginated)
        $movements = StockMovement::where('product_id', $product->id)
            ->with(['warehouse', 'createdBy:id,name'])
            ->orderByDesc('occurred_at')
            ->paginate(20)
            ->withQueryString();

        return view('stocks.show', compact(
            'product', 'stocks',
            'totalQty', 'totalReserved', 'totalAvailable', 'avgCost',
            'movements'
        ));
    }

    /**
     * Stock valuation report — total value per product/warehouse using avg_cost.
     */
    public function valuation(Request $request): View
    {
        $warehouseId = $request->input('warehouse_id');
        $familyId    = $request->input('family_id');
        $method      = $request->input('method', ''); // '' = all methods

        $query = ProductStock::with(['product.family', 'product.unit', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true)->where('is_stockable', true))
            ->where('quantity', '>', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($familyId) {
            $query->whereHas('product', fn($q) => $q->where('family_id', $familyId));
        }
        if ($method) {
            $query->whereHas('product', fn($q) => $q->where('valuation_method', $method));
        }

        $stocks = $query->orderBy('warehouse_id')
                        ->orderByRaw('(SELECT name FROM products WHERE id = product_stocks.product_id)')
                        ->get();

        $totalValue     = $stocks->sum(fn($s) => (float)$s->quantity * (float)$s->avg_cost);
        $byWarehouse    = $stocks->groupBy('warehouse_id');
        $warehouses     = Warehouse::active()->orderBy('name')->get(['id', 'name']);
        $families       = \App\Models\ProductFamily::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        return view('stocks.valuation', compact(
            'stocks', 'totalValue', 'byWarehouse',
            'warehouses', 'families',
            'warehouseId', 'familyId', 'method'
        ));
    }

    /**
     * [STOCK-PRO] Batch seuils min / max / réappro editor.
     */
    public function seuils(Request $request): View
    {
        $this->authorize('create', \App\Models\StockMovement::class);

        $search     = $request->input('search');
        $familyId   = $request->input('family_id');
        $alertOnly  = $request->boolean('alert_only');

        $query = Product::active()->where('is_stockable', true)
            ->with(['family', 'unit', 'productStocks'])
            ->orderBy('name');

        if ($search) {
            $query->where(fn($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('reference', 'like', '%'.$search.'%'));
        }
        if ($familyId) {
            $query->where('family_id', $familyId);
        }
        if ($alertOnly) {
            $query->where(function ($q) {
                $q->whereRaw('stock_min > 0')
                  ->whereHas('productStocks', fn($s) =>
                      $s->whereRaw('(quantity - reserved_quantity) <= (SELECT stock_min FROM products WHERE id = product_stocks.product_id)')
                  );
            });
        }

        $products = $query->paginate(50)->withQueryString();
        $families = \App\Models\ProductFamily::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        return view('stocks.seuils', compact('products', 'families', 'search', 'familyId', 'alertOnly'));
    }

    /**
     * [STOCK-PRO] Save batch seuils update.
     */
    public function seuilsUpdate(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\StockMovement::class);

        $data = $request->input('seuils', []);

        foreach ($data as $productId => $values) {
            $product = Product::find((int) $productId);
            if (! $product) {
                continue;
            }
            $product->update([
                'stock_min'    => isset($values['stock_min'])    && $values['stock_min']    !== '' ? (float) $values['stock_min']    : null,
                'stock_max'    => isset($values['stock_max'])    && $values['stock_max']    !== '' ? (float) $values['stock_max']    : null,
                'reorder_point'=> isset($values['reorder_point'])&& $values['reorder_point']!== '' ? (float) $values['reorder_point']: null,
            ]);
        }

        return back()->with('success', count($data) . ' article(s) mis à jour.');
    }

    /**
     * Store a new manual stock movement.
     */
    public function storeMovement(StoreMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\StockMovement::class);
        try {
            $this->stockService->recordMovement($request->validated());

            return redirect()
                ->route('stocks.movements')
                ->with('success', 'Mouvement de stock enregistré avec succès.');
        } catch (ValidationException $e) {
            // Stock insuffisant or other business-rule validation — show field errors
            throw $e;
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }
    }
}
