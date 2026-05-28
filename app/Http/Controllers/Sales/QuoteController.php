<?php

namespace App\Http\Controllers\Sales;

use App\Exports\Sales\QuoteExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreQuoteRequest;
use App\Http\Requests\Sale\UpdateQuoteRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quote;
use App\Services\QuoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QuoteController extends Controller
{
    public function __construct(private QuoteService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Quote::class);

        $filters = $request->only(['client_id', 'status', 'date_from', 'date_to', 'search']);
        $quotes  = $this->service->search($filters, 15);

        // ── Totaux agrégés sur l'ensemble des filtres (pas seulement la page courante) ──
        $company = Company::firstOrFail();
        $totalsQuery = Quote::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('issued_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn($q) => $q->whereDate('issued_at', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'      => (int) $totalsQuery->sum('total_ttc'),
            'total_ht'       => (int) (clone $totalsQuery)->sum('subtotal_ht'),
            'count_accepted' => (int) (clone $totalsQuery)->where('status', 'accepte')->count(),
            'count_pending'  => (int) (clone $totalsQuery)->whereIn('status', ['brouillon', 'envoye'])->count(),
            'count_expired'  => (int) (clone $totalsQuery)->where('status', 'expire')->count(),
            'total_accepted' => (int) (clone $totalsQuery)->where('status', 'accepte')->sum('total_ttc'),
        ];

        return view('ventes.devis.index', compact('quotes', 'filters', 'summary'));
    }

    /**
     * GET ventes/devis/export — export filtered list to Excel.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Quote::class);

        $filters  = $request->only(['client_id', 'status', 'date_from', 'date_to', 'search']);
        $filename = 'devis-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new QuoteExport($filters), $filename);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Quote::class);

        $clients        = Client::active()->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'phone', 'mobile', 'email', 'address', 'city', 'default_discount', 'payment_terms', 'payment_days']);
        $products       = Product::active()->sellable()->with('taxRate:id,rate')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id']);
        $selectedClient = $request->query('client_id');

        return view('ventes.devis.create', compact('clients', 'products', 'selectedClient'));
    }

    public function store(StoreQuoteRequest $request)
    {
        $this->authorize('create', Quote::class);

        $quote = $this->service->create($request->validated());

        return redirect()
            ->route('ventes.devis.show', $quote)
            ->with('success', 'Devis ' . $quote->number . ' créé avec succès.');
    }

    public function show(Quote $devis)
    {
        $this->authorize('view', $devis);

        $quote = $this->service->repository->findWithDetails($devis->id);

        return view('ventes.devis.show', compact('quote'));
    }

    public function edit(Quote $devis)
    {
        $quote    = $this->service->repository->findWithDetails($devis->id);
        $clients  = Client::active()->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'phone', 'mobile', 'email', 'address', 'city', 'default_discount', 'payment_terms', 'payment_days']);
        $products = Product::active()->sellable()->with('taxRate:id,rate')->orderBy('name')->get(['id', 'name', 'reference', 'sale_price', 'tax_rate_id']);

        return view('ventes.devis.edit', compact('quote', 'clients', 'products'));
    }

    public function update(UpdateQuoteRequest $request, Quote $devis)
    {
        $this->service->update($devis, $request->validated());

        return redirect()
            ->route('ventes.devis.show', $devis)
            ->with('success', 'Devis mis à jour.');
    }

    public function destroy(Quote $devis)
    {
        try {
            $this->service->delete($devis);
            return redirect()
                ->route('ventes.devis.index')
                ->with('success', 'Devis supprimé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [VENTES-PRO] Duplique un devis (équivalent Odoo Duplicate).
     */
    public function duplicate(Quote $devis)
    {
        $this->authorize('create', Quote::class);
        $new = $this->service->duplicate($devis);
        return redirect()
            ->route('ventes.devis.edit', $new)
            ->with('success', "Devis dupliqué : {$new->number}. Modifiez puis enregistrez.");
    }

    /** POST ventes/devis/{quote}/send — mark as sent to client. */
    public function send(Quote $devis)
    {
        try {
            $this->service->send($devis);
            return back()->with('success', 'Devis ' . $devis->number . ' marqué comme envoyé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/devis/{quote}/accept
     * Valide le devis ET crée automatiquement la commande en une seule action.
     * L'utilisateur est directement redirigé vers la commande créée.
     */
    public function accept(Quote $devis)
    {
        try {
            $order = $this->service->acceptAndConvert($devis);
            return redirect()
                ->route('ventes.commandes.show', $order)
                ->with('success', 'Devis ' . $devis->number . ' validé — Commande ' . $order->number . ' créée avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/devis/{quote}/refuse — client has refused. */
    public function refuse(Quote $devis)
    {
        try {
            $this->service->refuse($devis);
            return back()->with('success', 'Devis ' . $devis->number . ' marqué comme refusé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/devis/{quote}/cancel — cancel the quote. */
    public function cancel(Quote $devis)
    {
        try {
            $this->service->cancel($devis);
            return back()->with('success', 'Devis ' . $devis->number . ' annulé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/devis/{quote}/convert — convert quote to sales order.
     */
    public function convert(Quote $devis)
    {
        try {
            $order = $this->service->convertToOrder($devis);
            return redirect()
                ->route('ventes.commandes.show', $order)
                ->with('success', 'Devis converti en commande ' . $order->number . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET ventes/devis/{quote}/pdf — download or stream PDF.
     * Add ?preview=1 to open in browser instead of downloading.
     */
    public function pdf(Quote $devis, Request $request)
    {
        $quote    = $this->service->repository->findWithDetails($devis->id);
        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('ventes.pdf.quote', compact('quote', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

        $filename = 'Devis_' . str_replace(['/', '\\', ' '], '-', $quote->number) . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }
}
