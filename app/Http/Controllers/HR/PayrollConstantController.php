<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollConstant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollConstantController extends Controller
{
    private array $groupes = [
        'cnss' => 'CNSS', 'iuts' => 'IUTS / Fiscal',
        'heures' => 'Heures & Jours', 'conges' => 'Congés',
        'smig' => 'SMIG', 'anciennete' => 'Ancienneté',
        'fiscal' => 'Fiscal', 'autre' => 'Autre',
    ];

    private array $valueTypes = [
        'montant' => 'Montant', 'taux' => 'Taux (%)',
        'nombre' => 'Nombre', 'texte' => 'Texte', 'booleen' => 'Booléen',
    ];

    public function index(Request $request): View
    {
        $company = Company::firstOrFail();
        $groupe  = $request->input('groupe');

        $constants = PayrollConstant::where('company_id', $company->id)
            ->when($groupe, fn($q) => $q->where('groupe', $groupe))
            ->orderBy('groupe')->orderBy('code')->orderByDesc('valid_from')
            ->paginate(30)->withQueryString();

        // Regrouper pour l'affichage
        $grouped = PayrollConstant::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('groupe')->orderBy('code')
            ->get()->groupBy('groupe');

        $groupes = $this->groupes;

        return view('rh.parametrage.constantes.index', compact(
            'constants', 'grouped', 'groupes', 'groupe', 'company'
        ));
    }

    public function create(): View
    {
        $company  = Company::firstOrFail();
        $constant = new PayrollConstant();
        return view('rh.parametrage.constantes.create', [
            'constant'   => $constant,
            'company'    => $company,
            'groupes'    => $this->groupes,
            'valueTypes' => $this->valueTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Company::firstOrFail();
        $data    = $this->validated($request);

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        PayrollConstant::create($data);

        // Invalide le cache de cette constante
        PayrollConstant::clearCache($company->id, $data['code']);

        return redirect()->route('rh.constantes.index')
            ->with('success', "Constante « {$data['code']} » créée.");
    }

    public function edit(PayrollConstant $constant): View
    {
        $this->authorizeCompany($constant);
        return view('rh.parametrage.constantes.edit', [
            'constant'   => $constant,
            'company'    => Company::firstOrFail(),
            'groupes'    => $this->groupes,
            'valueTypes' => $this->valueTypes,
        ]);
    }

    public function update(Request $request, PayrollConstant $constant): RedirectResponse
    {
        $this->authorizeCompany($constant);
        $data = $this->validated($request, $constant);
        $data['updated_by'] = Auth::id();

        $constant->update($data);
        PayrollConstant::clearCache($constant->company_id, $constant->code);

        return redirect()->route('rh.constantes.index')
            ->with('success', "Constante « {$constant->code} » mise à jour.");
    }

    public function destroy(PayrollConstant $constant): RedirectResponse
    {
        $this->authorizeCompany($constant);
        $code = $constant->code;
        $constant->delete();
        PayrollConstant::clearCache($constant->company_id, $code);

        return back()->with('success', "Constante « {$code} » supprimée.");
    }

    public function history(string $code): View
    {
        $company = Company::firstOrFail();
        $history = PayrollConstant::where('company_id', $company->id)
            ->where('code', $code)
            ->orderByDesc('valid_from')
            ->orderByDesc('created_at')
            ->with('createdBy')
            ->get();

        return view('rh.parametrage.constantes.history', compact('history', 'code', 'company'));
    }

    private function validated(Request $request, ?PayrollConstant $constant = null): array
    {
        return $request->validate([
            'code'       => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/'],
            'libelle'    => ['required', 'string', 'max:150'],
            'description'=> ['nullable', 'string', 'max:500'],
            'value_type' => ['required', 'in:montant,taux,nombre,texte,booleen'],
            'value_raw'  => ['required', 'string', 'max:500'],
            'unit'       => ['nullable', 'string', 'max:20'],
            'groupe'     => ['required', 'in:cnss,iuts,heures,conges,smig,anciennete,fiscal,autre'],
            'valid_from' => ['nullable', 'date'],
            'valid_until'=> ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'  => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function authorizeCompany(PayrollConstant $constant): void
    {
        abort_if($constant->company_id !== Company::firstOrFail()->id, 403);
    }
}
