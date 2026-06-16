<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        return view('production.dashboard', compact('kpis', 'chartDaily', 'byStatus', 'topClients', 'avgCost', 'from', 'to'));
    }
}
