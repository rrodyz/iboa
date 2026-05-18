<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqQuote;
use App\Models\RfqSupplier;
use App\Models\Supplier;
use App\Services\RfqService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RfqController extends Controller
{
    public function __construct(private RfqService $service)
    {
        $this->middleware('can:purchase_orders.view')->only(['index', 'show', 'compare']);
        $this->middleware('can:purchase_orders.create')->except(['index', 'show', 'compare']);
    }

    public function index(Request $request): View
    {
        $query = Rfq::with(['rfqSuppliers', 'awardedQuote.rfqSupplier.supplier'])
            ->whereNull('deleted_at')
            ->latest();
        if ($status = $request->input('status')) $query->where('status', $status);

        $rfqs = $query->paginate(20)->withQueryString();
        return view('achats.rfq.index', compact('rfqs'));
    }

    public function create(): View
    {
        $suppliers = Supplier::where('is_active', 1)->orderBy('name')->get(['id','name','code']);
        $products  = Product::where('is_active', 1)->orderBy('reference')->get(['id','reference','name']);
        return view('achats.rfq.create', compact('suppliers', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'              => ['required','string','max:255'],
            'deadline'           => ['nullable','date'],
            'notes'              => ['nullable','string'],
            'supplier_ids'       => ['required','array','min:1'],
            'supplier_ids.*'     => ['integer','exists:suppliers,id'],
            'items'              => ['required','array','min:1'],
            'items.*.product_id' => ['nullable','integer','exists:products,id'],
            'items.*.description'=> ['required','string','max:255'],
            'items.*.quantity'   => ['required','numeric','gt:0'],
        ]);

        try {
            $rfq = $this->service->create($data);
            return redirect()->route('achats.rfq.show', $rfq)
                ->with('success', "RFQ {$rfq->number} créée en brouillon.");
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Rfq $rfq): View
    {
        $rfq->load(['items.product', 'rfqSuppliers.supplier', 'quotes.items', 'quotes.rfqSupplier.supplier', 'awardedQuote']);
        return view('achats.rfq.show', compact('rfq'));
    }

    public function compare(Rfq $rfq): View
    {
        $compare = $this->service->compareQuotes($rfq);
        return view('achats.rfq.compare', compact('rfq', 'compare'));
    }

    public function send(Rfq $rfq): RedirectResponse
    {
        try {
            $this->service->markSent($rfq);
            return back()->with('success', "RFQ {$rfq->number} marquée envoyée à tous les fournisseurs.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function recordQuote(Request $request, Rfq $rfq): RedirectResponse
    {
        $data = $request->validate([
            'rfq_supplier_id'        => ['required','integer','exists:rfq_suppliers,id'],
            'supplier_reference'     => ['nullable','string','max:100'],
            'valid_until'            => ['nullable','date'],
            'delivery_days'          => ['nullable','integer','min:0'],
            'notes'                  => ['nullable','string'],
            'items'                  => ['required','array','min:1'],
            'items.*.rfq_item_id'    => ['required','integer','exists:rfq_items,id'],
            'items.*.unit_price'     => ['required','numeric','min:0'],
            'items.*.discount_percent'=> ['nullable','numeric','min:0','max:100'],
            'items.*.tax_rate'       => ['nullable','numeric','min:0','max:100'],
            'items.*.delivery_days'  => ['nullable','integer','min:0'],
        ]);

        try {
            $this->service->recordQuote($rfq, $data);
            return redirect()->route('achats.rfq.show', $rfq)->with('success', 'Cotation enregistrée.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function award(Request $request, Rfq $rfq, RfqQuote $quote): RedirectResponse
    {
        try {
            $po = $this->service->awardToQuote($rfq, $quote);
            return redirect()->route('achats.commandes.show', $po)
                ->with('success', "RFQ attribuée à {$quote->rfqSupplier->supplier->name}. PO {$po->number} créé en brouillon.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Rfq $rfq): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required','string','min:5','max:255']]);
        try {
            $this->service->cancel($rfq, $data['reason']);
            return back()->with('success', "RFQ {$rfq->number} annulée.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Rfq $rfq): RedirectResponse
    {
        if (!$rfq->isDraft()) {
            return back()->with('error', 'Seules les RFQ en brouillon peuvent être supprimées.');
        }
        $rfq->delete();
        return redirect()->route('achats.rfq.index')->with('success', 'RFQ supprimée.');
    }
}
