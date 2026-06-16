<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\BatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(private BatchService $batches)
    {
        $this->middleware('permission:production.update');
    }

    public function store(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate(['quantity' => ['nullable', 'numeric', 'min:0']]);

        try {
            $this->batches->createForOrder($order, $request->filled('quantity') ? (float) $request->quantity : null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Lot de fabrication créé.');
    }

    public function close(ProductionBatch $batch): RedirectResponse
    {
        $this->batches->close($batch);

        return back()->with('success', 'Lot clôturé.');
    }
}
