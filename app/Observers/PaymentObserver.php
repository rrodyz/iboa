<?php

namespace App\Observers;

use App\Services\AuditService;

/**
 * Observer générique pour ClientPayment et SupplierPayment.
 * Capte toutes les opérations sensibles sur les paiements.
 *
 * Mêmes hooks pour les deux classes — la classe du modèle observé est
 * stockée automatiquement par AuditLog dans `model_type`.
 */
class PaymentObserver
{
    public function __construct(private AuditService $audit) {}

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
    }
}
