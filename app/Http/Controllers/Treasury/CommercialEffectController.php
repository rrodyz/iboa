<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommercialEffect;
use App\Models\Supplier;
use App\Services\CommercialEffectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommercialEffectController extends Controller
{
    public function __construct(private CommercialEffectService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'direction', 'status', 'date_from', 'date_to', 'search']);

        $effects = CommercialEffect::with(['client', 'supplier', 'createdBy'])
            ->when($filters['type']      ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['status']    ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('due_date', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('due_date', '<=', $v))
            ->when($filters['search']    ?? null, fn ($q, $v) => $q->where(function ($sq) use ($v) {
                $sq->where('number', 'like', "%$v%")
                   ->orWhere('reference', 'like', "%$v%")
                   ->orWhere('drawer', 'like', "%$v%");
            }))
            ->orderByDesc('due_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        // Upcoming due (next 7 days)
        $upcomingDue = $this->service->getUpcomingDue(7);

        // KPI : portefeuille d'effets en cours (non dénoués)
        $outstanding = ['en_attente', 'accepte', 'remis_banque'];
        $stats = [
            'a_recevoir' => (int) CommercialEffect::where('direction', 'a_recevoir')->whereIn('status', $outstanding)->sum('amount'),
            'a_payer'    => (int) CommercialEffect::where('direction', 'a_payer')->whereIn('status', $outstanding)->sum('amount'),
            'en_attente' => CommercialEffect::where('status', 'en_attente')->count(),
            'echus'      => CommercialEffect::whereIn('status', $outstanding)->whereDate('due_date', '<', now())->count(),
        ];

        return view('tresorerie.effets.index', compact('effects', 'filters', 'upcomingDue', 'stats'));
    }

    public function create(): View
    {
        $clients   = Client::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('tresorerie.effets.create', compact('clients', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'                => ['required', 'in:cheque,lcr,billet_ordre,traite'],
            'direction'           => ['required', 'in:a_recevoir,a_payer'],
            'client_id'           => ['nullable', 'integer', 'exists:clients,id'],
            'supplier_id'         => ['nullable', 'integer', 'exists:suppliers,id'],
            'invoice_id'          => ['nullable', 'integer', 'exists:invoices,id'],
            'supplier_invoice_id' => ['nullable', 'integer', 'exists:supplier_invoices,id'],
            'amount'              => ['required', 'integer', 'min:1'],
            'currency_code'       => ['required', 'string', 'size:3'],
            'issue_date'          => ['required', 'date'],
            'due_date'            => ['nullable', 'date', 'after_or_equal:issue_date'],
            'drawer'              => ['nullable', 'string', 'max:150'],
            'drawee'              => ['nullable', 'string', 'max:150'],
            'payee'               => ['nullable', 'string', 'max:150'],
            'bank_name'           => ['nullable', 'string', 'max:150'],
            'bank_account'        => ['nullable', 'string', 'max:100'],
            'reference'           => ['nullable', 'string', 'max:100'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ]);

        $effect = $this->service->create($data);

        return redirect()
            ->route('tresorerie.effets.show', $effect)
            ->with('success', 'Effet ' . $effect->number . ' créé.');
    }

    public function show(CommercialEffect $effet): View
    {
        $effet->load(['client', 'supplier', 'invoice', 'supplierInvoice', 'bankDeposit', 'cashAccount', 'createdBy']);
        return view('tresorerie.effets.show', compact('effet'));
    }

    // ─── Status transitions ───────────────────────────────────────────────
    public function accept(CommercialEffect $effet): RedirectResponse
    {
        try {
            $this->service->accept($effet);
            return redirect()->route('tresorerie.effets.show', $effet)->with('success', 'Effet accepté.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markEncaisse(Request $request, CommercialEffect $effet): RedirectResponse
    {
        $data = $request->validate(['payment_date' => ['required', 'date']]);
        try {
            $this->service->markEncaisse($effet, $data['payment_date']);
            return redirect()->route('tresorerie.effets.show', $effet)->with('success', 'Effet encaissé.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, CommercialEffect $effet): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        try {
            $this->service->reject($effet, $data['rejection_reason']);
            return redirect()->route('tresorerie.effets.show', $effet)->with('success', 'Effet rejeté.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function protest(Request $request, CommercialEffect $effet): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        try {
            $this->service->protest($effet, $data['rejection_reason']);
            return redirect()->route('tresorerie.effets.show', $effet)->with('success', 'Effet protesté.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(CommercialEffect $effet): RedirectResponse
    {
        try {
            $this->service->cancel($effet);
            return redirect()->route('tresorerie.effets.show', $effet)->with('success', 'Effet annulé.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
