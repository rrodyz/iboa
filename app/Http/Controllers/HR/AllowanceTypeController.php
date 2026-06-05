<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\PayrollAllowanceType;
use Illuminate\Http\Request;

class AllowanceTypeController extends Controller
{
    public function index()
    {
        $types = PayrollAllowanceType::orderBy('is_active', 'desc')
            ->orderBy('name')
            ->withCount('employeeAllowances')
            ->get();

        return view('rh.types-primes.index', compact('types'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:30', 'unique:payroll_allowance_types,code'],
            'is_taxable'        => ['boolean'],
            'is_social_charged' => ['boolean'],
            'description'       => ['nullable', 'string', 'max:500'],
            'is_active'         => ['boolean'],
        ]);

        $data['code']              = strtoupper($data['code']);
        $data['is_taxable']        = $request->boolean('is_taxable');
        $data['is_social_charged'] = $request->boolean('is_social_charged');
        $data['is_active']         = $request->boolean('is_active', true);

        PayrollAllowanceType::create($data);

        return back()->with('success', "Type de prime « {$data['name']} » créé.");
    }

    public function update(Request $request, PayrollAllowanceType $type)
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:30', 'unique:payroll_allowance_types,code,'.$type->id],
            'is_taxable'        => ['boolean'],
            'is_social_charged' => ['boolean'],
            'description'       => ['nullable', 'string', 'max:500'],
            'is_active'         => ['boolean'],
        ]);

        $data['code']              = strtoupper($data['code']);
        $data['is_taxable']        = $request->boolean('is_taxable');
        $data['is_social_charged'] = $request->boolean('is_social_charged');
        $data['is_active']         = $request->boolean('is_active', true);

        $type->update($data);

        return back()->with('success', "Type « {$type->name} » mis à jour.");
    }

    public function destroy(PayrollAllowanceType $type)
    {
        if ($type->employeeAllowances()->exists()) {
            return back()->with('error',
                "Impossible de supprimer « {$type->name} » : des employés ont des primes de ce type. Désactivez-le plutôt."
            );
        }

        $type->delete();
        return back()->with('success', "Type « {$type->name} » supprimé.");
    }
}
