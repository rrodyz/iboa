<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\BankDeposit;
use App\Models\CashAccount;
use App\Models\CommercialEffect;
use App\Services\BankDepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankDepositController extends Controller
{
    public function __construct(private BankDepositService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['cash_account_id', 'status', 'date_from', 'date_to']);

        $deposits = BankDeposit::with(['cashAccount', 'createdBy'])
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) => $q->where('cash_account_id', $v))
            ->when($filters['status']          ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['date_from']       ?? null, fn ($q, $v) => $q->where('deposit_date', '>=', $v))
            ->when($filters['date_to']         ?? null, fn ($q, $v) => $q->where('deposit_date', '<=', $v))
            ->orderByDesc('deposit_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $bankAccounts = CashAccount::where('type', 'banque')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'code']);

        $stats = [
            'brouillons'   => BankDeposit::where('status', 'brouillon')->count(),
            'valide_count' => BankDeposit::where('status', 'valide')->count(),
            'valide_total' => (int) BankDeposit::where('status', 'valide')->sum('total_amount'),
        ];

        return view('tresorerie.remises.index', compact('deposits', 'filters', 'bankAccounts', 'stats'));
    }

    public function create(): View
    {
        $bankAccounts  = CashAccount::where('type', 'banque')->where('is_active', true)->orderBy('name')->get();
        $caisseAccounts= CashAccount::where('type', 'caisse')->where('is_active', true)->orderBy('name')->get();

        // Undeposited commercial effects (a_recevoir, accepte)
        $availableEffects = CommercialEffect::with('client')
            ->where('direction', 'a_recevoir')
            ->whereIn('status', ['en_attente', 'accepte'])
            ->orderBy('due_date')
            ->get(['id', 'number', 'type', 'amount', 'due_date', 'client_id', 'drawer']);

        return view('tresorerie.remises.create', compact('bankAccounts', 'caisseAccounts', 'availableEffects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id'        => ['required', 'integer', 'exists:cash_accounts,id'],
            'source_cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'deposit_date'           => ['required', 'date'],
            'reference'              => ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.type'                  => ['required', 'in:especes,cheque,effet,virement'],
            'items.*.amount'                => ['required', 'integer', 'min:1'],
            'items.*.reference'             => ['nullable', 'string', 'max:100'],
            'items.*.drawer'                => ['nullable', 'string', 'max:150'],
            'items.*.bank_name'             => ['nullable', 'string', 'max:150'],
            'items.*.due_date'              => ['nullable', 'date'],
            'items.*.commercial_effect_id'  => ['nullable', 'integer', 'exists:commercial_effects,id'],
        ]);

        $deposit = $this->service->create($data);

        return redirect()
            ->route('tresorerie.remises.show', $deposit)
            ->with('success', 'Remise en banque ' . $deposit->number . ' créée.');
    }

    public function show(BankDeposit $remise): View
    {
        $remise->load(['cashAccount', 'sourceCashAccount', 'items.commercialEffect.client', 'createdBy', 'validatedBy']);
        return view('tresorerie.remises.show', compact('remise'));
    }

    public function validateDeposit(BankDeposit $remise): RedirectResponse
    {
        try {
            $this->service->validateDeposit($remise);
            return redirect()
                ->route('tresorerie.remises.show', $remise)
                ->with('success', 'Remise validée. Soldes mis à jour.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
