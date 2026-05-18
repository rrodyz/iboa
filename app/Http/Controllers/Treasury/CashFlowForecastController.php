<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashFlowForecast;
use App\Models\CashFlowForecastLine;
use App\Services\CashFlowForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashFlowForecastController extends Controller
{
    public function __construct(private CashFlowForecastService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['period_type', 'status', 'year']);

        $forecasts = CashFlowForecast::with('createdBy')
            ->when($filters['period_type'] ?? null, fn ($q, $v) => $q->where('period_type', $v))
            ->when($filters['status']      ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['year']        ?? null, fn ($q, $v) => $q->whereYear('period_start', $v))
            ->orderByDesc('period_start')
            ->paginate(20)->withQueryString();

        return view('tresorerie.previsions.index', compact('forecasts', 'filters'));
    }

    public function create(): View
    {
        $openingBalance = (int) CashAccount::where('is_active', true)->sum('current_balance');
        $categories     = array_merge(
            CashFlowForecastLine::inflowCategories(),
            CashFlowForecastLine::outflowCategories()
        );
        return view('tresorerie.previsions.create', compact('openingBalance', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'           => ['required', 'string', 'max:100'],
            'period_type'     => ['required', 'in:mensuel,trimestriel,annuel'],
            'period_start'    => ['required', 'date'],
            'period_end'      => ['required', 'date', 'after_or_equal:period_start'],
            'opening_balance' => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'lines'           => ['nullable', 'array'],
            'lines.*.category'        => ['required_with:lines', 'string'],
            'lines.*.label'           => ['nullable', 'string', 'max:200'],
            'lines.*.forecast_amount' => ['nullable', 'integer', 'min:0'],
        ], [
            'label.required'             => 'Le libellé est obligatoire.',
            'period_type.required'       => 'Le type de période est obligatoire.',
            'period_type.in'             => 'Le type de période est invalide.',
            'period_start.required'      => 'La date de début est obligatoire.',
            'period_start.date'          => 'La date de début est invalide.',
            'period_end.required'        => 'La date de fin est obligatoire.',
            'period_end.date'            => 'La date de fin est invalide.',
            'period_end.after_or_equal'  => 'La date de fin doit être après ou égale à la date de début.',
            'lines.*.category.required_with' => 'La catégorie est obligatoire pour chaque ligne.',
        ]);

        $forecast = $this->service->create($data);

        return redirect()
            ->route('tresorerie.previsions.show', $forecast)
            ->with('success', 'Prévision ' . $forecast->number . ' créée.');
    }

    public function show(CashFlowForecast $prevision): View
    {
        $prevision->load(['lines', 'createdBy']);
        return view('tresorerie.previsions.show', compact('prevision'));
    }

    public function validateForecast(CashFlowForecast $prevision): RedirectResponse
    {
        try {
            $this->service->validateForecast($prevision);
            return redirect()
                ->route('tresorerie.previsions.show', $prevision)
                ->with('success', 'Prévision validée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function syncActuals(CashFlowForecast $prevision): RedirectResponse
    {
        try {
            $this->service->updateActuals($prevision, []);
            return redirect()
                ->route('tresorerie.previsions.show', $prevision)
                ->with('success', 'Réalisations synchronisées depuis les transactions.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
