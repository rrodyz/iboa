<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreWarehouseRequest;
use App\Http\Requests\Stock\UpdateWarehouseRequest;
use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index', 'show']);
    }

    // ── Index ────────────────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $company = currentCompany();

        $warehouses = Warehouse::withCount(['productStocks', 'stockMovements'])
            ->where('company_id', $company->id)
            ->when($search, fn($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
            )
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'search'));
    }

    // ── Create ───────────────────────────────────────────────────────────────
    public function create(): View
    {
        $warehouse = new Warehouse();
        return view('warehouses.create', compact('warehouse'));
    }

    // ── Store ────────────────────────────────────────────────────────────────
    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['company_id'] = currentCompany()->id;
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active', true);

        $warehouse = DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }
            return Warehouse::create($data);
        });

        return redirect()
            ->route('stocks.warehouses.index')
            ->with('success', "Entrepôt « {$warehouse->name} » créé avec succès.");
    }

    // ── Show ─────────────────────────────────────────────────────────────────
    public function show(Warehouse $warehouse): View
    {
        $warehouse->loadCount(['productStocks', 'stockMovements']);

        $stocks = $warehouse->productStocks()
            ->with('product:id,name,reference,stock_min')
            ->orderByRaw('(quantity - reserved_quantity) ASC')
            ->paginate(20)
            ->withQueryString();

        $recentMovements = $warehouse->stockMovements()
            ->with('product:id,name,reference', 'creator:id,name')
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();

        return view('warehouses.show', compact('warehouse', 'stocks', 'recentMovements'));
    }

    // ── Edit ─────────────────────────────────────────────────────────────────
    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    // ── Update ───────────────────────────────────────────────────────────────
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->validated();

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active');

        DB::transaction(function () use ($warehouse, $data) {
            if ($data['is_default'] && !$warehouse->is_default) {
                Warehouse::where('is_default', true)->update(['is_default' => false]);
            }
            $warehouse->update($data);
        });

        return redirect()
            ->route('stocks.warehouses.index')
            ->with('success', "Entrepôt « {$warehouse->name} » mis à jour.");
    }

    // ── Destroy ──────────────────────────────────────────────────────────────
    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        // Cannot delete the default warehouse
        if ($warehouse->is_default) {
            return back()->with('error', 'Impossible de supprimer l\'entrepôt par défaut.');
        }

        // Cannot delete warehouse with stock
        $hasStock = $warehouse->productStocks()->where('quantity', '>', 0)->exists();
        if ($hasStock) {
            return back()->with('error', 'Impossible de supprimer un entrepôt contenant du stock.');
        }

        $warehouse->delete();

        return redirect()
            ->route('stocks.warehouses.index')
            ->with('success', "Entrepôt supprimé.");
    }
}
