<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollPlan;
use App\Models\PayrollProfile;
use App\Models\PayRubric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollProfileController extends Controller
{
    private array $categories = [
        'cadre'     => 'Cadre',
        'non_cadre' => 'Non-cadre',
        'dirigeant' => 'Dirigeant',
        'interim'   => 'Intérimaire',
        'stagiaire' => 'Stagiaire',
        'autre'     => 'Autre',
    ];

    public function index(): View
    {
        $company  = Company::firstOrFail();
        $profiles = PayrollProfile::where('company_id', $company->id)
            ->with(['plan', 'contracts'])
            ->withCount(['rubrics', 'contracts'])
            ->orderByDesc('is_default')
            ->orderBy('libelle')
            ->get();

        return view('rh.parametrage.profils.index', [
            'profiles'   => $profiles,
            'company'    => $company,
            'categories' => $this->categories,
        ]);
    }

    public function create(): View
    {
        $company = Company::firstOrFail();
        $profile = new PayrollProfile();
        $plans   = PayrollPlan::where('company_id', $company->id)
                              ->where('is_active', true)
                              ->orderBy('libelle')->get();

        return view('rh.parametrage.profils.create', [
            'profile'    => $profile,
            'company'    => $company,
            'plans'      => $plans,
            'categories' => $this->categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Company::firstOrFail();
        $data    = $this->validated($request);

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        if ($data['is_default'] ?? false) {
            PayrollProfile::where('company_id', $company->id)->update(['is_default' => false]);
        }

        $profile = PayrollProfile::create($data);

        // Si un plan est sélectionné, hériter automatiquement ses rubriques
        if ($profile->plan_id) {
            $added = $profile->inheritFromPlan();
            $msg   = "Profil « {$profile->libelle} » créé — {$added} rubrique(s) héritées du plan.";
        } else {
            $msg = "Profil « {$profile->libelle} » créé.";
        }

        return redirect()->route('rh.profils.show', $profile)->with('success', $msg);
    }

    public function show(PayrollProfile $profil): View
    {
        $this->authorizeCompany($profil);

        $profil->load(['plan', 'rubrics' => fn($q) => $q->orderBy('display_order')->orderBy('code')]);
        $profil->loadCount(['contracts', 'rubrics']);

        // Rubriques du plan qui ne sont pas encore dans le profil
        $planRubricIds   = $profil->rubrics->pluck('id');
        $availableRubrics = $profil->plan_id
            ? PayRubric::where('plan_id', $profil->plan_id)
                       ->where('is_active', true)
                       ->whereNotIn('id', $planRubricIds)
                       ->orderBy('display_order')->orderBy('code')
                       ->get()
            : collect();

        // Regrouper par catégorie pour l'affichage
        $rubricsByCategorie = $profil->rubrics->groupBy('categorie');

        return view('rh.parametrage.profils.show', [
            'profil'            => $profil,
            'rubricsByCategorie' => $rubricsByCategorie,
            'availableRubrics'  => $availableRubrics,
        ]);
    }

    public function edit(PayrollProfile $profil): View
    {
        $this->authorizeCompany($profil);
        $company = Company::firstOrFail();
        $plans   = PayrollPlan::where('company_id', $company->id)
                              ->where('is_active', true)
                              ->orderBy('libelle')->get();

        return view('rh.parametrage.profils.edit', [
            'profile'    => $profil,
            'company'    => $company,
            'plans'      => $plans,
            'categories' => $this->categories,
        ]);
    }

    public function update(Request $request, PayrollProfile $profil): RedirectResponse
    {
        $this->authorizeCompany($profil);
        $data = $this->validated($request);
        $data['updated_by'] = Auth::id();

        if ($data['is_default'] ?? false) {
            PayrollProfile::where('company_id', $profil->company_id)
                          ->where('id', '!=', $profil->id)
                          ->update(['is_default' => false]);
        }

        $profil->update($data);

        return redirect()->route('rh.profils.show', $profil)
                         ->with('success', "Profil « {$profil->libelle} » mis à jour.");
    }

    public function destroy(PayrollProfile $profil): RedirectResponse
    {
        $this->authorizeCompany($profil);

        if ($profil->contracts()->exists()) {
            return back()->with('error', "Ce profil est utilisé par {$profil->contracts_count} contrat(s). Réaffectez-les d'abord.");
        }

        $libelle = $profil->libelle;
        $profil->delete();

        return redirect()->route('rh.profils.index')
                         ->with('success', "Profil « {$libelle} » supprimé.");
    }

    // ── Gestion des rubriques du profil ─────────────────────────────────────

    /** Ajoute une rubrique au profil (depuis "rubriques disponibles") */
    public function addRubric(Request $request, PayrollProfile $profil): RedirectResponse
    {
        $this->authorizeCompany($profil);
        $request->validate(['rubric_id' => ['required', 'exists:pay_rubrics,id']]);

        $profil->rubrics()->syncWithoutDetaching([
            $request->rubric_id => ['is_active' => true],
        ]);

        return back()->with('success', 'Rubrique ajoutée au profil.');
    }

    /** Met à jour la surcharge d'une rubrique dans ce profil */
    public function updateRubric(Request $request, PayrollProfile $profil, PayRubric $rubric): RedirectResponse
    {
        $this->authorizeCompany($profil);

        $data = $request->validate([
            'is_active'             => ['nullable', 'boolean'],
            'override_calc_type'    => ['nullable', 'in:fixe,taux,formule,manuel'],
            'override_fixed_amount' => ['nullable', 'integer', 'min:0'],
            'override_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'override_formula'      => ['nullable', 'string', 'max:500'],
            'notes'                 => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        // Nettoyer les surcharges vides
        foreach (['override_calc_type', 'override_fixed_amount', 'override_rate', 'override_formula'] as $field) {
            if (($data[$field] ?? '') === '') {
                $data[$field] = null;
            }
        }

        $profil->rubrics()->updateExistingPivot($rubric->id, $data);

        return back()->with('success', "Rubrique « {$rubric->code} » mise à jour.");
    }

    /** Retire une rubrique du profil */
    public function removeRubric(PayrollProfile $profil, PayRubric $rubric): RedirectResponse
    {
        $this->authorizeCompany($profil);
        $profil->rubrics()->detach($rubric->id);

        return back()->with('success', "Rubrique « {$rubric->code} » retirée du profil.");
    }

    /** Hérite toutes les rubriques manquantes depuis le plan */
    public function syncFromPlan(PayrollProfile $profil): RedirectResponse
    {
        $this->authorizeCompany($profil);

        if (! $profil->plan_id) {
            return back()->with('error', 'Ce profil n\'est pas associé à un plan.');
        }

        $added = $profil->inheritFromPlan();

        return back()->with('success', "{$added} nouvelle(s) rubrique(s) héritée(s) du plan.");
    }

    // ── Privé ────────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/'],
            'libelle'     => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'plan_id'     => ['nullable', 'exists:payroll_plans,id'],
            'categorie'   => ['required', 'in:cadre,non_cadre,dirigeant,interim,stagiaire,autre'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'   => ['nullable', 'boolean'],
            'is_default'  => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');
        return $data;
    }

    private function authorizeCompany(PayrollProfile $profil): void
    {
        abort_if($profil->company_id !== Company::firstOrFail()->id, 403);
    }
}
