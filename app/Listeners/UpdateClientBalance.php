<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;

/**
 * Recalculate the client's outstanding balance whenever a payment is received.
 *
 * NOT queued intentionally: balance is critical business data that must be
 * consistent immediately. With QUEUE_CONNECTION=database a queued listener
 * would only run when the queue worker is active.
 */
class UpdateClientBalance
{
    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment->loadMissing(['client', 'allocations.invoice']);
        $client  = $payment->client;

        if (!$client) {
            return;
        }

        $client->recalculateBalance();

        // Notify comptable / directeur of new payment
        User::whereHas('roles', fn($q) => $q->whereIn('name', ['comptable', 'directeur', 'super_admin']))
            ->get()
            ->each(fn($u) => $u->notify(new PaymentReceivedNotification($payment)));
    }
}
