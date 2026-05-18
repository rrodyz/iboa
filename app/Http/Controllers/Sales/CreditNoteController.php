<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreCreditNoteRequest;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CreditNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    public function __construct(private CreditNoteService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CreditNote::class);
        $filters    = $request->only(['search', 'status', 'client_id']);
        $creditNotes = $this->service->repository->search($filters, 15);

        return view('ventes.avoirs.index', compact('creditNotes', 'filters'));
    }

    /**
     * GET ventes/avoirs/create?invoice_id=X
     * Pre-loads invoice items to prefill form.
     */
    public function create(Request $request)
    {
        $this->authorize('create', CreditNote::class);
        $invoiceId = $request->query('invoice_id');
        $invoice   = $invoiceId ? Invoice::with('items.product', 'client')->findOrFail($invoiceId) : null;

        return view('ventes.avoirs.create', compact('invoice'));
    }

    public function store(StoreCreditNoteRequest $request)
    {
        $this->authorize('create', CreditNote::class);
        $invoice    = Invoice::findOrFail($request->invoice_id);
        $creditNote = $this->service->createFromInvoice($invoice, $request->validated());

        return redirect()
            ->route('ventes.avoirs.show', $creditNote)
            ->with('success', 'Avoir ' . $creditNote->number . ' créé avec succès.');
    }

    public function show(CreditNote $avoir)
    {
        $this->authorize('view', $avoir);
        $creditNote = $this->service->repository->findWithDetails($avoir->id);
        return view('ventes.avoirs.show', compact('creditNote'));
    }

    public function validateNote(CreditNote $avoir)
    {
        $this->authorize('validate', $avoir);
        try {
            $creditNote = $this->service->validate($avoir);
            return redirect()
                ->route('ventes.avoirs.show', $creditNote)
                ->with('success', 'Avoir ' . $creditNote->number . ' validé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function applyToInvoice(CreditNote $avoir)
    {
        $this->authorize('update', $avoir);
        try {
            $this->service->applyToInvoice($avoir);
            return back()->with('success', 'Avoir appliqué à la facture avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(CreditNote $avoir)
    {
        $this->authorize('delete', $avoir);
        try {
            $this->service->delete($avoir);
            return redirect()
                ->route('ventes.avoirs.index')
                ->with('success', 'Avoir supprimé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pdf(CreditNote $avoir, Request $request)
    {
        $this->authorize('view', $avoir);
        $creditNote = $this->service->repository->findWithDetails($avoir->id);
        $settings   = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('ventes.pdf.credit-note', compact('creditNote', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

        $filename = 'Avoir_' . str_replace(['/', '\\', ' '], '-', $creditNote->number) . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }
}
