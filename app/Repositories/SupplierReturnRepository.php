<?php

namespace App\Repositories;

use App\Models\SupplierReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierReturnRepository extends BaseRepository
{
    public function __construct(SupplierReturn $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of supplier returns.
     *
     * Filters: supplier_id, status, search (number, supplier name)
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SupplierReturn::query()
            ->leftJoin('suppliers', 'supplier_returns.supplier_id', '=', 'suppliers.id')
            ->select('supplier_returns.*')
            ->with(['supplier'])
            ->when(
                !empty($filters['supplier_id']),
                fn ($q) => $q->where('supplier_returns.supplier_id', $filters['supplier_id'])
            )
            ->when(
                !empty($filters['status']),
                fn ($q) => $q->where('supplier_returns.status', $filters['status'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('supplier_returns.number', 'like', $s)
                       ->orWhere('suppliers.name', 'like', $s);
                })
            )
            ->orderByDesc('supplier_returns.returned_at')
            ->orderByDesc('supplier_returns.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single return with all related data needed for the show view.
     */
    public function findWithDetails(int $id): SupplierReturn
    {
        return SupplierReturn::with([
            'supplier',
            'items.product',
            'items.unit',
            'purchaseOrder',
            'reception',
            'supplierInvoice',
            'createdBy',
            'validatedBy',
        ])->findOrFail($id);
    }
}
