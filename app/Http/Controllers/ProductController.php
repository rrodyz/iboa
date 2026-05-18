<?php
namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Account;
use App\Models\Brand;
use App\Models\ProductFamily;
use App\Models\ProductPromotion;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $service,
        private ProductRepository $repository
    ) {}

    public function index(Request $request): View|BinaryFileResponse
    {
        $this->authorize('viewAny', Product::class);
        if ($request->boolean('export')) {
            return Excel::download(
                new ProductsExport($request->only(['search', 'family_id', 'brand_id', 'type'])),
                'articles-' . now()->format('Ymd') . '.xlsx'
            );
        }

        $products  = $this->repository->search($request->all(), 20);
        $families  = ProductFamily::whereNull('parent_id')->with('children')->orderBy('name')->get();
        $brands    = Brand::where('is_active', true)->orderBy('name')->get();
        return view('products.index', compact('products', 'families', 'brands'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);
        [$families, $brands, $units, $taxRates, $suppliers, $accounts, $componentProducts] =
            $this->loadFormReferenceData();
        return view('products.create', compact(
            'families', 'brands', 'units', 'taxRates', 'suppliers', 'accounts', 'componentProducts'
        ));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $product = $this->service->create($request->validated(), $request->file('image'));
        return redirect()->route('products.show', $product)->with('success', 'Article créé avec succès.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);
        $product = $this->repository->findWithDetails($product->id);

        $promotions = ProductPromotion::where(function ($q) use ($product) {
            $q->where('product_id', $product->id);
            if ($product->family_id) {
                $q->orWhere('family_id', $product->family_id);
            }
        })->orderByDesc('starts_at')->get();

        $recentMovements = $product->stockMovements()
            ->with('warehouse')
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        return view('products.show', compact('product', 'promotions', 'recentMovements'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);
        $product = $this->repository->findWithDetails($product->id);
        [$families, $brands, $units, $taxRates, $suppliers, $accounts, $componentProducts] =
            $this->loadFormReferenceData($product->id);
        return view('products.edit', compact(
            'product', 'families', 'brands', 'units', 'taxRates', 'suppliers', 'accounts', 'componentProducts'
        ));
    }

    /**
     * Charge les données de référence partagées par create/edit.
     * @return array{0:mixed,1:mixed,2:mixed,3:mixed,4:mixed,5:mixed,6:mixed}
     */
    private function loadFormReferenceData(?int $excludeProductId = null): array
    {
        $families = $this->service->getFamiliesTree();
        $brands   = Brand::where('is_active', true)->orderBy('name')->get();
        $units    = Unit::where('is_active', true)->orderBy('name')->get();
        $taxRates = TaxRate::where('is_active', true)->orderBy('rate')->get();

        $suppliers = Supplier::orderBy('name')->get(['id', 'code', 'name']);

        // Plan comptable filtré pour ventes (7xx), achats (6xx), stock (3xx)
        $accounts = Account::orderBy('code')->get(['id', 'code', 'name', 'company_id']);

        $componentProductsQ = Product::where('is_active', true)
            ->whereIn('type', ['simple', 'service']);
        if ($excludeProductId) {
            $componentProductsQ->where('id', '!=', $excludeProductId);
        }
        $componentProducts = $componentProductsQ->orderBy('name')->get(['id', 'name', 'reference']);

        return [$families, $brands, $units, $taxRates, $suppliers, $accounts, $componentProducts];
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $this->service->update($product, $request->validated(), $request->file('image'));
        return redirect()->route('products.show', $product)->with('success', 'Article mis à jour.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $this->service->delete($product);
        return redirect()->route('products.index')->with('success', 'Article supprimé.');
    }
}
