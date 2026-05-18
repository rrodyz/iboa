<?php

namespace App\Listeners;

use App\Events\CreditNoteValidated;
use App\Mail\CreditNoteValidatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Log and notify the client when a credit note is validated.
 * Runs asynchronously via the queue so it never blocks the HTTP response.
 */
class NotifyCreditNoteValidated implements ShouldQueue
{
    public function handle(CreditNoteValidated $event): void
    {
        $cn = $event->creditNote->loadMissing(['client', 'invoice', 'company']);

        Log::info('Avoir validé', [
            'number'    => $cn->number,
            'client'    => $cn->client?->name,
            'total_ttc' => $cn->total_ttc,
            'invoice'   => $cn->invoice?->number,
        ]);

        // Email the client a copy of the validated credit note (with PDF attachment)
        $email = $cn->client?->email;
        if (!$email) {
            Log::notice('CreditNoteValidated: client has no email, skipping notification', [
                'credit_note' => $cn->number,
            ]);
            return;
        }

        Mail::to($email)
            ->queue(new CreditNoteValidatedMail($cn));
    }
}
