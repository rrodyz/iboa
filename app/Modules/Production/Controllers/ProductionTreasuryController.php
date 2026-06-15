<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\ProductionTreasuryService;
use Illuminate\View\View;

class ProductionTreasuryController extends Controller
{
    public function __construct(private ProductionTreasuryService $service)
    {
        $this->middleware('permission:production.cost.view');
    }

    public function index(): View
    {
        $forecast = $this->service->forecast();

        return view('production.treasury.index', compact('forecast'));
    }
}
