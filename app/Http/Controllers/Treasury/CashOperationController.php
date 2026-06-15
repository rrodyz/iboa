<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashOperation;
use App\Services\CashOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashOperationController extends Controller
{
    public function __construct(private CashOperationService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['cash_account_id', 'direction', 'from', 'to']);

        $operations = CashOperation::with(['cashAccount', 'createdBy'])
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) => $q->where('cash_account_id', $v))
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('operation_date', '>=', $v))
            ->when($filters['to']   ?? null, fn ($q, $v) => $q->whereDate('operation_date', '<=', $v))
            ->orderByDesc('operation_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $coll          = $operations->getCollection()->where('status', 'valide');
        $totalEntrees  = $coll->where('direction', 'entree')->sum('amount');
        $totalSorties  = $coll->where('direction', 'sortie')->sum('amount');

        $cashAccounts = CashAccount::where('is_active', true)->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);

        return view('tresorerie.operations.index', compact('operations', 'cashAccounts', 'filters', 'totalEntrees', 'totalSorties'));
    }

    public function create(Request $request): View
    {
        $direction    = $request->input('direction') === 'sortie' ? 'sortie' : 'entree';
        $cashAccounts = CashAccount::where('type', 'caisse')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('tresorerie.operations.create', compact('cashAccounts', 'direction'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'direction'       => ['required', 'in:entree,sortie'],
            'amount'          => ['required', 'integer', 'min:1'],
            'operation_date'  => ['required', 'date'],
            'category'        => ['nullable', 'string', 'max:100'],
            'label'           => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $operation = $this->service->create($data);
            return redirect()
                ->route('tresorerie.operations.index')
                ->with('success', "Opération {$operation->number} ({$operation->directionLabel()}) enregistrée.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, CashOperation $operation): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => "Le motif d'annulation est obligatoire."]);

        try {
            $this->service->cancel($operation, $data['motif']);
            return back()->with('success', "Opération {$operation->number} annulée — solde restauré.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
