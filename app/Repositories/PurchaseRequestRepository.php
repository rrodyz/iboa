<?php

namespace App\Repositories;

use App\Models\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequestRepository extends BaseRepository
{
    public function __construct(PurchaseRequest $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseRequest::query()
            ->leftJoin('users', 'purchase_requests.requested_by', '=', 'users.id')
            ->select('purchase_requests.*')
            ->with(['requestedBy'])
            ->when(
                !empty($filters['status']),
                fn ($q) => $q->where('purchase_requests.status', $filters['status'])
            )
            ->when(
                !empty($filters['department']),
                fn ($q) => $q->where('purchase_requests.department', $filters['department'])
            )
            ->when(
                !empty($filters['search']),
                fn ($q) => $q->where(function ($q2) use ($filters) {
                    $s = '%' . $filters['search'] . '%';
                    $q2->where('purchase_requests.number', 'like', $s)
                       ->orWhere('purchase_requests.justification', 'like', $s)
                       ->orWhere('users.name', 'like', $s);
                })
            )
            ->orderByDesc('purchase_requests.created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): PurchaseRequest
    {
        return PurchaseRequest::with([
            'items.product',
            'items.unit',
            'requestedBy',
            'approvedBy',
            'purchaseOrder',
        ])->findOrFail($id);
    }
}
