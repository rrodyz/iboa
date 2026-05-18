<?php

namespace App\Repositories;

use App\Models\Quote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class QuoteRepository extends BaseRepository
{
    public function __construct(Quote $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of quotes.
     *
     * Accepted filters:
     *   client_id  – exact match
     *   status     – exact match (brouillon|envoye|accepte|refuse|expire|annule)
     *   date_from  – issued_at >= value (Y-m-d)
     *   date_to    – issued_at <= value (Y-m-d)
     *   search     – matches quote number or client name
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Quote::query()
            ->leftJoin('clients', 'quotes.client_id', '=', 'clients.id')
            ->select('quotes.*')
            ->with(['client'])
            ->when(
                isset($filters['client_id']),
                fn ($q) => $q->where('quotes.client_id', $filters['client_id'])
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('quotes.status', $filters['status'])
            )
            ->when(
                isset($filters['date_from']),
                fn ($q) => $q->where('quotes.issued_at', '>=', $filters['date_from'])
            )
            ->when(
                isset($filters['date_to']),
                fn ($q) => $q->where('quotes.issued_at', '<=', $filters['date_to'])
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('quotes.number', 'like', $s)
                       ->orWhere('clients.name', 'like', $s)
                       ->orWhere('clients.trade_name', 'like', $s);
                })
            )
            ->orderByDesc('quotes.issued_at')
            ->orderByDesc('quotes.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single quote with all related data needed for display.
     */
    public function findWithDetails(int $id): Quote
    {
        return Quote::with([
            'client',
            'items.product',
            'items.unit',
            'createdBy',
            'convertedOrder',
        ])->findOrFail($id);
    }
}
