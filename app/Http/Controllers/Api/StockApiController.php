<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockApiController extends Controller
{
    /**
     * Current stock levels with optional filters.
     * GET /api/stock?warehouse_id=1&product_id=5&low_stock=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductStock::with(['product:id,name,reference,stock_min,stock_max', 'warehouse:id,name'])
            ->where('quantity', '>', 0);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('low_stock')) {
            $query->whereHas('product', fn($q) => $q->whereColumn('stock_min', '>', 0))
                  ->whereRaw('quantity - reserved_quantity <= (SELECT stock_min FROM products WHERE products.id = product_stocks.product_id)');
        }

        $perPage = min((int) $request->get('per_page', 50), 200);

        $data = $query->paginate($perPage)->through(fn($s) => [
            'product_id'     => $s->product_id,
            'product_name'   => $s->product?->name,
            'reference'      => $s->product?->reference,
            'warehouse_id'   => $s->warehouse_id,
            'warehouse_name' => $s->warehouse?->name,
            'quantity'       => $s->quantity,
            'reserved'       => $s->reserved_quantity,
            'available'      => max(0, $s->quantity - $s->reserved_quantity),
            'avg_cost'       => $s->avg_cost,
            'stock_min'      => $s->product?->stock_min,
            'stock_max'      => $s->product?->stock_max,
            'updated_at'     => $s->last_movement_at?->toISOString() ?? $s->updated_at?->toISOString(),
        ]);

        return response()->json($data);
    }

    /**
     * Recent stock movements.
     * GET /api/stock/movements?product_id=5&type=sortie&limit=50
     */
    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product:id,name,reference', 'warehouse:id,name'])
            ->latest('occurred_at');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $limit = min((int) $request->get('limit', 50), 200);

        return response()->json(
            $query->limit($limit)->get()->map(fn($m) => [
                'id'             => $m->id,
                'type'           => $m->type,
                'product'        => $m->product?->name,
                'reference'      => $m->product?->reference,
                'warehouse'      => $m->warehouse?->name,
                'quantity'       => $m->quantity,
                'unit_cost'      => $m->unit_cost,
                'total_cost'     => $m->total_cost,
                'occurred_at'    => $m->occurred_at?->toISOString(),
                'reference_type' => $m->reference_type,
                'reference_id'   => $m->reference_id,
                'notes'          => $m->notes,
            ])
        );
    }
}
