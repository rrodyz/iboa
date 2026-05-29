<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SocialContribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SocialContributionController extends Controller
{
    public function index(): View
    {
        $company       = currentCompany();
        $contributions = SocialContribution::where('company_id', $company->id)
            ->orderBy('organisme')->orderBy('code')
            ->get();

        return view('rh.parametrage.cotisations.index', compact('contributions', 'company'));
    }

    public function create(): View
    {
        $company      = currentCompany();
        $contribution = new SocialContribution();
        return view('rh.parametrage.cotisations.create', compact('contribution', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = currentCompany();
        $data    = $this->validated($request);
        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        $c = SocialContribution::create($data);

        return redirect()->route('rh.cotisations.index')
            ->with('success', "Cotisation « {$c->libelle} » créée.");
    }

    public function edit(SocialContribution $contribution): View
    {
        $this->authorize403($contribution);
        return view('rh.parametrage.cotisations.edit', [
            'contribution' => $contribution,
            'company'      => currentCompany(),
        ]);
    }

    public function update(Request $request, SocialContribution $contribution): RedirectResponse
    {
        $this->authorize403($contribution);
        $data = $this->validated($request);
        $data['updated_by'] = Auth::id();
        $contribution->update($data);

        return redirect()->route('rh.cotisations.index')
            ->with('success', "Cotisation « {$contribution->libelle} » mise à jour.");
    }

    public function destroy(SocialContribution $contribution): RedirectResponse
    {
        $this->authorize403($contribution);
        $libelle = $contribution->libelle;
        $contribution->delete();

        return back()->with('success', "Cotisation « {$libelle} » supprimée.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/'],
            'libelle'          => ['required', 'string', 'max:150'],
            'organisme'        => ['required', 'in:cnss,assurance,retraite,mutuelle,autre'],
            'taux_salarie'     => ['required', 'numeric', 'min:0', 'max:100'],
            'taux_employeur'   => ['required', 'numeric', 'min:0', 'max:100'],
            'base_cotisable'   => ['required', 'in:salaire_brut,salaire_base,plafonne,custom'],
            'plafond'          => ['nullable', 'integer', 'min:0'],
            'base_ref'         => ['nullable', 'string', 'max:50'],
            'account_salarie'  => ['nullable', 'string', 'max:20'],
            'account_employeur'=> ['nullable', 'string', 'max:20'],
            'valid_from'       => ['nullable', 'date'],
            'valid_until'      => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'        => ['nullable', 'boolean'],
            'notes'            => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        return $data;
    }

    private function authorize403(SocialContribution $contribution): void
    {
        abort_if($contribution->company_id !== currentCompany()->id, 403);
    }
}
