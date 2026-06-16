<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionTimeLog;
use App\Modules\Production\Services\LaborService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductionTimeController extends Controller
{
    public function __construct(private LaborService $labor)
    {
        $this->middleware('permission:production.update');
    }

    public function store(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'hours'       => ['required', 'numeric', 'gt:0'],
            'hourly_cost' => ['required', 'numeric', 'min:0'],
            'is_overtime' => ['boolean'],
            'entry_date'  => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->labor->log($order, $data + ['is_overtime' => $request->boolean('is_overtime')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Pointage enregistré.');
    }

    public function destroy(ProductionTimeLog $timeLog): RedirectResponse
    {
        $this->labor->delete($timeLog);

        return back()->with('success', 'Pointage supprimé.');
    }
}
