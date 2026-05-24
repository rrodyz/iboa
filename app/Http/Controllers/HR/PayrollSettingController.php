<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * [RH-PRO] Paramétrage de la paie (singleton par entreprise).
 * Taux CNSS, barème IUTS, jours ouvrés, HS, etc.
 */
class PayrollSettingController extends Controller
{
    public function edit()
    {
        $company = Company::firstOrFail();
        $setting = PayrollSetting::forCompany($company->id);

        // Garantir que iuts_brackets est un tableau utilisable dans la vue
        $brackets = $setting->iuts_brackets ?: PayrollSetting::defaultIutsBrackets();

        return view('rh.parametrage.edit', compact('setting', 'brackets', 'company'));
    }

    public function update(Request $request)
    {
        $company = Company::firstOrFail();

        $validated = $request->validate([
            'cnss_employee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cnss_employer_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'cnss_ceiling'       => ['required', 'integer', 'min:0'],
            'cnss_at_rate'       => ['required', 'numeric', 'min:0', 'max:100'],
            'work_days_month'    => ['required', 'integer', 'min:1', 'max:31'],
            'work_hours_day'     => ['required', 'integer', 'min:1', 'max:24'],
            'hs_rate_25'         => ['required', 'numeric', 'min:0', 'max:500'],
            'hs_rate_50'         => ['required', 'numeric', 'min:0', 'max:500'],
            'hs_rate_nuit'       => ['required', 'numeric', 'min:0', 'max:500'],
            'nb_parts_max'       => ['required', 'integer', 'min:1', 'max:20'],
            'parts_per_child'    => ['required', 'numeric', 'min:0', 'max:5'],
            'bulletin_prefix'    => ['required', 'string', 'max:10'],
            'currency_code'      => ['required', 'string', 'max:10'],
            'country_code'       => ['required', 'string', 'max:5'],
            'notes'              => ['nullable', 'string', 'max:2000'],

            // Barème IUTS : tableau d'entrées [plafond, taux]
            'brackets'           => ['required', 'array', 'min:1'],
            'brackets.*.limit'   => ['required', 'numeric', 'min:1'],
            'brackets.*.rate'    => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        // Reconstruire le JSON du barème, trié par plafond croissant
        $brackets = collect($validated['brackets'])
            ->sortBy('limit')
            ->values()
            ->map(fn($b) => [(int) $b['limit'], (float) $b['rate']])
            ->toArray();

        // La dernière tranche doit avoir un plafond très élevé (∞ simulé)
        // On force la dernière à 9_999_999_999 si l'utilisateur a saisi autre chose
        if (count($brackets) > 0) {
            $last = &$brackets[count($brackets) - 1];
            $last[0] = 9_999_999_999;
        }

        $setting = PayrollSetting::firstOrNew(['company_id' => $company->id]);
        $setting->fill(array_merge(
            \Arr::except($validated, ['brackets']),
            ['iuts_brackets' => $brackets, 'updated_by' => Auth::id()]
        ));
        $setting->company_id = $company->id;
        $setting->save();

        // Invalider le cache
        PayrollSetting::clearCache($company->id);

        return redirect()->route('rh.parametrage.edit')
            ->with('success', 'Paramètres de paie mis à jour avec succès.');
    }
}
