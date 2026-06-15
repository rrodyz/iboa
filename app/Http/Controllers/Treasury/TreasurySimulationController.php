<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Services\TreasurySimulationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreasurySimulationController extends Controller
{
    public function __construct(private TreasurySimulationService $service)
    {
        $this->middleware('permission:payments.view');
    }

    public function index(Request $request): View
    {
        $params = $request->validate([
            'horizon_weeks'    => ['nullable', 'integer', 'min:1', 'max:52'],
            'recovery_rate'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'delay_days'       => ['nullable', 'integer', 'min:0', 'max:180'],
            'recurring_weekly' => ['nullable', 'integer', 'min:0'],
        ]);

        $params = array_merge([
            'horizon_weeks'    => 12,
            'recovery_rate'    => 90,
            'delay_days'       => 0,
            'recurring_weekly' => 0,
        ], array_filter($params, fn ($v) => $v !== null));

        $result = $this->service->simulate($params);

        return view('tresorerie.simulations.index', compact('result', 'params'));
    }
}
