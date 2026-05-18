<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of orders.
     *
     * Accepted filters:
     *   client_id – exact match
     *   status    – exact match (brouillon|confirme|en_preparation|partiellement_livre|livre|facture|annule)
     *   search    – matches order number
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['client'])
            ->when(
                isset($filters['client_id']),
                fn ($q) => $q->where('orders.client_id', $filters['client_id'])
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('orders.status', $filters['status'])
            )
            ->when(
                isset($filters['search']),
                // [PERF-FIX-03] Replace correlated orWhereHas subquery with LEFT JOIN.
                fn ($q) => $q
                    ->leftJoin('clients', 'orders.client_id', '=', 'clients.id')
                    ->select('orders.*')
                    ->where(fn ($q2) => $q2
                        ->where('orders.number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('clients.name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('clients.trade_name', 'like', '%' . $filters['search'] . '%')
                    )
            )
            ->orderByDesc('orders.issued_at')
            ->orderByDesc('orders.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single order with all related data needed for display.
     */
    public function findWithDetails(int $id): Order
    {
        return Order::with([
            'client',
            'items.product',
            'items.unit',
            'deliveryNotes',
            'invoices',
            'quote',
            'createdBy',
        ])->findOrFail($id);
    }
}
