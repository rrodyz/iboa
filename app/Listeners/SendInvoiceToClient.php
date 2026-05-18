<?php

namespace App\Listeners;

use App\Events\InvoiceValidated;
use App\Jobs\SendInvoiceEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Automatically email a PDF invoice to the client when an invoice is validated.
 * Runs via the queue to avoid blocking the HTTP response.
 */
class SendInvoiceToClient implements ShouldQueue
{
    public function handle(InvoiceValidated $event): void
    {
        $invoice = $event->invoice->loadMissing('client');
        $client  = $invoice->client;

        if (!$client || !$client->email) {
            return;
        }

        SendInvoiceEmailJob::dispatch($invoice, $client->email, $client->name)
            ->onQueue('emails');
    }
}
