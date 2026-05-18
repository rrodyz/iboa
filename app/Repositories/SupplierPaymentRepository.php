<?php

namespace App\Repositories;

use App\Models\SupplierPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierPaymentRepository extends BaseRepository
{
    public function __construct(SupplierPayment $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of supplier payments.
     *
     * Accepted filters:
     *   supplier_id       – exact match
     *   payment_method_id – exact match
     *   date_from         – payment_date >= date_from
     *   date_to           – payment_date <= date_to
     *   search            – matches number/reference or supplier name
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SupplierPayment::query()
            ->leftJoin('suppliers', 'supplier_payments.supplier_id', '=', 'suppliers.id')
            ->select('supplier_payments.*')
            ->with(['supplier', 'paymentMethod', 'allocations.supplierInvoice'])
            ->when(
                !empty($filters['supplier_id']),
                fn ($q) => $q->where('supplier_payments.supplier_id', $filters['supplier_id'])
            )
            ->when(
                !empty($filters['payment_method_id']),
                fn ($q) => $q->where('supplier_payments.payment_method_id', $filters['payment_method_id'])
            )
            ->when(
                !empty($filters['date_from']),
                fn ($q) => $q->where('supplier_payments.payment_date', '>=', $filters['date_from'])
            )
            ->when(
                !empty($filters['date_to']),
                fn ($q) => $q->where('supplier_payments.payment_date', '<=', $filters['date_to'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('supplier_payments.number', 'like', $s)
                       ->orWhere('supplier_payments.reference', 'like', $s)
                       ->orWhere('suppliers.name', 'like', $s);
                })
            )
            ->orderByDesc('supplier_payments.payment_date')
            ->orderByDesc('supplier_payments.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single supplier payment with all related data for display.
     */
    public function findWithDetails(int $id): SupplierPayment
    {
        return SupplierPayment::with([
            'supplier',
            'paymentMethod',
            'cashAccount',
            'allocations.supplierInvoice',
            'createdBy',
        ])->findOrFail($id);
    }
}
