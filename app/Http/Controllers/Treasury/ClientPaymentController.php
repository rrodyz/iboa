<?php

namespace App\Http\Controllers\Treasury;

use App\Exports\ClientPaymentsExport;
use App\Helpers\NumberHelper;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\Treasury\StoreClientPaymentRequest;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\Order;
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

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $totalsQuery = ClientPayment::query()
            ->when(!empty($filters['client_id']),         fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['payment_method_id']), fn($q) => $q->where('payment_method_id', $filters['payment_method_id']))
            ->when(!empty($filters['date_from']),          fn($q) => $q->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),            fn($q) => $q->whereDate('payment_date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),             fn($q) => $q->where(fn($sq) =>
                $sq->where('reference', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_amount'  => (int) $totalsQuery->sum('amount'),
            'count'         => (int) (clone $totalsQuery)->count(),
            'this_month'    => (int) (clone $totalsQuery)->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('amount'),
        ];

        return view('tresorerie.encaissements.index', compact('payments', 'filters', 'clients', 'paymentMethods', 'summary'));
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
        $company  = currentCompany();
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
    public function create(Request $request)
    {
        $this->authorize('create', ClientPayment::class);
        $clients        = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type', 'requires_reference', 'is_mobile_money', 'attachment_required']);
        $cashAccounts   = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);
        $selectedClient = $request->query('client_id');

        // [OVERPAYMENT-GUARD-UI] Vérification PROACTIVE à l'ouverture du formulaire :
        // si un client est pré-sélectionné via l'URL et qu'il a déjà des paiements non
        // imputés couvrant ses dettes, on bloque l'accès au formulaire et on redirige
        // l'utilisateur vers l'imputation des paiements existants.
        if ($selectedClient) {
            $pending = $this->checkPendingUnallocated((int) $selectedClient);
            if ($pending) {
                return redirect()
                    ->route('tresorerie.encaissements.show', $pending['payment_id'])
                    ->with('error', $pending['message']);
            }
        }

        return view('tresorerie.encaissements.create', compact('clients', 'paymentMethods', 'cashAccounts', 'selectedClient'));
    }

    /**
     * [PENDING-PAYMENT-GUARD-UI] Bloque la création d'un nouvel encaissement
     * si le client a déjà UN SEUL paiement non imputé. Force l'utilisateur à
     * traiter d'abord les paiements en attente — élimine le doublon par construction.
     *
     * Retourne ['payment_id'=>X, 'message'=>...] si bloqué, null sinon.
     */
    private function checkPendingUnallocated(int $clientId): ?array
    {
        $pending = ClientPayment::where('client_id', $clientId)
            ->where('unallocated_amount', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'number', 'amount', 'unallocated_amount', 'created_at']);

        if ($pending->isEmpty()) return null;

        $client = Client::find($clientId);
        $totalPending = $pending->sum('unallocated_amount');
        $count        = $pending->count();
        $first        = $pending->first();

        return [
            'payment_id' => $first->id,
            'message'    => sprintf(
                "🔒 Création d'encaissement bloquée pour %s : %d paiement(s) non imputé(s) en attente "
                . "totalisant %s FCFA (le plus ancien : %s). "
                . "Imputez d'abord ce(s) paiement(s) sur ses factures impayées avant d'en saisir un nouveau. "
                . "C'est obligatoire pour éviter les doublons d'encaissement.",
                $client?->name ?? 'ce client',
                $count,
                number_format($totalPending, 0, ',', ' '),
                $first->number
            ),
        ];
    }

    /**
     * Store a new client payment.
     */
    public function store(StoreClientPaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', ClientPayment::class);

        try {
            $payment = $this->service->create($request->validated());
        } catch (\RuntimeException $e) {
            // [GUARD-UX] Anti-doublon, facture verrouillée, etc. : on renvoie au formulaire
            // avec le message lisible au lieu d'une 500 générique.
            return back()->withInput()->with('error', $e->getMessage());
        }

        // [PARITÉ SAGE X3] Pièces jointes de l'encaissement (post-création, additif).
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/client_payment/'.$payment->id, 'local');
            $payment->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }

        // [SAGE X3] « Enregistrer et créer » : rebascule sur un formulaire vierge.
        if ($request->boolean('save_and_new')) {
            return redirect()
                ->route('tresorerie.encaissements.create')
                ->with('success', 'Encaissement '.$payment->number.' enregistré. Nouvelle saisie.');
        }

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
     * Generate and download the payment receipt as PDF.
     */
    public function recu(ClientPayment $encaissement, Request $request): mixed
    {
        $this->authorize('view', $encaissement);
        $payment        = $this->repository->findWithDetails($encaissement->id);
        $company        = currentCompany();
        $amountInWords  = NumberHelper::toWords((int) $payment->amount);

        $pdf = Pdf::loadView('tresorerie.encaissements.pdf.receipt', compact('payment', 'company', 'amountInWords'))
            ->setPaper([0, 0, 419.53, 595.28]); // A5 portrait

        $filename = 'recu_' . $payment->number . '_' . now()->format('Ymd') . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    /**
     * Post-payment allocation: impute unallocated amount on a specific invoice.
     */
    public function imputer(Request $request, ClientPayment $encaissement): RedirectResponse
    {
        $this->authorize('create', ClientPayment::class);

        $data = $request->validate([
            'invoice_id'       => ['required', 'integer', 'exists:invoices,id'],
            'allocated_amount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->service->addAllocation($encaissement, (int) $data['invoice_id'], (int) $data['allocated_amount']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Imputation enregistrée avec succès.');
    }

    public function cancel(Request $request, ClientPayment $encaissement): RedirectResponse
    {
        $this->authorize('update', $encaissement);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Le motif d’annulation est obligatoire.',
            'reason.min'      => 'Le motif doit comporter au moins 5 caractères.',
        ]);

        try {
            $this->service->cancel($encaissement, $data['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tresorerie.encaissements.show', $encaissement)
            ->with('success', 'Encaissement annulé : factures, caisse et comptabilité restaurées.');
    }

    /**
     * AJAX: return unpaid invoices for a given client_id.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $this->authorize('create', ClientPayment::class);
        $clientId = (int) $request->query('client_id');
        $orderId  = $request->integer('order_id') ?: null;

        if (!$clientId) {
            return response()->json([]);
        }

        $invoices = $this->service->getClientUnpaidInvoices($clientId, $orderId);

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

    /**
     * AJAX: return the selectable sales orders for a given client.
     */
    public function getOrders(Request $request): JsonResponse
    {
        $this->authorize('create', ClientPayment::class);
        $clientId = (int) $request->query('client_id');

        if (!$clientId) {
            return response()->json([]);
        }

        $orders = Order::query()
            ->where('client_id', $clientId)
            ->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre', 'livre', 'facture'])
            ->withSum([
                'clientPayments as received_amount' => fn ($query) => $query->where('status', 'confirme'),
            ], 'amount')
            ->latest('issued_at')
            ->latest('id')
            ->get(['id', 'number', 'issued_at', 'total_ttc', 'status']);

        return response()->json($orders
            ->map(fn (Order $order) => [
                'id'               => $order->id,
                'number'           => $order->number,
                'issued_at'        => $order->issued_at?->format('d/m/Y'),
                'total_ttc'        => $order->total_ttc,
                'received_amount'  => (int) ($order->received_amount ?? 0),
                'remaining_amount' => max(0, (int) $order->total_ttc - (int) ($order->received_amount ?? 0)),
                'status'           => $order->status,
            ])
            ->filter(fn (array $order) => $order['remaining_amount'] > 0)
            ->values());
    }
}
