<?php

namespace App\Http\Controllers;

use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $brands = Brand::withCount('products')
            ->when($search, fn($q) => $q->where('name', 'like', "%$search%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('brands.index', compact('brands', 'search'));
    }

    public function create()
    {
        return view('brands.create');
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        Brand::create($data);
        return redirect()->route('brands.index')->with('success', 'Marque créée avec succès.');
    }

    public function edit(Brand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $brand->update($data);
        return redirect()->route('brands.index')->with('success', 'Marque mise à jour.');
    }

    public function destroy(Brand $brand)
    {
        if ($brand->products()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des articles utilisent cette marque.');
        }
        $brand->delete();
        return redirect()->route('brands.index')->with('success', 'Marque supprimée.');
    }

    /**
     * Quick-create via AJAX — retourne JSON {id, name}.
     */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name',
        ]);
        $data['is_active'] = true;

        $brand = Brand::create($data);

        return response()->json(['id' => $brand->id, 'name' => $brand->name], 201);
    }
}
