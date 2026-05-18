<?php

namespace App\Repositories;

use App\Models\SupplierInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class SupplierInvoiceRepository extends BaseRepository
{
    public function __construct(SupplierInvoice $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of supplier invoices.
     *
     * Accepted filters:
     *   supplier_id – exact match
     *   status      – exact match
     *   overdue     – boolean: due_at < today AND status not in [payee, annulee]
     *   search      – matches invoice number or supplier name
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SupplierInvoice::query()
            ->leftJoin('suppliers', 'supplier_invoices.supplier_id', '=', 'suppliers.id')
            ->select('supplier_invoices.*')
            ->with(['supplier'])
            ->when(
                !empty($filters['supplier_id']),
                fn ($q) => $q->where('supplier_invoices.supplier_id', $filters['supplier_id'])
            )
            ->when(
                !empty($filters['status']),
                fn ($q) => $q->where('supplier_invoices.status', $filters['status'])
            )
            ->when(
                !empty($filters['overdue']),
                fn ($q) => $q->where('supplier_invoices.due_at', '<', Carbon::today())
                             ->whereNotIn('supplier_invoices.status', ['payee', 'annulee'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('supplier_invoices.number', 'like', $s)
                       ->orWhere('suppliers.name', 'like', $s);
                })
            )
            ->orderByDesc('supplier_invoices.received_at')
            ->orderByDesc('supplier_invoices.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single supplier invoice with all related data needed for display.
     */
    public function findWithDetails(int $id): SupplierInvoice
    {
        return SupplierInvoice::with([
            'supplier',
            'items.product',
            'payments.paymentMethod',
            'purchaseOrder',
            'createdBy',
        ])->findOrFail($id);
    }
}
