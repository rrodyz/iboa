<?php

namespace App\Listeners;

use App\Events\SupplierInvoiceValidated;

/**
 * Recalculate the supplier balance whenever a supplier invoice is validated.
 * The new payable increases the outstanding balance immediately.
 *
 * NOT queued: balance consistency is required immediately.
 */
class SyncSupplierBalanceOnInvoice
{
    public function handle(SupplierInvoiceValidated $event): void
    {
        $supplier = $event->invoice->supplier;

        if (!$supplier) {
            return;
        }

        $supplier->recalculateBalance();
    }
}
