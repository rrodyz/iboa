<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\PlanningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionPlanningController extends Controller
{
    public function __construct(private PlanningService $planning)
    {
        $this->middleware('permission:production.view');
    }

    public function index(Request $request): View
    {
        $horizon = (int) $request->input('horizon', 7);
        $horizon = max(1, min(60, $horizon));

        $plan = $this->planning->loadByWorkCenter($horizon);

        return view('production.planning.index', compact('plan', 'horizon'));
    }
}
