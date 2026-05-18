<?php

namespace App\Listeners;

use App\Events\SupplierPaymentCreated;

/**
 * Recalculate the supplier's outstanding balance after a payment is recorded.
 *
 * NOT queued intentionally: balance is critical business data that must be
 * consistent immediately. With QUEUE_CONNECTION=database a queued listener
 * would only run when the queue worker is active.
 */
class UpdateSupplierBalance
{
    public function handle(SupplierPaymentCreated $event): void
    {
        $supplier = $event->payment->supplier;

        if (!$supplier) {
            return;
        }

        $supplier->recalculateBalance();
    }
}
