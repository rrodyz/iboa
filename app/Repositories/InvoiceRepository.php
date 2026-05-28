<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class InvoiceRepository extends BaseRepository
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * Paginated, filtered list of invoices.
     *
     * Accepted filters:
     *   client_id – exact match
     *   status    – exact match
     *   overdue   – boolean: due_at < today AND status not in [payee, annulee]
     *   search    – matches invoice number or client name
     *   date_from – issued_at >= date_from
     *   date_to   – issued_at <= date_to
     */
    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['client'])
            ->when(
                !empty($filters['client_id']),
                fn ($q) => $q->where('invoices.client_id', $filters['client_id'])
            )
            ->when(
                !empty($filters['status']),
                fn ($q) => $q->where('invoices.status', $filters['status'])
            )
            // [UX-3] Filtre par type de facture (standard, proforma, acompte…)
            ->when(
                !empty($filters['type']),
                fn ($q) => $q->where('invoices.type', $filters['type'])
            )
            ->when(
                !empty($filters['overdue']),
                fn ($q) => $q->where('invoices.due_at', '<', Carbon::today())
                             ->whereNotIn('invoices.status', ['payee', 'annulee'])
            )
            ->when(
                !empty($filters['search']),
                // [PERF-FIX-03] Replace correlated orWhereHas subquery with a single LEFT JOIN.
                // orWhereHas generates "WHERE EXISTS (SELECT …)" which can't use indexes efficiently;
                // a LEFT JOIN lets MySQL use the FK index on invoices.client_id and the name index
                // on clients without a correlated subquery per row.
                fn ($q) => $q
                    ->leftJoin('clients', 'invoices.client_id', '=', 'clients.id')
                    ->select('invoices.*')
                    ->where(fn ($q2) => $q2
                        ->where('invoices.number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('clients.name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('clients.trade_name', 'like', '%' . $filters['search'] . '%')
                    )
            )
            ->when(
                !empty($filters['date_from']),
                fn ($q) => $q->whereDate('invoices.issued_at', '>=', $filters['date_from'])
            )
            ->when(
                !empty($filters['date_to']),
                fn ($q) => $q->whereDate('invoices.issued_at', '<=', $filters['date_to'])
            )
            ->orderByDesc('invoices.issued_at')
            ->orderByDesc('invoices.id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Load a single invoice with all related data needed for display.
     */
    public function findWithDetails(int $id): Invoice
    {
        return Invoice::with([
            'client.addresses',
            'items.product',
            'items.unit',
            'creditNotes',
            'payments.paymentMethod',
            'paymentSchedules',
            'createdBy',
            'validatedBy',
            'order.quote',
            'deliveryNote',
        ])->findOrFail($id);
    }
}
