<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Services\AuditService;

/**
 * Audit des écritures comptables — feature ultra-critique.
 *
 * Les écritures ne peuvent JAMAIS être modifiées après validation.
 * Toute opération (création, validation, contre-passation, suppression
 * d'un brouillon) est tracée pour la traçabilité comptable.
 */
class JournalEntryObserver
{
    public function __construct(private AuditService $audit) {}

    public function created(JournalEntry $entry): void
    {
        $this->audit->log('journal_entry_created', $entry, [], [
            'number'        => $entry->number,
            'reference'     => $entry->reference,
            'journal_type_id' => $entry->journal_type_id,
            'entry_date'    => $entry->entry_date?->toDateString(),
            'description'   => $entry->description,
            'total_debit'   => (float) $entry->total_debit,
            'total_credit'  => (float) $entry->total_credit,
            'status'        => $entry->status,
        ]);
    }

    public function updated(JournalEntry $entry): void
    {
        $changes = $entry->getChanges();
        unset($changes['updated_at']);
        if (empty($changes)) return;

        // Action dérivée du diff de statut
        $action = match (true) {
            isset($changes['status']) && $changes['status'] === 'valide'  => 'journal_entry_validated',
            isset($changes['status']) && $changes['status'] === 'annule'  => 'journal_entry_reversed',
            default => 'journal_entry_modified',
        };

        $this->audit->log(
            $action,
            $entry,
            array_intersect_key($entry->getOriginal(), $changes),
            $changes
        );
    }

    public function deleted(JournalEntry $entry): void
    {
        $this->audit->log('journal_entry_deleted', $entry, [
            'number'       => $entry->number,
            'status'       => $entry->status,
            'total_debit'  => (float) $entry->total_debit,
            'total_credit' => (float) $entry->total_credit,
        ], []);
    }
}
