<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\AuditService;

/**
 * Audit trail des opérations sur les factures clients.
 *
 * Trace dans `audit_logs` :
 *  - created  : numéro, client, montant, statut initial
 *  - updated  : champs modifiés (avant/après)
 *  - deleted  : numéro, client, montant
 *  - restored : restauration soft-delete
 *
 * Les listings d'audit sont consultables via /audit (AuditController).
 */
class InvoiceObserver
{
    public function __construct(private AuditService $audit) {}

    /** Champs dont la modification n'est PAS utile à tracer (bruit) */
    private const IGNORED_FIELDS = [
        'updated_at',
        // Recalculés automatiquement à chaque update : ne pas polluer le journal
        'subtotal_ht', 'total_tax', 'total_ttc', 'total_discount',
        'withholding_details', 'withholding_amount', 'net_to_pay',
        'remaining_amount', 'paid_amount',
    ];

    public function created(Invoice $invoice): void
    {
        $this->audit->log('created', $invoice, [], [
            'number'   => $invoice->number,
            'client_id' => $invoice->client_id,
            'status'   => $invoice->status,
            'type'     => $invoice->type,
            'total_ttc' => (int) $invoice->total_ttc,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at'   => $invoice->due_at?->toDateString(),
        ]);
    }

    public function updated(Invoice $invoice): void
    {
        $changes = $invoice->getChanges();
        $original = $invoice->getOriginal();

        // Retire les champs ignorés
        foreach (self::IGNORED_FIELDS as $field) {
            unset($changes[$field]);
        }

        if (empty($changes)) {
            return; // rien à tracer (only changements ignorés)
        }

        // Construit le diff (avant/après)
        $old = [];
        $new = [];
        foreach ($changes as $field => $value) {
            $old[$field] = $original[$field] ?? null;
            $new[$field] = $value;
        }

        // Action dérivée du diff de status (priorité)
        $action = match (true) {
            isset($changes['status']) && $changes['status'] === 'emise'    => 'validated',
            isset($changes['status']) && $changes['status'] === 'envoyee'  => 'sent',
            isset($changes['status']) && $changes['status'] === 'annulee'  => 'cancelled',
            isset($changes['status']) && $changes['status'] === 'payee'    => 'paid',
            isset($changes['status']) && $changes['status'] === 'partiellement_payee' => 'partially_paid',
            isset($changes['status']) && $changes['status'] === 'en_retard' => 'overdue',
            default => 'updated',
        };

        $this->audit->log(
            $action,
            $invoice,
            array_merge(['number' => $invoice->number], $old),
            array_merge(['number' => $invoice->number], $new),
        );
    }

    public function deleted(Invoice $invoice): void
    {
        $this->audit->log('deleted', $invoice, [
            'number'   => $invoice->number,
            'status'   => $invoice->status,
            'total_ttc' => (int) $invoice->total_ttc,
        ], []);
    }

    public function restored(Invoice $invoice): void
    {
        $this->audit->log('restored', $invoice, [], [
            'number' => $invoice->number,
        ]);
    }
}
