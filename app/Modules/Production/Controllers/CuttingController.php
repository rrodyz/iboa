<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Services\CuttingOptimizerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuttingController extends Controller
{
    public function __construct(private CuttingOptimizerService $optimizer)
    {
        $this->middleware('permission:production.view');
    }

    public function index(): View
    {
        return view('production.cutting.index', ['plan' => null, 'input' => null]);
    }

    public function optimize(Request $request): View
    {
        $data = $request->validate([
            'stock_length'        => ['required', 'numeric', 'gt:0'],
            'kerf'                => ['nullable', 'numeric', 'min:0'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.length'      => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity'    => ['nullable', 'integer', 'min:0'],
        ]);

        $plan = null;
        $error = null;
        try {
            $plan = $this->optimizer->optimize(
                (float) $data['stock_length'],
                (float) ($data['kerf'] ?? 0),
                $data['items'],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $error = collect($e->errors())->flatten()->first();
        }

        return view('production.cutting.index', ['plan' => $plan, 'input' => $data, 'error' => $error]);
    }
}
