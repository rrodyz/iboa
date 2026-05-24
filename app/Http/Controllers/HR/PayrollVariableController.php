<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollVariable;
use Illuminate\Http\Request;

class PayrollVariableController extends Controller
{
    /**
     * Ajoute ou met à jour une variable mensuelle pour un employé sur un run.
     * Appelé en AJAX depuis la page paie/show.
     */
    public function store(Request $request, PayrollRun $run)
    {
        if (!$run->isEditable()) {
            return response()->json(['error' => 'Ce bulletin est validé.'], 422);
        }

        $data = $request->validate([
            'employee_id'       => ['required', 'exists:employees,id'],
            'type'              => ['required', 'string', 'in:' . implode(',', array_keys(PayrollVariable::TYPES))],
            'label'             => ['nullable', 'string', 'max:120'],
            'qty'               => ['required', 'numeric', 'min:0'],
            'unit'              => ['required', 'in:heures,jours,forfait'],
            'amount'            => ['required', 'integer', 'min:0'],
            'is_gain'           => ['required', 'boolean'],
            'is_taxable'        => ['required', 'boolean'],
            'is_social_charged' => ['required', 'boolean'],
            'note'              => ['nullable', 'string'],
        ]);

        // Label par défaut depuis le type
        if (empty($data['label'])) {
            $data['label'] = PayrollVariable::TYPES[$data['type']]['label'] ?? $data['type'];
        }

        $variable = PayrollVariable::create(array_merge($data, [
            'payroll_run_id' => $run->id,
        ]));

        return response()->json([
            'variable' => $variable->load('employee'),
            'message'  => 'Variable ajoutée.',
        ]);
    }

    public function destroy(PayrollRun $run, PayrollVariable $variable)
    {
        if (!$run->isEditable()) {
            return response()->json(['error' => 'Ce bulletin est validé.'], 422);
        }
        $variable->delete();
        return response()->json(['message' => 'Variable supprimée.']);
    }
}
