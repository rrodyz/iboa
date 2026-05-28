<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollBulletinTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollBulletinTemplateController extends Controller
{
    public function index(): View
    {
        $company   = Company::firstOrFail();
        $templates = PayrollBulletinTemplate::where('company_id', $company->id)
            ->withCount('items')
            ->orderByDesc('is_default')
            ->orderBy('libelle')
            ->get();

        return view('rh.parametrage.modeles-bulletins.index', [
            'templates' => $templates,
            'company'   => $company,
        ]);
    }

    public function create(): View
    {
        $company  = Company::firstOrFail();
        $template = new PayrollBulletinTemplate([
            'show_logo'            => true,
            'show_company_address' => true,
            'show_employee_photo'  => false,
            'show_net_a_payer_box' => true,
            'show_cumuls'          => true,
            'show_conges_solde'    => true,
            'show_cout_employeur'  => false,
            'paper_size'           => 'A4',
            'orientation'          => 'portrait',
            'primary_color'        => 'indigo',
        ]);

        return view('rh.parametrage.modeles-bulletins.create', [
            'template' => $template,
            'company'  => $company,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Company::firstOrFail();
        $data    = $this->validated($request);

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        if ($data['is_default'] ?? false) {
            PayrollBulletinTemplate::where('company_id', $company->id)->update(['is_default' => false]);
        }

        $template = PayrollBulletinTemplate::create($data);

        return redirect()->route('rh.modeles-bulletins.index')
                         ->with('success', "Modèle « {$template->libelle} » créé.");
    }

    public function edit(PayrollBulletinTemplate $modele): View
    {
        $this->authorizeCompany($modele);
        $modele->loadCount('items');

        return view('rh.parametrage.modeles-bulletins.edit', [
            'template' => $modele,
            'company'  => Company::firstOrFail(),
        ]);
    }

    public function update(Request $request, PayrollBulletinTemplate $modele): RedirectResponse
    {
        $this->authorizeCompany($modele);
        $data = $this->validated($request);
        $data['updated_by'] = Auth::id();

        if ($data['is_default'] ?? false) {
            PayrollBulletinTemplate::where('company_id', $modele->company_id)
                                   ->where('id', '!=', $modele->id)
                                   ->update(['is_default' => false]);
        }

        $modele->update($data);

        return redirect()->route('rh.modeles-bulletins.index')
                         ->with('success', "Modèle « {$modele->libelle} » mis à jour.");
    }

    public function destroy(PayrollBulletinTemplate $modele): RedirectResponse
    {
        $this->authorizeCompany($modele);

        if ($modele->is_default) {
            return back()->with('error', 'Impossible de supprimer le modèle par défaut.');
        }

        if ($modele->items()->exists()) {
            $count = $modele->items()->count();
            return back()->with('error', "Impossible de supprimer : {$count} bulletin(s) utilisent ce modèle.");
        }

        $libelle = $modele->libelle;
        $modele->delete();

        return redirect()->route('rh.modeles-bulletins.index')
                         ->with('success', "Modèle « {$libelle} » supprimé.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code'                 => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/'],
            'libelle'              => ['required', 'string', 'max:150'],
            'description'          => ['nullable', 'string', 'max:500'],
            'header_text'          => ['nullable', 'string', 'max:1000'],
            'footer_text'          => ['nullable', 'string', 'max:1000'],
            'show_logo'            => ['nullable', 'boolean'],
            'show_company_address' => ['nullable', 'boolean'],
            'show_employee_photo'  => ['nullable', 'boolean'],
            'show_net_a_payer_box' => ['nullable', 'boolean'],
            'show_cumuls'          => ['nullable', 'boolean'],
            'show_conges_solde'    => ['nullable', 'boolean'],
            'show_cout_employeur'  => ['nullable', 'boolean'],
            'paper_size'           => ['required', 'in:A4,letter'],
            'orientation'          => ['required', 'in:portrait,landscape'],
            'primary_color'        => ['required', 'in:indigo,blue,gray,green,red,orange,teal'],
            'is_default'           => ['nullable', 'boolean'],
            'is_active'            => ['nullable', 'boolean'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ]);

        $data['show_logo']            = $request->boolean('show_logo');
        $data['show_company_address'] = $request->boolean('show_company_address');
        $data['show_employee_photo']  = $request->boolean('show_employee_photo');
        $data['show_net_a_payer_box'] = $request->boolean('show_net_a_payer_box');
        $data['show_cumuls']          = $request->boolean('show_cumuls');
        $data['show_conges_solde']    = $request->boolean('show_conges_solde');
        $data['show_cout_employeur']  = $request->boolean('show_cout_employeur');
        $data['is_default']           = $request->boolean('is_default');
        $data['is_active']            = $request->boolean('is_active', true);

        return $data;
    }

    private function authorizeCompany(PayrollBulletinTemplate $template): void
    {
        abort_if($template->company_id !== Company::firstOrFail()->id, 403);
    }
}
