<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view');
    }

    public function index(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));
        $f    = Carbon::parse($from)->startOfDay();
        $t    = Carbon::parse($to)->endOfDay();

        $kpis = [
            'of_total'      => ProductionOrder::count(),
            'of_en_cours'   => ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count(),
            'of_termine'    => ProductionOrder::where('status', 'termine')->whereBetween('finished_at', [$f, $t])->count(),
            'meters'        => (float) ProductionOutput::whereBetween('produced_at', [$f, $t])->sum('total_meters'),
            'material_cost' => (float) ProductionConsumption::whereBetween('consumed_at', [$f, $t])->sum('cost'),
            'waste_weight'  => (float) ProductionWaste::whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$f, $t]))->sum('weight'),
            'coils_stock'   => (float) Coil::where('status', '!=', 'epuisee')->sum('remaining_weight'),
        ];

        // [§10] KPIs complémentaires
        $kpis['of_en_retard'] = ProductionOrder::whereIn('status', ['lance', 'en_cours'])
            ->whereHas('order', fn ($q) => $q->whereNotNull('delivery_date')->whereDate('delivery_date', '<', today()))
            ->count();
        $kpis['mp_critiques'] = Product::whereHas('family', fn ($q) => $q->whereIn('code', ['MP', 'BPRE', 'BGAL']))
            ->where('stock_min', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity - reserved_quantity), 0) FROM product_stocks WHERE product_stocks.product_id = products.id) < products.stock_min')
            ->count();
        $kpis['pf_disponibles'] = (float) ProductStock::whereHas('product.family', fn ($q) => $q->where('code', 'PF'))
            ->sum(DB::raw('quantity - reserved_quantity'));
        $kpis['meters_today'] = (float) ProductionOutput::whereDate('produced_at', today())->sum('total_meters');
        $kpis['avaries'] = (float) StockMovement::where('type', 'entree')
            ->whereHas('product.family', fn ($q) => $q->where('code', 'AVAR'))
            ->whereBetween('occurred_at', [$f, $t])->sum('quantity');
        $kpis['ventes_jour'] = (int) Invoice::whereDate('issued_at', today())
            ->where('status', '!=', 'annulee')->sum('total_ttc');

        // Stock disponible par dépôt
        $stockParDepot = ProductStock::join('warehouses', 'warehouses.id', '=', 'product_stocks.warehouse_id')
            ->selectRaw('warehouses.name n, warehouses.type t, SUM(product_stocks.quantity - product_stocks.reserved_quantity) dispo')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.type')
            ->orderBy('warehouses.name')->get();

        // Rendement matière moyen sur la période (OF terminés)
        $consumed = (float) ProductionConsumption::whereBetween('consumed_at', [$f, $t])->sum('weight_consumed');
        $waste    = $kpis['waste_weight'];
        $kpis['yield'] = $consumed > 0 ? round((($consumed - $waste) / $consumed) * 100, 1) : null;

        // Production par jour (mètres)
        $daily = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->selectRaw('DATE(produced_at) as d, SUM(total_meters) as m')
            ->groupByRaw('DATE(produced_at)')->orderByRaw('DATE(produced_at)')->get();
        $chartDaily = [
            'labels' => $daily->map(fn ($r) => Carbon::parse($r->d)->format('d/m'))->all(),
            'data'   => $daily->map(fn ($r) => round((float) $r->m, 2))->all(),
        ];

        // OF par statut
        $byStatus = ProductionOrder::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        // Top clients par mètres produits
        $topClients = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->join('production_orders', 'production_orders.id', '=', 'production_outputs.production_order_id')
            ->leftJoin('clients', 'clients.id', '=', 'production_orders.client_id')
            ->selectRaw('COALESCE(clients.name, "—") as client, SUM(production_outputs.total_meters) as m')
            ->groupByRaw('clients.name')->orderByDesc('m')->limit(8)->get();

        // Coût de revient moyen récent
        $avgCost = (float) ProductionCost::avg('cost_per_meter');

        return view('production.dashboard', compact('kpis', 'chartDaily', 'byStatus', 'topClients', 'avgCost', 'stockParDepot', 'from', 'to'));
    }
}
