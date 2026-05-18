<?php

namespace App\Listeners;

use App\Events\SupplierInvoiceValidated;
use App\Mail\SupplierInvoiceValidatedMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Log and notify the finance / accounting team when a supplier invoice is validated.
 * Recipients are all active users with roles: comptable, directeur, or super_admin.
 * Runs asynchronously via the queue so it never blocks the HTTP response.
 */
class NotifySupplierInvoiceValidated implements ShouldQueue
{
    public function handle(SupplierInvoiceValidated $event): void
    {
        $invoice = $event->invoice->loadMissing(['supplier', 'company']);

        Log::info('Facture fournisseur validée', [
            'number'    => $invoice->number,
            'supplier'  => $invoice->supplier?->name,
            'total_ttc' => $invoice->total_ttc,
            'due_at'    => $invoice->due_at,
        ]);

        // Notify finance / accounting team members
        $recipients = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['comptable', 'directeur', 'super_admin'])
        )
        ->where('is_active', true)
        ->whereNotNull('email')
        ->get();

        if ($recipients->isEmpty()) {
            Log::notice('SupplierInvoiceValidated: no finance-team recipients found, skipping email', [
                'invoice' => $invoice->number,
            ]);
            return;
        }

        foreach ($recipients as $user) {
            Mail::to($user->email)
                ->queue(new SupplierInvoiceValidatedMail($invoice));
        }
    }
}
