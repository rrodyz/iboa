<?php

namespace App\Repositories;

use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierRepository extends BaseRepository
{
    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query()->withCount('contacts');

        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'like', $s)
                ->orWhere('code', 'like', $s)
                ->orWhere('email', 'like', $s)
                ->orWhere('phone', 'like', $s)
            );
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): Supplier
    {
        return $this->model
            ->with([
                'contacts',
                'addresses',
                'purchaseConditions.product',
                'purchaseOrders' => fn ($q) => $q->latest()->limit(5),
            ])
            ->findOrFail($id);
    }
}
