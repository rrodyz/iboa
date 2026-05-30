<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * [AUDIT-POL] Policy d'autorisation pour les fiches employés.
 *
 * Permissions utilisées (module RH) :
 *   rh.employees.view   — consulter les fiches employés
 *   rh.employees.manage — créer, modifier, archiver les employés
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('rh.employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('rh.employees.view');
    }

    public function create(User $user): bool
    {
        return $user->can('rh.employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('rh.employees.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        // Seuls les employés archivés/inactifs peuvent être supprimés
        return $user->can('rh.employees.manage')
            && in_array($employee->status ?? '', ['archive', 'inactif']);
    }

    /**
     * Archiver un employé actif (désactivation douce).
     */
    public function archive(User $user, Employee $employee): bool
    {
        return $user->can('rh.employees.manage');
    }
}
