<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository extends BaseRepository
{
    public function __construct(PurchaseOrder $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of purchase orders.
     *
     * Accepted filters:
     *   supplier_id – exact match
     *   status      – exact match
     *   search      – matches PO number or supplier name
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*')
            ->with(['supplier'])
            ->when(
                !empty($filters['supplier_id']),
                fn ($q) => $q->where('purchase_orders.supplier_id', $filters['supplier_id'])
            )
            ->when(
                !empty($filters['status']),
                fn ($q) => $q->where('purchase_orders.status', $filters['status'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('purchase_orders.number', 'like', $s)
                       ->orWhere('suppliers.name', 'like', $s);
                })
            )
            ->orderByDesc('purchase_orders.ordered_at')
            ->orderByDesc('purchase_orders.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single purchase order with all related data needed for display.
     */
    public function findWithDetails(int $id): PurchaseOrder
    {
        return PurchaseOrder::with([
            'supplier',
            'items.product',
            'receptions',
            'supplierInvoices',
            'createdBy',
        ])->findOrFail($id);
    }
}
