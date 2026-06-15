<?php

namespace App\Http\Controllers;

use App\Services\DirectionService;
use Illuminate\View\View;

class DirectionDashboardController extends Controller
{
    public function __construct(private DirectionService $service)
    {
        $this->middleware('permission:reports.view');
    }

    public function index(): View
    {
        $kpis = $this->service->kpis();

        return view('direction.dashboard', compact('kpis'));
    }
}
