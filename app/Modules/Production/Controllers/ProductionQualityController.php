<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionQualityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.update');
    }

    public function store(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate([
            'status'            => ['required', 'in:conforme,non_conforme,a_reprendre'],
            'reason'            => ['nullable', 'string', 'max:255'],
            'rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'controller_id'     => ['nullable', 'integer', 'exists:employees,id'],
            'controlled_at'     => ['nullable', 'date'],
        ]);

        $order->qualityControls()->create([
            'company_id'        => $order->company_id,
            'thickness_ok'      => $request->boolean('thickness_ok'),
            'length_ok'         => $request->boolean('length_ok'),
            'color_ok'          => $request->boolean('color_ok'),
            'visual_ok'         => $request->boolean('visual_ok'),
            'status'            => $request->input('status'),
            'reason'            => $request->input('reason'),
            'rejected_quantity' => $request->input('rejected_quantity', 0),
            'controller_id'     => $request->input('controller_id'),
            'controlled_at'     => $request->input('controlled_at') ?? now(),
            'created_by'        => Auth::id(),
        ]);

        return back()->with('success', 'Contrôle qualité enregistré.');
    }

    public function destroy(ProductionQualityControl $qualityControl): RedirectResponse
    {
        $qualityControl->delete();

        return back()->with('success', 'Contrôle qualité supprimé.');
    }
}
