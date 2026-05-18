<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\StockInsightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * [STOCK-PRO] Tableau de bord stock + pages détaillées :
 *   - dashboard()    → vue d'ensemble avec KPIs + top 10
 *   - restock()      → alertes réapprovisionnement
 *   - dormant()      → articles dormants
 *   - expiry()       → DLC proches + lots périmés
 */
class StockDashboardController extends Controller
{
    public function __construct(private StockInsightsService $insights)
    {
        $this->middleware('can:stocks.view');
    }

    public function dashboard(Request $request): View
    {
        $kpis            = $this->insights->dashboardKpis();
        $topValuation    = $this->insights->topValuationProducts(10);
        $topMoved        = $this->insights->topMovedProductsThisMonth(10);
        $alertsPreview   = $this->insights->restockAlertsQuery()->limit(5)->get();
        $dormantPreview  = $this->insights->dormantProductsQuery()->limit(5)->get();
        $expiringPreview = $this->insights->expiringLotsQuery()->limit(5)->get();

        return view('stocks.dashboard', compact(
            'kpis', 'topValuation', 'topMoved',
            'alertsPreview', 'dormantPreview', 'expiringPreview'
        ));
    }

    public function restock(Request $request): View
    {
        $alerts = $this->insights->restockAlertsQuery()->paginate(50)->withQueryString();
        return view('stocks.insights.restock', compact('alerts'));
    }

    public function dormant(Request $request): View
    {
        $days     = max(7, min(365, $request->integer('days', StockInsightsService::DORMANT_DAYS_DEFAULT)));
        $products = $this->insights->dormantProductsQuery($days)->paginate(50)->withQueryString();
        return view('stocks.insights.dormant', compact('products', 'days'));
    }

    public function expiry(Request $request): View
    {
        $window  = max(1, min(365, $request->integer('window', StockInsightsService::EXPIRY_WINDOW_DEFAULT)));
        $expiring = $this->insights->expiringLotsQuery($window)->paginate(30)->withQueryString();
        $expired  = $this->insights->expiredLotsQuery()->limit(50)->get();
        return view('stocks.insights.expiry', compact('expiring', 'expired', 'window'));
    }

    /**
     * [STOCK-PRO] Analyse ABC — Pareto sur valorisation / rotation / CA.
     */
    public function abc(Request $request): View
    {
        $criterion = in_array($request->input('criterion'), ['valuation','rotation','ca'])
            ? $request->input('criterion') : 'valuation';
        $months = max(1, min(36, $request->integer('months', 12)));

        $analysis = $this->insights->abcAnalysis($criterion, $months);

        return view('stocks.insights.abc', compact('analysis', 'criterion', 'months'));
    }
}
