<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Repositories\CashAccountRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public function __construct(protected CashAccountRepository $repository)
    {
        $this->middleware('can:cash_accounts.view')->only(['index', 'show']);
        $this->middleware('can:cash_accounts.manage')->except(['index', 'show']);
    }

    public function index(): View
    {
        $accounts = CashAccount::with('paymentMethod')
            ->where('is_active', true)
            ->orderBy('type')->orderBy('name')
            ->get();

        return view('tresorerie.caisses.index', compact('accounts'));
    }

    public function create(): View
    {
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
        return view('tresorerie.caisses.create', compact('paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:30', 'unique:cash_accounts,code'],
            'type'              => ['required', 'in:caisse,banque,mobile_money'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency_code'     => ['required', 'string', 'size:3'],
            'opening_balance'   => ['required', 'integer'],
            'is_default'        => ['boolean'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $company = \App\Models\Company::firstOrFail();
        $data['company_id']      = $company->id;
        $data['current_balance'] = (int) $data['opening_balance'];
        $data['is_default']      = $request->boolean('is_default');
        $data['is_active']       = true;

        $account = CashAccount::create($data);

        return redirect()
            ->route('tresorerie.caisses.show', $account)
            ->with('success', 'Compte ' . $account->name . ' créé.');
    }

    public function show(CashAccount $caisse): View
    {
        $account      = $this->repository->findWithTransactions($caisse->id, 20);
        $transactions = $account->getRelation('transactions');

        return view('tresorerie.caisses.show', compact('account', 'transactions'));
    }

    public function edit(CashAccount $caisse): View
    {
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
        return view('tresorerie.caisses.edit', compact('caisse', 'paymentMethods'));
    }

    public function update(Request $request, CashAccount $caisse): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:30', 'unique:cash_accounts,code,' . $caisse->id],
            'type'              => ['required', 'in:caisse,banque,mobile_money'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'currency_code'     => ['required', 'string', 'size:3'],
            'is_default'        => ['boolean'],
            'is_active'         => ['boolean'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active']  = $request->boolean('is_active');

        $caisse->update($data);

        return redirect()
            ->route('tresorerie.caisses.show', $caisse)
            ->with('success', 'Compte mis à jour.');
    }
}
