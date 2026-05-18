<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreSupplierInvoiceRequest;
use App\Http\Requests\Purchase\UpdateSupplierInvoiceRequest;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    public function __construct(private SupplierInvoiceService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SupplierInvoice::class);
        $filters  = $request->only(['supplier_id', 'status', 'overdue', 'search']);
        $invoices = $this->service->search($filters, 15);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);

        return view('achats.factures-fournisseurs.index', compact('invoices', 'filters', 'suppliers'));
    }

    public function create()
    {
        $this->authorize('create', SupplierInvoice::class);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.factures-fournisseurs.create', compact('suppliers', 'products'));
    }

    public function store(StoreSupplierInvoiceRequest $request)
    {
        $this->authorize('create', SupplierInvoice::class);
        $invoice = $this->service->create($request->validated());

        return redirect()
            ->route('achats.factures-fournisseurs.show', $invoice)
            ->with('success', 'Facture fournisseur ' . $invoice->number . ' créée avec succès.');
    }

    public function show(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('view', $facturesFournisseur);
        $invoice        = $this->service->repository->findWithDetails($facturesFournisseur->id);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $cashAccounts   = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('achats.factures-fournisseurs.show', compact('invoice', 'paymentMethods', 'cashAccounts'));
    }

    public function edit(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('update', $facturesFournisseur);
        $invoice   = $this->service->repository->findWithDetails($facturesFournisseur->id);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.factures-fournisseurs.edit', compact('invoice', 'suppliers', 'products'));
    }

    public function update(UpdateSupplierInvoiceRequest $request, SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('update', $facturesFournisseur);
        $this->service->update($facturesFournisseur, $request->validated());

        return redirect()
            ->route('achats.factures-fournisseurs.show', $facturesFournisseur)
            ->with('success', 'Facture fournisseur mise à jour.');
    }

    public function destroy(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('delete', $facturesFournisseur);
        try {
            $this->service->delete($facturesFournisseur);
            return redirect()
                ->route('achats.factures-fournisseurs.index')
                ->with('success', 'Facture fournisseur supprimée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST achats/factures-fournisseurs/{facturesFournisseur}/payment
     */
    public function recordPayment(Request $request, SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('update', $facturesFournisseur);
        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'payment_date'      => 'required|date',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id'   => 'nullable|exists:cash_accounts,id',
            'reference'         => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
        ]);

        try {
            $this->service->recordPayment($facturesFournisseur, $request->only([
                'amount', 'payment_date', 'payment_method_id', 'cash_account_id', 'reference', 'notes',
            ]));

            return redirect()
                ->route('achats.factures-fournisseurs.show', $facturesFournisseur)
                ->with('success', 'Paiement enregistré avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST achats/factures-fournisseurs/{supplierInvoice}/validate
     */
    public function validateInvoice(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('validate', $facturesFournisseur);
        try {
            $this->service->validate($facturesFournisseur);
            return redirect()
                ->route('achats.factures-fournisseurs.show', $facturesFournisseur)
                ->with('success', 'Facture fournisseur validée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
