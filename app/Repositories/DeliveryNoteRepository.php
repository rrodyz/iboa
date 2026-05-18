<?php

namespace App\Repositories;

use App\Models\DeliveryNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryNoteRepository extends BaseRepository
{
    public function __construct(DeliveryNote $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of delivery notes.
     *
     * Accepted filters:
     *   client_id – exact match
     *   status    – exact match (brouillon|valide|livre|annule)
     *   order_id  – exact match
     *   search    – matches delivery note number
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DeliveryNote::query()
            ->with(['client'])
            ->when(
                isset($filters['client_id']),
                fn ($q) => $q->where('client_id', $filters['client_id'])
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['order_id']),
                fn ($q) => $q->where('order_id', $filters['order_id'])
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where('number', 'like', '%' . $filters['search'] . '%')
            )
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single delivery note with all related data needed for display.
     */
    public function findWithDetails(int $id): DeliveryNote
    {
        return DeliveryNote::with([
            'client',
            'order.quote',
            'order.invoices',
            'invoices',
            'items.product',
            'items.unit',
            'warehouse',
            'createdBy',
        ])->findOrFail($id);
    }
}
