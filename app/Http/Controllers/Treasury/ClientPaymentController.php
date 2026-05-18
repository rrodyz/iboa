<?php

namespace App\Http\Controllers\Treasury;

use App\Exports\ClientPaymentsExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Treasury\StoreClientPaymentRequest;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\PaymentMethod;
use App\Repositories\ClientPaymentRepository;
use App\Services\ClientPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class ClientPaymentController extends Controller
{
    public function __construct(
        protected ClientPaymentRepository $repository,
        protected ClientPaymentService    $service,
    ) {}

    /**
     * List all client payments with optional filters.
     */
    public function index(Request $request): View|BinaryFileResponse
    {
        $this->authorize('viewAny', ClientPayment::class);
        $filters = $request->only(['search', 'client_id', 'payment_method_id', 'date_from', 'date_to']);
        // Remove empty values
        $filters = array_filter($filters, fn ($v) => $v !== '' && $v !== null);

        if ($request->boolean('export')) {
            return Excel::download(
                new ClientPaymentsExport($filters),
                'encaissements-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $payments       = $this->repository->search($filters, 15);
        $clients        = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type']);

        return view('tresorerie.encaissements.index', compact('payments', 'filters', 'clients', 'paymentMethods'));
    }

    /**
     * Export encaissements as PDF.
     */
    public function exportPdf(Request $request): mixed
    {
        $this->authorize('viewAny', ClientPayment::class);
        $filters  = array_filter(
            $request->only(['search', 'client_id', 'payment_method_id', 'date_from', 'date_to']),
            fn($v) => $v !== '' && $v !== null
        );
        $company  = \App\Models\Company::firstOrFail();
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;

        $payments = ClientPayment::with(['client', 'paymentMethod', 'cashAccount', 'allocations'])
            ->when(!empty($filters['client_id']),         fn($q) => $q->where('client_id',         $filters['client_id']))
            ->when(!empty($filters['payment_method_id']), fn($q) => $q->where('payment_method_id', $filters['payment_method_id']))
            ->when($dateFrom,                             fn($q) => $q->whereDate('payment_date',  '>=', $dateFrom))
            ->when($dateTo,                               fn($q) => $q->whereDate('payment_date',  '<=', $dateTo))
            ->when(!empty($filters['search']),            fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                   ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ))
            ->orderByDesc('payment_date')
            ->get();

        $totalAmount      = $payments->sum('amount');
        $totalAllocated   = $payments->sum('allocated_amount');
        $totalUnallocated = $payments->sum('unallocated_amount');

        $pdf = Pdf::loadView('tresorerie.encaissements.pdf.index', compact(
            'company', 'payments', 'filters',
            'dateFrom', 'dateTo',
            'totalAmount', 'totalAllocated', 'totalUnallocated'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('encaissements_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Show the payment creation form.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', ClientPayment::class);
        $clients        = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type', 'requires_reference', 'is_mobile_money']);
        $cashAccounts   = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);
        $selectedClient = $request->query('client_id');

        return view('tresorerie.encaissements.create', compact('clients', 'paymentMethods', 'cashAccounts', 'selectedClient'));
    }

    /**
     * Store a new client payment.
     */
    public function store(StoreClientPaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', ClientPayment::class);
        $payment = $this->service->create($request->validated());

        return redirect()
            ->route('tresorerie.encaissements.show', $payment)
            ->with('success', 'Encaissement enregistré avec succès.');
    }

    /**
     * Show a single client payment.
     */
    public function show(ClientPayment $encaissement): View
    {
        $this->authorize('view', $encaissement);
        $payment = $this->repository->findWithDetails($encaissement->id);

        return view('tresorerie.encaissements.show', compact('payment'));
    }

    /**
     * AJAX: return unpaid invoices for a given client_id.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $this->authorize('create', ClientPayment::class);
        $clientId = (int) $request->query('client_id');

        if (!$clientId) {
            return response()->json([]);
        }

        $invoices = $this->service->getClientUnpaidInvoices($clientId);

        return response()->json($invoices->map(fn ($inv) => [
            'id'               => $inv->id,
            'number'           => $inv->number,
            'issued_at'        => $inv->issued_at?->format('d/m/Y'),
            'due_at'           => $inv->due_at?->format('d/m/Y'),
            'total_ttc'        => $inv->total_ttc,
            'remaining_amount' => $inv->remaining_amount,
            'status'           => $inv->status,
        ]));
    }
}
