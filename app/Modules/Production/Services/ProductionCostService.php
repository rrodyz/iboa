<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;

/**
 * [PRODUCTION] Calcul du coût de revient d'un OF.
 *
 * Coût total = matière + main-d'œuvre + machine + frais indirects.
 *   - matière  : somme réelle des consommations de bobines
 *   - MO       : BOM.labor_per_unit × quantité  (ou override manuel)
 *   - machine  : temps machine (BOM) × coût horaire machine de la ligne
 *   - indirect : taux % appliqué sur (matière + MO + machine)  (ou override)
 *
 * Coût/mètre et coût/unité dérivés des sorties réelles.
 * Marge = chiffre d'affaires estimé − coût total.
 */
class ProductionCostService
{
    /**
     * Recalcule et persiste le coût de revient (1 ligne par OF).
     *
     * @param array $opts labor_cost, machine_cost, overhead_cost, overhead_rate, revenue
     */
    public function compute(ProductionOrder $order, array $opts = []): ProductionCost
    {
        $order->loadMissing(['consumptions', 'outputs', 'billOfMaterial', 'productionLine.machine', 'product', 'order']);

        $quantity = (float) ($order->quantity_produced ?: $order->quantity_requested);
        $bom      = $order->billOfMaterial;

        // 1. Matière — coût réel consommé
        $material = (int) $order->consumptions->sum('cost');

        // 2. Main-d'œuvre — priorité au pointage RÉEL (production_time_logs),
        //    sinon override manuel, sinon estimation nomenclature (BOM).
        $realLabor = (int) $order->timeLogs()->sum('labor_cost');
        if ($realLabor > 0) {
            $labor = $realLabor;
        } elseif (array_key_exists('labor_cost', $opts) && $opts['labor_cost'] !== null) {
            $labor = (int) $opts['labor_cost'];
        } else {
            $labor = (int) round((float) ($bom->labor_per_unit ?? 0) * $quantity);
        }

        // 3. Machine — temps (min) × coût horaire
        $machine = array_key_exists('machine_cost', $opts) && $opts['machine_cost'] !== null
            ? (int) $opts['machine_cost']
            : (int) round($this->machineMinutes($order, $quantity) / 60 * $this->hourlyCost($order));

        // 4. Frais indirects
        if (array_key_exists('overhead_cost', $opts) && $opts['overhead_cost'] !== null) {
            $overhead = (int) $opts['overhead_cost'];
        } else {
            $rate     = (float) ($opts['overhead_rate'] ?? 0);
            $overhead = (int) round(($material + $labor + $machine) * $rate / 100);
        }

        $total = $material + $labor + $machine + $overhead;

        // Coût standard (nomenclature × quantité) + écart réel/standard
        $standardTotal = (int) round((
            (float) ($bom->std_material_cost ?? 0)
            + (float) ($bom->std_labor_cost ?? 0)
            + (float) ($bom->std_machine_cost ?? 0)
            + (float) ($bom->std_overhead_cost ?? 0)
        ) * $quantity);
        $variance = $standardTotal > 0 ? $total - $standardTotal : null; // >0 = défavorable

        // Bases unitaires
        $meters = (float) $order->outputs->sum('total_meters');
        if ($meters <= 0) {
            $meters = $order->totalMeters();
        }
        $costPerMeter = $meters > 0 ? round($total / $meters, 2) : 0;
        $costPerUnit  = $quantity > 0 ? round($total / $quantity, 2) : 0;

        // Marge
        $revenue = array_key_exists('revenue', $opts) && $opts['revenue'] !== null
            ? (float) $opts['revenue']
            : $this->estimateRevenue($order, $quantity);
        $margin = (int) round($revenue - $total);

        return ProductionCost::updateOrCreate(
            ['production_order_id' => $order->id],
            [
                'company_id'     => $order->company_id,
                'material_cost'  => $material,
                'labor_cost'     => $labor,
                'machine_cost'   => $machine,
                'overhead_cost'  => $overhead,
                'total_cost'     => $total,
                'standard_total' => $standardTotal,
                'variance'       => $variance,
                'cost_per_meter' => $costPerMeter,
                'cost_per_unit'  => $costPerUnit,
                'margin'         => $margin,
                'created_by'     => Auth::id(),
            ]
        );
    }

    private function machineMinutes(ProductionOrder $order, float $quantity): float
    {
        return (float) ($order->billOfMaterial->machine_time_per_unit ?? 0) * $quantity;
    }

    private function hourlyCost(ProductionOrder $order): float
    {
        return (float) ($order->productionLine?->machine?->hourly_cost ?? 0);
    }

    private function estimateRevenue(ProductionOrder $order, float $quantity): float
    {
        if ($order->product && $order->product->sale_price) {
            return (float) $order->product->sale_price * $quantity;
        }

        return (float) ($order->order->total_ttc ?? 0);
    }
}
