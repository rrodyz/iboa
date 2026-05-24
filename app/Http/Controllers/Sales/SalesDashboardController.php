<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\SalesInsightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesDashboardController extends Controller
{
    public function __construct(private SalesInsightsService $insights)
    {
        $this->middleware('can:invoices.view');
    }

    public function index(Request $request): View
    {
        $kpis         = $this->insights->dashboardKpis();
        $dueSoon      = $this->insights->upcomingDueInvoices(30);
        $topClients   = $this->insights->topClients(5);
        $topProducts  = $this->insights->topProducts(5);
        $monthly      = $this->insights->monthlyEvolution(12);
        $pipeline     = $this->insights->quotesPipeline();

        return view('ventes.dashboard', compact(
            'kpis', 'dueSoon', 'topClients', 'topProducts', 'monthly', 'pipeline'
        ));
    }
}
