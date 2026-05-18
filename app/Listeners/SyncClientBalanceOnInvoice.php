<?php

namespace App\Listeners;

use App\Events\InvoiceValidated;

/**
 * Recalculate the client balance whenever an invoice is validated (status → emise).
 * The new receivable increases the outstanding balance immediately.
 *
 * NOT queued: balance consistency is required immediately.
 */
class SyncClientBalanceOnInvoice
{
    public function handle(InvoiceValidated $event): void
    {
        $client = $event->invoice->client;

        if (!$client) {
            return;
        }

        $client->recalculateBalance();
    }
}
