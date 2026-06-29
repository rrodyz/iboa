<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionTimeLog;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Quality\Models\QualityInspection;
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

        // ── TRS — Taux de Rendement Synthétique (§16 CDC Production) ─────────
        // TRS = Disponibilité × Performance × Qualité
        // Disponibilité = (Temps théorique - Arrêts) / Temps théorique
        // Performance   = Mètres réels / Mètres théoriques (si défini)
        // Qualité       = (Mètres produits - Rebuts métriques) / Mètres produits
        $trs = $this->computeTrs($f, $t, $kpis);

        // ── Coût standard vs réel (§11 CDC) ──────────────────────────────────
        $coutComparaison = $this->computeCostComparison($f, $t);

        return view('production.dashboard', compact(
            'kpis', 'chartDaily', 'byStatus', 'topClients', 'avgCost',
            'stockParDepot', 'from', 'to', 'trs', 'coutComparaison'
        ));
    }

    /**
     * TRS = D × P × Q (§16 CDC — indicateur industrie).
     * Disponibilité = 1 - (arrêts / temps théorique)
     * Performance   = mètres réels / (mètres théoriques selon gamme)
     * Qualité       = mètres bons / mètres produits
     */
    private function computeTrs(Carbon $f, Carbon $t, array $kpis): array
    {
        // Disponibilité : arrêts machine sur la période
        $downtimeMinutes = (float) MachineMaintenance::whereBetween('started_at', [$f, $t])
            ->where('status', 'cloture')
            ->sum('downtime_minutes');

        $nbJours = max(1, $f->diffInDays($t) + 1);
        // Hypothèse : 1 machine × 8h/jour (configurable) = temps théorique en minutes
        $nbMachines = \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->count() ?: 1;
        $theoreticalMinutes = $nbJours * 8 * 60 * $nbMachines;

        $disponibilite = $theoreticalMinutes > 0
            ? max(0, min(100, round((1 - $downtimeMinutes / $theoreticalMinutes) * 100, 1)))
            : 100.0;

        // Performance : mètres réels vs heures ouvrées × cadence théorique
        // On utilise le rendement matière comme proxy de performance
        $metersReal = $kpis['meters'] ?? 0;
        // Cadence standard = mètres théoriques basés sur les OF terminés avec quantité planifiée
        $metersPlanned = (float) ProductionOrder::where('status', 'termine')
            ->whereBetween('finished_at', [$f, $t])
            ->sum('quantity_planned');
        $performance = $metersPlanned > 0
            ? max(0, min(100, round(($metersReal / $metersPlanned) * 100, 1)))
            : ($metersReal > 0 ? 85.0 : 0.0); // fallback estimation si pas de quantité planifiée

        // Qualité : (mètres produits - rebuts en kg / densité) / mètres produits
        $wasteKg   = $kpis['waste_weight'] ?? 0;
        $metersTotal = $metersReal;
        // Estimation rebuts en mètres : 1 m de tôle bac ≈ poids_m = epaisseur × largeur × densité
        // On utilise le ratio rebuts/consommation comme proxy qualité
        $consumed = (float) ProductionConsumption::whereBetween('consumed_at', [$f, $t])->sum('weight_consumed');
        $qualite  = $consumed > 0
            ? max(0, min(100, round((1 - $wasteKg / max(1, $consumed)) * 100, 1)))
            : ($metersTotal > 0 ? 95.0 : 0.0);

        $trsValue = round($disponibilite * $performance * $qualite / 10000, 1);

        return [
            'trs'            => $trsValue,
            'disponibilite'  => $disponibilite,
            'performance'    => $performance,
            'qualite'        => $qualite,
            'downtime_h'     => round($downtimeMinutes / 60, 1),
            'theoretical_h'  => round($theoreticalMinutes / 60, 0),
        ];
    }

    /**
     * Comparaison coût standard vs coût réel par produit (§11 CDC).
     */
    private function computeCostComparison(Carbon $f, Carbon $t): \Illuminate\Support\Collection
    {
        return ProductionCost::with('productionOrder.product')
            ->whereBetween('created_at', [$f, $t])
            ->get()
            ->groupBy('productionOrder.product.name')
            ->map(function ($costs, $productName) {
                $real     = $costs->avg('cost_per_meter') ?? 0;
                $standard = $costs->first()?->productionOrder?->product?->purchase_price ?? 0;
                return [
                    'product'   => $productName ?: 'Produit inconnu',
                    'cout_reel' => round($real, 0),
                    'cout_std'  => round($standard, 0),
                    'ecart'     => round($real - $standard, 0),
                    'ecart_pct' => $standard > 0 ? round((($real - $standard) / $standard) * 100, 1) : null,
                ];
            })->values()->take(10);
    }
}
