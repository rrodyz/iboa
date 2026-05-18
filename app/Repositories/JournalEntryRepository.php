<?php

namespace App\Repositories;

use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalEntryRepository extends BaseRepository
{
    public function __construct(JournalEntry $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = JournalEntry::query()
            ->with(['journalType'])
            ->when(
                !empty($filters['journal_type_id']),
                fn ($q) => $q->where('journal_type_id', $filters['journal_type_id'])
            )
            ->when(
                !empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                !empty($filters['date_from']),
                fn ($q) => $q->whereDate('entry_date', '>=', $filters['date_from'])
            )
            ->when(
                !empty($filters['date_to']),
                fn ($q) => $q->whereDate('entry_date', '<=', $filters['date_to'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $q2->where('number', 'like', '%' . $filters['search'] . '%')
                       ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                       ->orWhere('reference', 'like', '%' . $filters['search'] . '%');
                })
            )
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        return $query->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): JournalEntry
    {
        return JournalEntry::with([
            'journalType',
            'lines.account',
            'createdBy',
            'validatedBy',
        ])->findOrFail($id);
    }

    /**
     * Grand livre (account ledger) — all lines for an account, with running balance.
     */
    public function grandLivre(int $accountId, array $filters = []): \Illuminate\Support\Collection
    {
        return \App\Models\JournalEntryLine::query()
            ->with(['journalEntry.journalType'])
            ->where('account_id', $accountId)
            ->when(
                !empty($filters['date_from']),
                fn ($q) => $q->whereHas('journalEntry', fn ($je) => $je->whereDate('entry_date', '>=', $filters['date_from']))
            )
            ->when(
                !empty($filters['date_to']),
                fn ($q) => $q->whereHas('journalEntry', fn ($je) => $je->whereDate('entry_date', '<=', $filters['date_to']))
            )
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'valide'))
            ->orderBy(function ($sub) {
                $sub->select('entry_date')
                    ->from('journal_entries')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
                    ->limit(1);
            })
            ->get();
    }
}
