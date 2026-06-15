<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\CommercialWorkflowService;
use App\Services\SalesInsightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesDashboardController extends Controller
{
    public function __construct(
        private SalesInsightsService        $insights,
        private CommercialWorkflowService   $workflow,
    ) {}

    public function index(Request $request): View
    {
        // [AUTH] Double garde : Gate::authorize (cohérent avec le reste du module)
        $this->authorize('viewAny', Invoice::class);

        $kpis         = $this->insights->dashboardKpis();
        $dueSoon      = $this->insights->upcomingDueInvoices(30);
        $topClients   = $this->insights->topClients(5);
        $topProducts  = $this->insights->topProducts(5);
        $monthly      = $this->insights->monthlyEvolution(12);
        $pipeline     = $this->insights->quotesPipeline();
        $workflowKpis = $this->workflow->getDashboardKpis();
        $prodKpis     = app(\App\Modules\Production\Services\SalesProductionService::class)->dashboardKpis();

        return view('ventes.dashboard', compact(
            'kpis', 'dueSoon', 'topClients', 'topProducts', 'monthly', 'pipeline', 'workflowKpis', 'prodKpis'
        ));
    }
}
