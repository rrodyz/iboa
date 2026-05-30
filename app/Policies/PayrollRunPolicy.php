<?php

namespace App\Policies;

use App\Models\PayrollRun;
use App\Models\User;

/**
 * [AUDIT-POL] Policy d'autorisation pour les runs de paie.
 *
 * Permissions utilisées (module RH) :
 *   rh.payroll.view     — consulter les bulletins et runs de paie
 *   rh.payroll.manage   — créer et calculer les runs de paie
 *   rh.payroll.validate — valider (marquer comme payé) un run de paie
 */
class PayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rh.payroll.view');
    }

    public function view(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('rh.payroll.view');
    }

    public function create(User $user): bool
    {
        return $user->can('rh.payroll.manage');
    }

    public function update(User $user, PayrollRun $payrollRun): bool
    {
        // Un run validé/payé ne peut plus être modifié
        return $user->can('rh.payroll.manage')
            && ! in_array($payrollRun->status ?? '', ['valide', 'paye']);
    }

    public function delete(User $user, PayrollRun $payrollRun): bool
    {
        // Seuls les runs en brouillon peuvent être supprimés
        return $user->can('rh.payroll.manage')
            && ($payrollRun->status ?? '') === 'brouillon';
    }

    /**
     * Valider un run de paie (passer en statut « valide » ou « paye »).
     */
    public function validate(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('rh.payroll.validate')
            && ($payrollRun->status ?? '') === 'calcule';
    }

    /**
     * Exporter les bulletins de paie (PDF, CSV).
     */
    public function export(User $user, PayrollRun $payrollRun): bool
    {
        return $user->can('rh.payroll.view');
    }
}
