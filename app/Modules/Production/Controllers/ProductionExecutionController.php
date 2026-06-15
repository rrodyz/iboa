<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * [PRODUCTION] Exécution d'un OF : consommation matière, sorties PF, chutes.
 * Toutes les actions exigent production.update et un OF « en cours ».
 */
class ProductionExecutionController extends Controller
{
    public function __construct(
        private CoilConsumptionService $consumption,
        private ProductionStockService $stock,
    ) {
        $this->middleware('permission:production.update');
    }

    // ── Consommation matière ────────────────────────────────────────────────
    public function consume(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'coil_id'         => ['required', 'integer', 'exists:coils,id'],
            'weight_consumed' => ['required', 'numeric', 'gt:0'],
            'length_consumed' => ['nullable', 'numeric', 'min:0'],
            'consumed_at'     => ['nullable', 'date'],
        ]);

        $coil = Coil::findOrFail($data['coil_id']);
        $this->consumption->consume($order, $coil, (float) $data['weight_consumed'], $data['length_consumed'] ?? null, $data['consumed_at'] ?? null);

        return back()->with('success', 'Consommation enregistrée.');
    }

    public function destroyConsumption(ProductionConsumption $consumption): RedirectResponse
    {
        $this->consumption->reverse($consumption);

        return back()->with('success', 'Consommation annulée — poids restitué à la bobine.');
    }

    // ── Sortie produit fini ─────────────────────────────────────────────────
    public function output(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'product_id'   => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'length'       => ['nullable', 'numeric', 'min:0'],
            'quantity'     => ['required', 'numeric', 'gt:0'],
            'color'        => ['nullable', 'string', 'max:60'],
            'thickness'    => ['nullable', 'numeric', 'min:0'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0'],
            'produced_at'  => ['nullable', 'date'],
        ]);

        $this->stock->recordOutput($order, $data);

        return back()->with('success', 'Production enregistrée et entrée en stock.');
    }

    public function destroyOutput(ProductionOutput $output): RedirectResponse
    {
        $this->stock->reverseOutput($output);

        return back()->with('success', 'Sortie de production annulée.');
    }

    // ── Chutes / pertes ─────────────────────────────────────────────────────
    public function waste(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'type'        => ['required', 'in:reutilisable,non_reutilisable,rebut'],
            'weight'      => ['nullable', 'numeric', 'min:0'],
            'quantity'    => ['nullable', 'numeric', 'min:0'],
            'machine_id'  => ['nullable', 'integer', 'exists:production_machines,id'],
            'operator_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reason'      => ['nullable', 'string', 'max:255'],
        ]);

        $this->stock->recordWaste($order, $data);

        return back()->with('success', 'Chute enregistrée.');
    }

    public function destroyWaste(ProductionWaste $waste): RedirectResponse
    {
        $this->stock->reverseWaste($waste);

        return back()->with('success', 'Chute supprimée.');
    }
}
