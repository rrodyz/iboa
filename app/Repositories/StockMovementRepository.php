<?php

namespace App\Repositories;

use App\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

class StockMovementRepository extends BaseRepository
{
    public function __construct(StockMovement $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model
            ->leftJoin('products', 'stock_movements.product_id', '=', 'products.id')
            ->select('stock_movements.*')
            ->with(['product', 'warehouse', 'createdBy']);

        if (!empty($filters['product_id'])) {
            $query->where('stock_movements.product_id', $filters['product_id']);
        }
        if (!empty($filters['warehouse_id'])) {
            $query->where('stock_movements.warehouse_id', $filters['warehouse_id']);
        }
        if (!empty($filters['type'])) {
            $query->where('stock_movements.type', $filters['type']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('stock_movements.occurred_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('stock_movements.occurred_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $query->where(fn($q) => $q->where('products.name', 'like', $s)
                                      ->orWhere('products.reference', 'like', $s));
        }

        return $query->orderByDesc('stock_movements.occurred_at')->orderByDesc('stock_movements.id')->paginate($perPage)->withQueryString();
    }
}
