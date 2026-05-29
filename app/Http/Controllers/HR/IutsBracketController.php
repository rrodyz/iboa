<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\IutsBracket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IutsBracketController extends Controller
{
    public function index(): View
    {
        $company  = currentCompany();
        $brackets = IutsBracket::where('company_id', $company->id)
            ->orderBy('impot')->orderBy('ordre')->orderBy('tranche_min')
            ->get()
            ->groupBy('impot');

        return view('rh.parametrage.baremes.index', compact('brackets', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = currentCompany();
        $data    = $this->validated($request);

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        IutsBracket::create($data);
        IutsBracket::clearCache($company->id);

        return back()->with('success', 'Tranche ajoutée.');
    }

    public function update(Request $request, IutsBracket $bracket): RedirectResponse
    {
        abort_if($bracket->company_id !== currentCompany()->id, 403);
        $bracket->update($this->validated($request));
        IutsBracket::clearCache($bracket->company_id);

        return back()->with('success', 'Tranche mise à jour.');
    }

    public function destroy(IutsBracket $bracket): RedirectResponse
    {
        abort_if($bracket->company_id !== currentCompany()->id, 403);
        $cid = $bracket->company_id;
        $bracket->delete();
        IutsBracket::clearCache($cid);

        return back()->with('success', 'Tranche supprimée.');
    }

    /**
     * Simulation IUTS : retourne l'impôt calculé pour un salaire et des parts.
     */
    public function simulate(Request $request): JsonResponse
    {
        $request->validate([
            'salaire_imposable' => ['required', 'integer', 'min:0'],
            'nb_parts'          => ['required', 'numeric', 'min:1', 'max:10'],
        ]);

        $company = currentCompany();
        $iuts    = IutsBracket::computeIuts(
            $company->id,
            (int) $request->salaire_imposable,
            (float) $request->nb_parts,
        );

        return response()->json([
            'iuts'             => $iuts,
            'iuts_formatted'   => number_format($iuts, 0, ',', ' ') . ' FCFA',
            'taux_moyen'       => $request->salaire_imposable > 0
                ? round($iuts / $request->salaire_imposable * 100, 2) . ' %'
                : '0 %',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'pays'         => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'max:5'],
            'impot'        => ['required', 'in:iuts,its,autre'],
            'tranche_min'  => ['required', 'integer', 'min:0'],
            'tranche_max'  => ['required', 'integer', 'gt:tranche_min'],
            'taux'         => ['required', 'numeric', 'min:0', 'max:100'],
            'montant_fixe' => ['nullable', 'integer', 'min:0'],
            'abattement'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ordre'        => ['required', 'integer', 'min:1'],
            'valid_from'   => ['nullable', 'date'],
            'valid_until'  => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'    => ['nullable', 'boolean'],
        ]) + [
            'montant_fixe' => 0,
            'abattement'   => 0,
            'is_active'    => $request->boolean('is_active', true),
        ];
    }
}
