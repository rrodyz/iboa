<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\TreasuryBudget;
use App\Services\TreasuryBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TreasuryBudgetController extends Controller
{
    public function __construct(private TreasuryBudgetService $service) {}

    public function index(): View
    {
        $budgets = TreasuryBudget::with('createdBy')
            ->withSum('lines as total_planned', 'planned_amount')
            ->orderByDesc('year')->orderBy('name')
            ->paginate(20);

        return view('tresorerie.budgets.index', compact('budgets'));
    }

    public function create(): View
    {
        return view('tresorerie.budgets.create', ['year' => now()->year]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'year'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['nullable', 'array'],
            'lines.*.category'  => ['nullable', 'string', 'max:100'],
            'lines.*.direction' => ['nullable', 'in:entree,sortie'],
            'lines.*.months'    => ['nullable', 'array'],
        ]);

        $budget = $this->service->create($data, $data['lines'] ?? []);
        return redirect()->route('tresorerie.budgets.show', $budget)
            ->with('success', "Budget « {$budget->name} » créé.");
    }

    public function show(TreasuryBudget $budget): View
    {
        $budget->load('lines', 'createdBy');
        $comparison = $this->service->comparison($budget);
        $byCategory = $budget->lines->groupBy('category');

        return view('tresorerie.budgets.show', compact('budget', 'comparison', 'byCategory'));
    }
}
