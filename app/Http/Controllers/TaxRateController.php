<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaxRateController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index(): View
    {
        $taxRates = TaxRate::with(['collectedAccount:id,code,name', 'deductibleAccount:id,code,name'])
            ->orderByDesc('is_default')->orderBy('rate')->get();

        // Comptes 44 (TVA) actifs/postables uniquement
        $tvaAccounts = Account::active()->postable()
            ->where('code', 'like', '44%')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('settings.tax-rates', compact('taxRates', 'tvaAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:50'],
            'short_name'            => ['required', 'string', 'max:10'],
            'rate'                  => ['required', 'numeric', 'min:0', 'max:100'],
            'type'                  => ['required', 'in:tva,retenue'],
            'collected_account_id'  => ['nullable', 'exists:accounts,id'],
            'deductible_account_id' => ['nullable', 'exists:accounts,id'],
            'is_default'            => ['boolean'],
            'is_active'             => ['boolean'],
        ]);

        DB::transaction(function () use ($data, $request) {
            if ($request->boolean('is_default')) {
                TaxRate::where('is_default', true)->update(['is_default' => false]);
            }
            TaxRate::create([
                ...$data,
                'is_default' => $request->boolean('is_default'),
                'is_active'  => $request->boolean('is_active', true),
            ]);
        });

        return back()->with('success', "Taux « {$data['name']} » créé.");
    }

    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:50'],
            'short_name'            => ['required', 'string', 'max:10'],
            'rate'                  => ['required', 'numeric', 'min:0', 'max:100'],
            'type'                  => ['required', 'in:tva,retenue'],
            'collected_account_id'  => ['nullable', 'exists:accounts,id'],
            'deductible_account_id' => ['nullable', 'exists:accounts,id'],
            'is_active'             => ['boolean'],
        ]);

        $taxRate->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Taux « {$taxRate->name} » mis à jour.");
    }

    public function setDefault(TaxRate $taxRate): RedirectResponse
    {
        if (!$taxRate->is_active) {
            return back()->with('error', 'Impossible de définir un taux inactif comme taux par défaut.');
        }

        DB::transaction(function () use ($taxRate) {
            TaxRate::where('is_default', true)->update(['is_default' => false]);
            $taxRate->update(['is_default' => true]);
        });

        return back()->with('success', "« {$taxRate->name} » défini comme taux par défaut.");
    }

    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        if ($taxRate->is_default) {
            return back()->with('error', 'Impossible de supprimer le taux par défaut.');
        }

        // Check if used by products
        if ($taxRate->products()->exists()) {
            return back()->with('error', 'Ce taux est utilisé par des articles et ne peut pas être supprimé.');
        }

        $taxRate->delete();
        return back()->with('success', "Taux supprimé.");
    }
}
