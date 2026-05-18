<?php

namespace App\Http\Controllers;

use App\Http\Requests\Promotion\StorePromotionRequest;
use App\Http\Requests\Promotion\UpdatePromotionRequest;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductPromotion;
use Illuminate\Http\Request;

class ProductPromotionController extends Controller
{
    public function index(Request $request)
    {
        $status     = $request->input('status', 'all');
        $type       = $request->input('type');
        $product_id = $request->input('product_id');

        $promotions = ProductPromotion::with(['product', 'family'])
            ->when($status === 'active',   fn($q) => $q->active())
            ->when($status === 'expired',  fn($q) => $q->where('ends_at', '<', now()))
            ->when($status === 'upcoming', fn($q) => $q->where('starts_at', '>', now()))
            ->when($type,       fn($q) => $q->where('type', $type))
            ->when($product_id, fn($q) => $q->where('product_id', $product_id))
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString();

        $products = Product::active()->sellable()->orderBy('name')->get(['id', 'name']);

        return view('promotions.index', compact('promotions', 'status', 'products'));
    }

    public function create()
    {
        $products = Product::active()->sellable()->orderBy('name')->get(['id', 'name', 'reference']);
        $families = ProductFamily::orderBy('name')->get(['id', 'name']);
        return view('promotions.create', compact('products', 'families'));
    }

    public function store(StorePromotionRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        ProductPromotion::create($data);
        return redirect()->route('promotions.index')->with('success', 'Promotion créée avec succès.');
    }

    public function edit(ProductPromotion $promotion)
    {
        $products = Product::active()->sellable()->orderBy('name')->get(['id', 'name', 'reference']);
        $families = ProductFamily::orderBy('name')->get(['id', 'name']);
        return view('promotions.edit', compact('promotion', 'products', 'families'));
    }

    public function update(UpdatePromotionRequest $request, ProductPromotion $promotion)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $promotion->update($data);
        return redirect()->route('promotions.index')->with('success', 'Promotion mise à jour.');
    }

    public function destroy(ProductPromotion $promotion)
    {
        $promotion->delete();
        return redirect()->route('promotions.index')->with('success', 'Promotion supprimée.');
    }
}
