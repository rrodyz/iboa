<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Supplier::class);
        $filters   = $request->only(['search', 'is_active']);
        $suppliers = $this->service->search($filters, 15);

        return view('suppliers.index', compact('suppliers', 'filters'));
    }

    public function create()
    {
        $this->authorize('create', Supplier::class);
        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);
        $supplier = $this->service->create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Fournisseur créé avec succès.');
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('view', $supplier);
        $supplier = $this->service->repository->findWithDetails($supplier->id);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $supplier->load(['contacts', 'addresses']);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $this->service->update($supplier, $request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete', $supplier);
        try {
            $this->service->delete($supplier);

            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fournisseur supprimé avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
