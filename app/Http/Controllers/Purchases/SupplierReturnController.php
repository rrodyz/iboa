<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreSupplierReturnRequest;
use App\Http\Requests\Purchase\UpdateSupplierReturnRequest;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\SupplierReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierReturnController extends Controller
{
    public function __construct(private SupplierReturnService $service) {}

    public function index(Request $request): View
    {
        $filters   = $request->only(['supplier_id', 'status', 'search']);
        $returns   = $this->service->search($filters, 15);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);

        return view('achats.retours-fournisseurs.index', compact('returns', 'filters', 'suppliers'));
    }

    public function create(): View
    {
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.retours-fournisseurs.create', compact('suppliers', 'products'));
    }

    public function store(StoreSupplierReturnRequest $request): RedirectResponse
    {
        $return = $this->service->create($request->validated());

        return redirect()
            ->route('achats.retours-fournisseurs.show', $return)
            ->with('success', 'Retour fournisseur ' . $return->number . ' créé avec succès.');
    }

    public function show(SupplierReturn $retoursFournisseurs): View
    {
        $return = $this->service->repository->findWithDetails($retoursFournisseurs->id);

        return view('achats.retours-fournisseurs.show', compact('return'));
    }

    public function edit(SupplierReturn $retoursFournisseurs): View|RedirectResponse
    {
        if (! $retoursFournisseurs->isEditable()) {
            return back()->with('error', 'Ce retour ne peut plus être modifié.');
        }

        $return    = $this->service->repository->findWithDetails($retoursFournisseurs->id);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.retours-fournisseurs.edit', compact('return', 'suppliers', 'products'));
    }

    public function update(UpdateSupplierReturnRequest $request, SupplierReturn $retoursFournisseurs): RedirectResponse
    {
        try {
            $this->service->update($retoursFournisseurs, $request->validated());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('achats.retours-fournisseurs.show', $retoursFournisseurs)
            ->with('success', 'Retour ' . $retoursFournisseurs->number . ' mis à jour.');
    }

    public function pdf(SupplierReturn $retoursFournisseurs): mixed
    {
        $return  = $this->service->repository->findWithDetails($retoursFournisseurs->id);
        $company = currentCompany();

        $pdf = Pdf::loadView('achats.pdf.supplier-return', compact('return', 'company'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('avoir_' . $return->number . '_' . now()->format('Ymd') . '.pdf');
    }

    public function destroy(SupplierReturn $retoursFournisseurs): RedirectResponse
    {
        try {
            $this->service->delete($retoursFournisseurs);

            return redirect()
                ->route('achats.retours-fournisseurs.index')
                ->with('success', 'Retour fournisseur supprimé.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateReturn(SupplierReturn $retoursFournisseurs): RedirectResponse
    {
        try {
            $this->service->validate($retoursFournisseurs);

            return redirect()
                ->route('achats.retours-fournisseurs.show', $retoursFournisseurs)
                ->with('success', 'Retour fournisseur validé. Le stock a été ajusté.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
