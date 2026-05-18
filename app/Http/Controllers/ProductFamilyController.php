<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductFamily\StoreProductFamilyRequest;
use App\Http\Requests\ProductFamily\UpdateProductFamilyRequest;
use App\Models\Account;
use App\Models\ProductFamily;
use Illuminate\Http\Request;

class ProductFamilyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index()
    {
        $families = ProductFamily::with(['children' => fn($q) => $q->withCount('products')])
            ->withCount('products')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('product-families.index', compact('families'));
    }

    public function create()
    {
        $parents  = ProductFamily::where('depth', 0)->orderBy('name')->get();
        $accounts = $this->loadAccountsByType();
        return view('product-families.create', compact('parents', 'accounts'));
    }

    public function store(StoreProductFamilyRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['depth']     = $data['parent_id'] ? 1 : 0;

        ProductFamily::create($data);
        return redirect()->route('product-families.index')->with('success', 'Famille créée avec succès.');
    }

    public function edit(ProductFamily $family)
    {
        $parents = ProductFamily::where('depth', 0)
            ->where('id', '!=', $family->id)
            ->orderBy('name')
            ->get();

        $accounts = $this->loadAccountsByType();

        return view('product-families.edit', compact('family', 'parents', 'accounts'));
    }

    /**
     * Récupère les comptes comptables groupés par usage (vente / achat / stock).
     * Filtre sur le 1er chiffre du code (norme SYSCOA/OHADA) :
     *  - 7xx : Produits (ventes)
     *  - 6xx : Charges (achats)
     *  - 3xx : Stocks
     */
    private function loadAccountsByType(): array
    {
        $all = Account::active()->postable()->orderBy('code')->get(['id', 'code', 'name']);

        return [
            'sale'     => $all->filter(fn($a) => str_starts_with($a->code, '7'))->values(),
            'purchase' => $all->filter(fn($a) => str_starts_with($a->code, '6'))->values(),
            'stock'    => $all->filter(fn($a) => str_starts_with($a->code, '3'))->values(),
        ];
    }

    public function update(UpdateProductFamilyRequest $request, ProductFamily $family)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['depth']     = $data['parent_id'] ? 1 : 0;

        $family->update($data);
        return redirect()->route('product-families.index')->with('success', 'Famille mise à jour.');
    }

    public function destroy(ProductFamily $family)
    {
        if ($family->products()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des articles appartiennent à cette famille.');
        }
        if ($family->children()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : cette famille contient des sous-familles.');
        }
        $family->delete();
        return redirect()->route('product-families.index')->with('success', 'Famille supprimée.');
    }

    /**
     * Quick-create via AJAX — retourne JSON {id, name}.
     */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'code'      => 'nullable|string|max:30|unique:product_families,code',
            'parent_id' => 'nullable|exists:product_families,id',
        ]);
        $data['is_active'] = true;
        $data['depth']     = isset($data['parent_id']) ? 1 : 0;

        $family = ProductFamily::create($data);

        $label = $data['depth'] === 1
            ? ProductFamily::find($data['parent_id'])?->name . ' › ' . $family->name
            : $family->name;

        return response()->json(['id' => $family->id, 'name' => $family->name, 'label' => $label], 201);
    }
}
