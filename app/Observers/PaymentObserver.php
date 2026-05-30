<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Log;

/**
 * Observer générique pour ClientPayment et SupplierPayment.
 * Capte toutes les opérations sensibles sur les paiements.
 *
 * Mêmes hooks pour les deux classes — la classe du modèle observé est
 * stockée automatiquement par AuditLog dans `model_type`.
 */
class PaymentObserver
{
    public function __construct(
        private AuditService     $audit,
        private AccountingService $accounting,
    ) {}

    public function created($payment): void
    {
        $this->audit->log('payment_created', $payment, [], [
            'number'        => $payment->number ?? null,
            'date'          => $payment->payment_date?->toDateString(),
            'amount'        => (float) $payment->amount,
            'method'        => $payment->paymentMethod?->name ?? null,
            'cash_account'  => $payment->cashAccount?->name ?? null,
            'reference'     => $payment->reference ?? null,
            'status'        => $payment->status ?? null,
        ]);
    }

    public function updated($payment): void
    {
        $changes = $payment->getChanges();
        unset($changes['updated_at']);
        if (empty($changes)) return;

        $action = match (true) {
            isset($changes['status']) && $changes['status'] === 'valide'  => 'payment_validated',
            isset($changes['status']) && $changes['status'] === 'annule'  => 'payment_cancelled',
            default => 'payment_modified',
        };

        $this->audit->log(
            $action,
            $payment,
            array_intersect_key($payment->getOriginal(), $changes),
            $changes
        );
    }

    public function deleted($payment): void
    {
        $this->audit->log('payment_deleted', $payment, [
            'number' => $payment->number ?? null,
            'amount' => (float) $payment->amount,
            'status' => $payment->status ?? null,
        ], []);

        // [FIX-COMPTA-SUPPRESSION] Contre-passer automatiquement l'écriture GL
        // lorsqu'un paiement est soft-deleté, pour éviter des soldes fantômes.
        $journalEntryId = $payment->journal_entry_id ?? null;
        if (!$journalEntryId) {
            return;
        }

        $entry = JournalEntry::find($journalEntryId);
        if (!$entry || $entry->reversed_by_entry_id) {
            return; // déjà contre-passée ou introuvable
        }

        try {
            $this->accounting->reverseEntry(
                $entry,
                'Annulation paiement supprimé ' . ($payment->number ?? '')
            );
        } catch (\Throwable $e) {
            // On journalise l'erreur sans bloquer la suppression
            Log::error('PaymentObserver: échec contre-passation JE lors suppression', [
                'payment_id'       => $payment->id,
                'payment_number'   => $payment->number,
                'journal_entry_id' => $journalEntryId,
                'error'            => $e->getMessage(),
            ]);
        }
    }
}
