<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with(['family', 'unit', 'taxRate', 'productStocks.warehouse'])
            ->where('is_active', true);

        if ($request->filled('q')) {
            $like = '%'.$request->q.'%';
            $query->where(fn($q) => $q->where('name', 'like', $like)
                                      ->orWhere('reference', 'like', $like)
                                      ->orWhere('barcode', 'like', $like));
        }

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->family_id);
        }

        if ($request->filled('is_stockable')) {
            $query->where('is_stockable', (bool) $request->is_stockable);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return ProductResource::collection($query->paginate($perPage));
    }

    /**
     * Lightweight search for autocomplete / document-line pickers.
     *
     * Returns at most 20 active products matching ?q= (name, reference, barcode).
     * Response is intentionally compact — no pagination envelope, just a flat array
     * so Alpine.js / Select2 dropdowns can consume it directly.
     *
     * Optional filters: family_id, is_stockable
     *
     * GET /api/products/search?q=ciment&family_id=3&is_stockable=1
     */
    public function search(Request $request): JsonResponse
    {
        $query = Product::with(['unit:id,abbreviation', 'taxRate:id,rate'])
            ->where('is_active', true);

        if ($request->filled('q')) {
            $like = '%' . $request->q . '%';
            $query->where(fn ($q) =>
                $q->where('name',      'like', $like)
                  ->orWhere('reference', 'like', $like)
                  ->orWhere('barcode',   'like', $like)
            );
        }

        if ($request->filled('family_id')) {
            $query->where('family_id', $request->family_id);
        }

        if ($request->filled('is_stockable')) {
            $query->where('is_stockable', (bool) $request->is_stockable);
        }

        $products = $query->orderBy('name')->limit(20)->get([
            'id', 'reference', 'name', 'barcode',
            'sale_price', 'purchase_price',
            'is_stockable', 'stock_min',
            'unit_id', 'tax_rate_id', 'family_id',
        ]);

        return response()->json(
            $products->map(fn ($p) => [
                'id'             => $p->id,
                'reference'      => $p->reference,
                'name'           => $p->name,
                'barcode'        => $p->barcode,
                'sale_price'     => $p->sale_price,
                'purchase_price' => $p->purchase_price,
                'is_stockable'   => $p->is_stockable,
                'unit'           => $p->unit ? [
                    'id'           => $p->unit->id,
                    'abbreviation' => $p->unit->abbreviation,
                ] : null,
                'tax_rate'       => $p->taxRate?->rate,
            ])
        );
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['family', 'unit', 'taxRate', 'productStocks.warehouse']);
        return new ProductResource($product);
    }
}
