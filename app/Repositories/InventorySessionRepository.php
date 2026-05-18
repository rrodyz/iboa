<?php

namespace App\Repositories;

use App\Models\InventorySession;
use Illuminate\Pagination\LengthAwarePaginator;

class InventorySessionRepository extends BaseRepository
{
    public function __construct(InventorySession $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->with(['warehouse', 'createdBy'])
            ->withCount('items');

        if (!empty($filters['warehouse_id'])) {
            $query->where('inventory_sessions.warehouse_id', $filters['warehouse_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('inventory_sessions.status', $filters['status']);
        }

        return $query->orderByDesc('inventory_sessions.started_at')->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): InventorySession
    {
        return $this->model
            ->with(['warehouse', 'items.product', 'items.countedBy', 'createdBy'])
            ->findOrFail($id);
    }
}
