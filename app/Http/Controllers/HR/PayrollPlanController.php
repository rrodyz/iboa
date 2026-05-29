<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollPlanController extends Controller
{
    public function index(Request $request): View
    {
        $company = currentCompany();
        $plans   = PayrollPlan::where('company_id', $company->id)
            ->withCount('rubrics')
            ->orderByDesc('is_default')
            ->orderBy('libelle')
            ->paginate(15)->withQueryString();

        return view('rh.parametrage.plans.index', compact('plans', 'company'));
    }

    public function create(): View
    {
        $company = currentCompany();
        $plan    = new PayrollPlan();
        return view('rh.parametrage.plans.create', compact('plan', 'company'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = currentCompany();
        $data    = $request->validate([
            'code'        => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
            'libelle'     => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pays'        => ['required', 'string', 'max:100'],
            'country_code'=> ['required', 'string', 'max:5'],
            'devise'      => ['required', 'string', 'max:10'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'   => ['nullable', 'boolean'],
            'is_default'  => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        $data['company_id'] = $company->id;
        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');
        $data['created_by'] = Auth::id();

        if ($data['is_default']) {
            PayrollPlan::where('company_id', $company->id)->update(['is_default' => false]);
        }

        $plan = PayrollPlan::create($data);

        return redirect()->route('rh.plans.show', $plan)
            ->with('success', "Plan « {$plan->libelle} » créé avec succès.");
    }

    public function show(PayrollPlan $plan): View
    {
        $this->authorizeCompany($plan);
        $plan->loadCount('rubrics');
        $rubrics = $plan->rubrics()->orderBy('display_order')->orderBy('code')->get();
        return view('rh.parametrage.plans.show', compact('plan', 'rubrics'));
    }

    public function edit(PayrollPlan $plan): View
    {
        $this->authorizeCompany($plan);
        return view('rh.parametrage.plans.edit', compact('plan'));
    }

    public function update(Request $request, PayrollPlan $plan): RedirectResponse
    {
        $this->authorizeCompany($plan);
        $data = $request->validate([
            'libelle'     => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pays'        => ['required', 'string', 'max:100'],
            'country_code'=> ['required', 'string', 'max:5'],
            'devise'      => ['required', 'string', 'max:10'],
            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'   => ['nullable', 'boolean'],
            'is_default'  => ['nullable', 'boolean'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');
        $data['updated_by'] = Auth::id();

        if ($data['is_default']) {
            PayrollPlan::where('company_id', $plan->company_id)
                ->where('id', '!=', $plan->id)
                ->update(['is_default' => false]);
        }

        $plan->update($data);

        return redirect()->route('rh.plans.show', $plan)
            ->with('success', "Plan « {$plan->libelle} » mis à jour.");
    }

    public function duplicate(Request $request, PayrollPlan $plan): RedirectResponse
    {
        $this->authorizeCompany($plan);
        $request->validate([
            'code'    => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
            'libelle' => ['required', 'string', 'max:150'],
        ]);

        $clone = $plan->duplicate($request->code, $request->libelle);

        return redirect()->route('rh.plans.show', $clone)
            ->with('success', "Plan dupliqué : « {$clone->libelle} ».");
    }

    public function destroy(PayrollPlan $plan): RedirectResponse
    {
        $this->authorizeCompany($plan);

        if ($plan->is_default) {
            return back()->with('error', 'Impossible de supprimer le plan par défaut.');
        }
        if ($plan->rubrics()->count() > 0) {
            return back()->with('error', 'Ce plan contient des rubriques. Supprimez-les d\'abord ou réaffectez-les.');
        }

        $plan->delete();
        return redirect()->route('rh.plans.index')
            ->with('success', 'Plan supprimé.');
    }

    private function authorizeCompany(PayrollPlan $plan): void
    {
        $company = currentCompany();
        abort_if($plan->company_id !== $company->id, 403);
    }
}
