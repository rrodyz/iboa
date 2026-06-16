<?php

namespace App\Modules\Production\Services;

use App\Models\Reception;
use App\Modules\Production\Models\Coil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION ↔ ACHATS] Génère des bobines (matières premières) à partir d'une
 * réception fournisseur validée. Boucle MRP → demande d'achat → commande →
 * réception → bobine en stock.
 *
 * Réutilise le module Achats (Reception/ReceptionItem) — aucun doublon.
 * Idempotent : ne recrée pas de bobines si la réception en a déjà généré.
 */
class CoilReceptionService
{
    /**
     * @return array<int, Coil> bobines créées
     */
    public function createFromReception(Reception $reception): array
    {
        if (! $reception->validated_at) {
            throw ValidationException::withMessages(['status' => 'La réception doit être validée avant de générer les bobines.']);
        }

        if (Coil::where('reception_id', $reception->id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Les bobines de cette réception ont déjà été générées.']);
        }

        $reception->loadMissing('items');

        return DB::transaction(function () use ($reception) {
            $created = [];
            $i = 0;

            foreach ($reception->items as $item) {
                $weight = (float) $item->received_quantity;
                if ($weight <= 0) {
                    continue;
                }
                $i++;

                $costPerKg = (float) $item->unit_cost;

                $created[] = Coil::create([
                    'company_id'       => $reception->company_id,
                    'product_id'       => $item->product_id,
                    'supplier_id'      => $reception->supplier_id,
                    'reception_id'     => $reception->id,
                    'reference'        => 'BOB-' . $reception->number . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'lot_number'       => $item->lot_number,
                    'initial_weight'   => $weight,
                    'remaining_weight' => $weight,
                    'estimated_length' => 0,
                    'purchase_price'   => (int) round($weight * $costPerKg),
                    'cost_per_kg'      => round($costPerKg, 2),
                    'received_at'      => $reception->received_at ?? now(),
                    'status'           => 'disponible',
                    'created_by'       => Auth::id(),
                ]);
            }

            return $created;
        });
    }
}
