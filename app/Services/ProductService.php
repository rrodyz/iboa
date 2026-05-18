<?php
namespace App\Services;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Repositories\ProductRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(private ProductRepository $repository) {}

    public function create(array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $image) {
            if ($image) {
                $data['image'] = $image->store('products', 'public');
            }
            if (empty($data['reference'])) {
                $data['reference'] = $this->generateReference();
            }
            $product = Product::create($data);

            if (!empty($data['components'])) {
                foreach ($data['components'] as $component) {
                    $product->components()->create($component);
                }
            }
            return $product->load(['family', 'brand', 'unit', 'taxRate']);
        });
    }

    public function update(Product $product, array $data, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($product, $data, $image) {
            if ($image) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $image->store('products', 'public');
            }
            $product->update($data);

            if (isset($data['components'])) {
                $product->components()->delete();
                foreach ($data['components'] as $component) {
                    $product->components()->create($component);
                }
            }
            return $product->fresh(['family', 'brand', 'unit', 'taxRate']);
        });
    }

    public function delete(Product $product): bool
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        return $product->delete();
    }

    private function generateReference(): string
    {
        $last = Product::withTrashed()->orderByDesc('id')->value('reference');
        $num  = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
        return 'ART-' . str_pad($num, 5, '0', STR_PAD_LEFT);
    }

    public function getFamiliesTree(): \Illuminate\Database\Eloquent\Collection
    {
        return ProductFamily::whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();
    }
}
