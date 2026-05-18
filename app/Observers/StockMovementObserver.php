<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\AuditService;

/**
 * Audit des mouvements de stock (entrée/sortie/ajustement/transfert).
 * Critique pour la traçabilité comptable et l'inventaire.
 *
 * Les StockMovement sont immuables après création (pas d'update prévu),
 * mais on trace quand même les rares updates et les destroy (qui devraient
 * être ultra-rares — préférer une contre-passation par mouvement opposé).
 */
class StockMovementObserver
{
    public function __construct(private AuditService $audit) {}

    public function created(StockMovement $mov): void
    {
        $this->audit->log('stock_movement', $mov, [], [
            'product_id'     => $mov->product_id,
            'warehouse_id'   => $mov->warehouse_id,
            'type'           => $mov->type,
            'quantity'       => (float) $mov->quantity,
            'unit_cost'      => (float) $mov->unit_cost,
            'reference_type' => $mov->reference_type,
            'reference_id'   => $mov->reference_id,
            'notes'          => $mov->notes,
        ]);
    }

    public function updated(StockMovement $mov): void
    {
        $changes = $mov->getChanges();
        unset($changes['updated_at'], $changes['avg_cost_after']);
        if (empty($changes)) return;

        $this->audit->log('stock_movement_modified', $mov, $mov->getOriginal(), $changes);
    }

    public function deleted(StockMovement $mov): void
    {
        $this->audit->log('stock_movement_deleted', $mov, [
            'type'         => $mov->type,
            'quantity'     => (float) $mov->quantity,
            'product_id'   => $mov->product_id,
            'warehouse_id' => $mov->warehouse_id,
        ], []);
    }
}
