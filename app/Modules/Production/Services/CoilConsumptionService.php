<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Consommation de bobines (matière première) sur un OF.
 *
 * La bobine est la source de vérité du stock matière (poids + lot + coût/kg).
 * On ne double-track PAS la matière dans product_stocks pour éviter les
 * doublons avec le module stock — le poids restant de la bobine fait foi.
 */
class CoilConsumptionService
{
    /** Enregistre une consommation de matière depuis une bobine. */
    public function consume(ProductionOrder $order, Coil $coil, float $weight, ?float $length = null, ?string $date = null): ProductionConsumption
    {
        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'La consommation n\'est possible que sur un OF « en cours ».']);
        }
        if ($weight <= 0) {
            throw ValidationException::withMessages(['weight' => 'Le poids consommé doit être positif.']);
        }
        if ($weight > (float) $coil->remaining_weight + 0.001) {
            throw ValidationException::withMessages([
                'weight' => 'Poids demandé (' . $weight . ' kg) supérieur au restant de la bobine (' . $coil->remaining_weight . ' kg).',
            ]);
        }

        return DB::transaction(function () use ($order, $coil, $weight, $length, $date) {
            $cost = (int) round($weight * (float) $coil->cost_per_kg);

            $consumption = $order->consumptions()->create([
                'company_id'      => $order->company_id,
                'coil_id'         => $coil->id,
                'weight_consumed' => $weight,
                'length_consumed' => $length ?? 0,
                'cost'            => $cost,
                'consumed_at'     => $date ?? now(),
                'created_by'      => Auth::id(),
            ]);

            $this->applyCoilDelta($coil, -$weight);

            return $consumption;
        });
    }

    /** Annule une consommation : restitue le poids à la bobine. */
    public function reverse(ProductionConsumption $consumption): void
    {
        $order = $consumption->productionOrder;
        if ($order && ! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Annulation impossible : l\'OF n\'est plus « en cours ».']);
        }

        DB::transaction(function () use ($consumption) {
            if ($coil = $consumption->coil) {
                $this->applyCoilDelta($coil, (float) $consumption->weight_consumed);
            }
            $consumption->delete();
        });
    }

    /** Applique une variation de poids à la bobine et resynchronise son statut. */
    private function applyCoilDelta(Coil $coil, float $delta): void
    {
        $coil = Coil::lockForUpdate()->find($coil->id);
        $remaining = max(0, round((float) $coil->remaining_weight + $delta, 2));

        $status = match (true) {
            $remaining <= 0.001                            => 'epuisee',
            $remaining < (float) $coil->initial_weight     => 'en_production',
            default                                        => 'disponible',
        };

        $coil->update(['remaining_weight' => $remaining, 'status' => $status]);
    }
}
