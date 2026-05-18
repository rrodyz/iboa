<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\VatDeclaration;
use App\Services\VatDeclarationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VatDeclarationController extends Controller
{
    public function __construct(private VatDeclarationService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'period_type', 'year']);

        $query = VatDeclaration::with('createdBy')
            ->when($filters['status']      ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['period_type'] ?? null, fn ($q, $v) => $q->where('period_type', $v))
            ->when($filters['year']        ?? null, fn ($q, $v) => $q->whereYear('period_start', $v))
            ->orderByDesc('period_start')
            ->orderByDesc('id');

        $declarations = $query->paginate(20)->withQueryString();

        return view('comptabilite.tva.index', compact('declarations', 'filters'));
    }

    public function create(Request $request): View
    {
        // Pre-calculate if period is given
        $calc     = null;
        $detail   = null;
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;

        if ($dateFrom && $dateTo) {
            $calc   = $this->service->calculatePeriod($dateFrom, $dateTo);
            $detail = $this->service->getDetail($dateFrom, $dateTo);
        }

        return view('comptabilite.tva.create', compact('calc', 'dateFrom', 'dateTo', 'detail'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_label'     => ['required', 'string', 'max:50'],
            'period_type'      => ['required', 'in:mensuel,trimestriel'],
            'period_start'     => ['required', 'date'],
            'period_end'       => ['required', 'date', 'after_or_equal:period_start'],
            'declaration_date' => ['required', 'date'],
            'due_date'         => ['nullable', 'date'],
            'tva_collectee'    => ['nullable', 'integer', 'min:0'],
            'tva_deductible'   => ['nullable', 'integer', 'min:0'],
            'tva_due'          => ['nullable', 'integer', 'min:0'],
            'credit_tva'       => ['nullable', 'integer', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $decl = $this->service->create($data);

        return redirect()
            ->route('comptabilite.tva.show', $decl)
            ->with('success', 'Déclaration TVA ' . $decl->number . ' créée.');
    }

    public function show(VatDeclaration $tva): View
    {
        $tva->load('createdBy');
        $detail = null;

        if ($tva->period_start && $tva->period_end) {
            $detail = $this->service->getDetail(
                $tva->period_start->toDateString(),
                $tva->period_end->toDateString()
            );
        }

        return view('comptabilite.tva.show', compact('tva', 'detail'));
    }

    public function submit(VatDeclaration $tva): RedirectResponse
    {
        try {
            $this->service->submit($tva);
            return redirect()
                ->route('comptabilite.tva.show', $tva)
                ->with('success', 'Déclaration soumise.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markPaid(Request $request, VatDeclaration $tva): RedirectResponse
    {
        $data = $request->validate(['amount_paid' => ['required', 'integer', 'min:0']]);

        try {
            $this->service->markPaid($tva, $data['amount_paid']);
            return redirect()
                ->route('comptabilite.tva.show', $tva)
                ->with('success', 'Paiement enregistré.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ─── AJAX: calculate from period ─────────────────────────────────────────
    public function calculate(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date'],
        ]);

        $calc   = $this->service->calculatePeriod($request->date_from, $request->date_to);
        $detail = $this->service->getDetail($request->date_from, $request->date_to);

        return response()->json([
            'calc'   => $calc,
            'detail' => [
                'collectee'  => $detail['collectee']->map(fn ($r) => [
                    'code'  => $r->account->code ?? '—',
                    'name'  => $r->account->name ?? '—',
                    'total' => $r->total,
                ]),
                'deductible' => $detail['deductible']->map(fn ($r) => [
                    'code'  => $r->account->code ?? '—',
                    'name'  => $r->account->name ?? '—',
                    'total' => $r->total,
                ]),
            ],
        ]);
    }
}
