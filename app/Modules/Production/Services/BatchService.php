<?php

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * [PRODUCTION] Lots de fabrication (batch) — traçabilité produit fini.
 */
class BatchService
{
    public function createForOrder(ProductionOrder $order, ?float $quantity = null): ProductionBatch
    {
        if (in_array($order->status, ['brouillon', 'annule'], true)) {
            throw ValidationException::withMessages(['status' => 'Lot impossible sur un OF brouillon ou annulé.']);
        }

        $seq = $order->batches()->count() + 1;
        $number = 'LOT-' . $order->number . '-' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT);

        return $order->batches()->create([
            'company_id'  => $order->company_id,
            'product_id'  => $order->product_id,
            'batch_number'=> $number,
            'quantity'    => $quantity ?? (float) ($order->quantity_produced ?: $order->quantity_requested),
            'status'      => 'en_cours',
            'produced_at' => now(),
            'created_by'  => Auth::id(),
        ]);
    }

    public function close(ProductionBatch $batch): void
    {
        $batch->update(['status' => 'cloture']);
    }
}
