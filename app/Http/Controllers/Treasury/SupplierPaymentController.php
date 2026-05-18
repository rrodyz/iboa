<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\StoreSupplierPaymentRequest;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Repositories\SupplierPaymentRepository;
use App\Services\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentRepository $repository,
        protected SupplierPaymentService    $service,
    ) {
        $this->middleware('can:payments.view')->only(['index', 'show']);
        $this->middleware('can:payments.create')->except(['index', 'show']);
    }

    /**
     * List all supplier payments with optional filters.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'supplier_id', 'payment_method_id', 'date_from', 'date_to']);
        $filters = array_filter($filters, fn ($v) => $v !== '' && $v !== null);

        $payments       = $this->repository->search($filters, 15);
        $suppliers      = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type']);

        return view('tresorerie.decaissements.index', compact('payments', 'filters', 'suppliers', 'paymentMethods'));
    }

    /**
     * Show the payment creation form.
     */
    public function create(Request $request): View
    {
        $suppliers      = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type', 'requires_reference', 'is_mobile_money']);
        $cashAccounts   = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);
        $selectedSupplier = $request->query('supplier_id');

        return view('tresorerie.decaissements.create', compact('suppliers', 'paymentMethods', 'cashAccounts', 'selectedSupplier'));
    }

    /**
     * Store a new supplier payment.
     */
    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        $payment = $this->service->create($request->validated());

        return redirect()
            ->route('tresorerie.decaissements.show', $payment)
            ->with('success', 'Décaissement enregistré avec succès.');
    }

    /**
     * Show a single supplier payment.
     */
    public function show(SupplierPayment $decaissement): View
    {
        $payment = $this->repository->findWithDetails($decaissement->id);

        return view('tresorerie.decaissements.show', compact('payment'));
    }

    /**
     * Annule un décaissement (contre-passation comptable + restauration facture).
     * Le motif est obligatoire pour traçabilité comptable.
     */
    public function cancel(Request $request, SupplierPayment $decaissement): RedirectResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);  // permission gérée par groupe route

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Le motif d\'annulation est obligatoire (traçabilité comptable).',
            'reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        try {
            $this->service->cancel($decaissement, $data['reason']);
            return redirect()
                ->route('tresorerie.decaissements.show', $decaissement)
                ->with('success', 'Décaissement ' . $decaissement->number . ' annulé. Contre-passation comptable et restauration de la facture effectuées.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * AJAX: return unpaid invoices for a given supplier_id.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $supplierId = (int) $request->query('supplier_id');

        if (!$supplierId) {
            return response()->json([]);
        }

        $invoices = $this->service->getSupplierUnpaidInvoices($supplierId);

        return response()->json($invoices->map(fn ($inv) => [
            'id'                      => $inv->id,
            'number'                  => $inv->number,
            'supplier_invoice_number' => $inv->supplier_invoice_number,
            'received_at'             => $inv->received_at?->format('d/m/Y'),
            'due_at'                  => $inv->due_at?->format('d/m/Y'),
            'total_ttc'               => $inv->total_ttc,
            'remaining_amount'        => $inv->remaining_amount,
            'status'                  => $inv->status,
        ]));
    }
}
