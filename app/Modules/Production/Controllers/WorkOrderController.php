<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderOperation;
use App\Modules\Production\Services\RoutingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * [PRODUCTION] Work Orders : génération + exécution des opérations d'un OF.
 */
class WorkOrderController extends Controller
{
    public function __construct(private RoutingService $routing)
    {
        $this->middleware('permission:production.update');
    }

    /** Génère les opérations de l'OF depuis la gamme de sa nomenclature. */
    public function generate(ProductionOrder $order): RedirectResponse
    {
        try {
            $n = $this->routing->generateWorkOrders($order);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', $n . ' opération(s) générée(s) depuis la gamme.');
    }

    public function start(ProductionOrderOperation $operation): RedirectResponse
    {
        try {
            $this->routing->start($operation);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Opération démarrée.');
    }

    public function finish(Request $request, ProductionOrderOperation $operation): RedirectResponse
    {
        $request->validate(['real_minutes' => ['nullable', 'numeric', 'min:0']]);
        $this->routing->finish($operation, $request->filled('real_minutes') ? (float) $request->real_minutes : null);

        return back()->with('success', 'Opération terminée.');
    }
}
