<?php

namespace App\Http\Controllers\Sales;

use App\Exports\InvoicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreInvoiceRequest;
use App\Http\Requests\Sale\UpdateInvoiceRequest;
use App\Http\Traits\ManagesEditLock;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceController extends Controller
{
    use ManagesEditLock;

    public function __construct(private InvoiceService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filters  = $request->only(['client_id', 'status', 'type', 'overdue', 'search', 'date_from', 'date_to']);
        $invoices = $this->service->search($filters, 15);

        if ($request->boolean('export')) {
            return Excel::download(
                new InvoicesExport($filters),
                'factures-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);

        return view('ventes.factures.index', compact('invoices', 'filters', 'clients'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $clients = Client::active()
            ->with(['taxRates' => fn($q) => $q->where('type', 'retenue')])
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name']);
        $products       = Product::active()->sellable()->with('taxRate:id,rate')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id']);
        $selectedClient = $request->query('client_id');

        // Map { clientId: [{name, short_name, rate}, ...] } pour le calcul JS des retenues
        $clientWithholding = $clients->mapWithKeys(fn($c) => [
            $c->id => $c->taxRates->map(fn($t) => [
                'name'       => $t->name,
                'short_name' => $t->short_name,
                'rate'       => (float) $t->rate,
            ])->values(),
        ]);

        return view('ventes.factures.create', compact('clients', 'products', 'selectedClient', 'clientWithholding'));
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->service->create($request->validated());

        return redirect()
            ->route('ventes.factures.show', $invoice)
            ->with('success', 'Facture ' . $invoice->number . ' créée avec succès.');
    }

    public function show(Invoice $facture)
    {
        $this->authorize('view', $facture);

        $invoice = $this->service->repository->findWithDetails($facture->id);

        // [UX-4] Audit log de cette facture — 20 dernières opérations
        $audits = \App\Models\AuditLog::where('model_type', \App\Models\Invoice::class)
            ->where('model_id', $invoice->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('ventes.factures.show', compact('invoice', 'audits'));
    }

    public function edit(Invoice $facture)
    {
        $this->authorize('update', $facture);

        if (!in_array($facture->status, ['brouillon'])) {
            return back()->with('error', 'Seules les factures en brouillon peuvent être modifiées.');
        }

        // [CONCURRENCE] Acquisition du verrou d'édition
        $lock = $this->acquireLockOr($facture, 'ventes.factures.show', $facture);
        if ($lock instanceof \Illuminate\Http\RedirectResponse) return $lock;

        $invoice  = $this->service->repository->findWithDetails($facture->id);
        $clients  = Client::active()
            ->with(['taxRates' => fn($q) => $q->where('type', 'retenue')])
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name']);
        $products = Product::active()->sellable()->with('taxRate:id,rate')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id']);

        $clientWithholding = $clients->mapWithKeys(fn($c) => [
            $c->id => $c->taxRates->map(fn($t) => [
                'name'       => $t->name,
                'short_name' => $t->short_name,
                'rate'       => (float) $t->rate,
            ])->values(),
        ]);

        $editLock = $lock; // déjà le verrou actif pour ce user
        return view('ventes.factures.edit', compact('invoice', 'clients', 'products', 'clientWithholding', 'editLock'));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $facture)
    {
        $this->authorize('update', $facture);

        try {
            $this->service->update($facture, $request->validated());
            $this->releaseLock($facture); // [CONCURRENCE] Libère le verrou après sauvegarde
            return redirect()
                ->route('ventes.factures.show', $facture)
                ->with('success', 'Facture mise à jour.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Invoice $facture)
    {
        $this->authorize('delete', $facture);

        try {
            $this->service->delete($facture);
            return redirect()
                ->route('ventes.factures.index')
                ->with('success', 'Facture supprimée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/factures/{invoice}/validate — validate the invoice.
     */
    public function validateInvoice(Invoice $facture)
    {
        $this->authorize('validate', $facture);

        try {
            $invoice = $this->service->validate($facture);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' validée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/factures/{invoice}/cancel — annulation avec contre-passation comptable.
     * Seulement possible pour factures émises sans paiement encaissé.
     */
    /**
     * POST ventes/factures/{invoice}/convert-proforma — convertit une proforma en facture standard.
     * Crée une nouvelle facture standard avec déclenchement compta + stock.
     * La proforma originelle passe à 'annulee' avec lien parent_invoice_id.
     */
    public function convertProforma(Invoice $facture)
    {
        $this->authorize('validate', $facture);

        try {
            $newInvoice = $this->service->convertProforma($facture);
            return redirect()
                ->route('ventes.factures.show', $newInvoice)
                ->with('success', 'Proforma ' . $facture->number . ' convertie en facture ' . $newInvoice->number . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInvoice(Invoice $facture, Request $request)
    {
        $this->authorize('delete', $facture);  // l'annulation est aussi sensible que la suppression

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Le motif de l\'annulation est obligatoire pour l\'audit comptable.',
            'reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        try {
            $invoice = $this->service->cancel($facture, $data['reason']);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' annulée (contre-passation comptable effectuée).');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET ventes/factures/export-pdf — export the filtered invoice list as PDF.
     */
    public function exportPdf(Request $request): mixed
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = array_filter(
            $request->only(['client_id', 'status', 'type', 'overdue', 'search', 'date_from', 'date_to']),
            fn($v) => $v !== '' && $v !== null
        );

        $company = \App\Models\Company::first();

        $invoices = Invoice::with(['client'])
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status',    $filters['status']))
            ->when(!empty($filters['overdue']),   fn($q) => $q->where('due_at', '<', now())
                ->whereNotIn('status', ['payee', 'annulee']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                   ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('issued_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn($q) => $q->whereDate('issued_at', '<=', $filters['date_to']))
            ->orderByDesc('issued_at')
            ->orderByDesc('number')
            ->get();

        $totalHt        = $invoices->sum('subtotal_ht');
        $totalTtc       = $invoices->sum('total_ttc');
        $totalTva       = $invoices->sum('total_tax');
        $totalRemaining = $invoices->sum('remaining_amount');
        $countOverdue   = $invoices->filter(fn($i) =>
            $i->due_at && $i->due_at->isPast() && !in_array($i->status, ['payee', 'annulee'])
        )->count();

        $statusLabels = [
            'brouillon'           => 'Brouillon',
            'emise'               => 'Émise',
            'envoyee'             => 'Envoyée',
            'partiellement_payee' => 'Part. payée',
            'payee'               => 'Payée',
            'en_retard'           => 'En retard',
            'annulee'             => 'Annulée',
        ];

        $pdf = Pdf::loadView('ventes.factures.pdf.index', compact(
            'company', 'invoices', 'filters',
            'totalHt', 'totalTtc', 'totalTva', 'totalRemaining', 'countOverdue',
            'statusLabels'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('factures_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * GET ventes/factures/{invoice}/pdf — download or stream PDF.
     * Add ?preview=1 to open in browser instead of downloading.
     */
    public function pdf(Invoice $facture, Request $request)
    {
        $this->authorize('view', $facture);

        $invoice  = $this->service->repository->findWithDetails($facture->id);
        $viewPath = $this->service->generatePdfPath($invoice);
        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView($viewPath, compact('invoice', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

        $filename = 'Facture_' . str_replace(['/', '\\', ' '], '-', $invoice->number) . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    /**
     * POST ventes/factures/{invoice}/send-email — send invoice by email.
     */
    public function sendEmail(Invoice $facture)
    {
        $this->authorize('send', $facture);

        $invoice = $this->service->repository->findWithDetails($facture->id);
        $email   = $invoice->client?->email;

        if (!$email) {
            return back()->with('error', 'Ce client n\'a pas d\'adresse email renseignée.');
        }

        // [PERF-C1] Dispatch asynchronously — the job handles send + status update + logging.
        SendInvoiceEmailJob::dispatch(
            $invoice,
            $email,
            $invoice->client?->displayName() ?? $email,
        );

        return back()->with('success', 'Email programmé pour ' . $email . '. Il sera envoyé sous quelques instants.');
    }
}
