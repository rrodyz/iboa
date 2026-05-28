<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * [RH-PRO] Permissions & Rôles du module Ressources Humaines / Paie.
 *
 * Idempotent : peut être relancé sans doublon (firstOrCreate).
 *
 * ─── Permissions ──────────────────────────────────────────────────────────────
 *   rh.view              Accéder au module RH (dashboard, listes)
 *   rh.employees.view    Consulter les fiches employés
 *   rh.employees.manage  Créer / modifier / archiver les employés + contrats + primes
 *   rh.payroll.view      Voir les bulletins de paie
 *   rh.payroll.manage    Créer, calculer, exporter les bulletins
 *   rh.payroll.validate  Valider et marquer comme payé
 *   rh.leaves.view       Consulter les congés et soldes
 *   rh.leaves.manage     Approuver / refuser les demandes de congé
 *   rh.loans.view        Consulter les prêts et avances
 *   rh.loans.manage      Créer / approuver / rembourser les prêts et avances
 *   rh.settings          Paramétrage paie (CNSS, IUTS, rubriques)
 *   rh.portail           Portail self-service employé
 *
 * ─── Rôles ────────────────────────────────────────────────────────────────────
 *   drh          Directeur des Ressources Humaines — toutes les permissions RH
 *   rh_manager   Gestionnaire RH — tout sauf paramétrage
 *   rh_agent     Agent RH — consultation + saisie, pas de validation ni paramétrage
 *   employe      Employé — portail self-service uniquement
 */
class RhPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Créer les permissions ──────────────────────────────────────────
        $permissions = [
            'rh.view'             => 'Accéder au module RH',
            'rh.employees.view'   => 'Consulter les fiches employés',
            'rh.employees.manage' => 'Créer / modifier les employés',
            'rh.payroll.view'     => 'Voir les bulletins de paie',
            'rh.payroll.manage'   => 'Créer et calculer les bulletins',
            'rh.payroll.validate' => 'Valider et marquer comme payé',
            'rh.leaves.view'      => 'Consulter les congés',
            'rh.leaves.manage'    => 'Approuver / refuser les congés',
            'rh.loans.view'       => 'Consulter les prêts et avances',
            'rh.loans.manage'     => 'Gérer les prêts et avances',
            'rh.settings'         => 'Paramétrage paie (CNSS, IUTS, rubriques)',
            'rh.portail'          => 'Portail self-service employé',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ── 2. Rôles RH ───────────────────────────────────────────────────────

        // DRH — toutes les permissions RH
        $drh = Role::firstOrCreate(['name' => 'drh', 'guard_name' => 'web']);
        $drh->syncPermissions(array_keys($permissions));

        // RH Manager — tout sauf paramétrage
        $rhManager = Role::firstOrCreate(['name' => 'rh_manager', 'guard_name' => 'web']);
        $rhManager->syncPermissions([
            'rh.view',
            'rh.employees.view', 'rh.employees.manage',
            'rh.payroll.view', 'rh.payroll.manage', 'rh.payroll.validate',
            'rh.leaves.view', 'rh.leaves.manage',
            'rh.loans.view', 'rh.loans.manage',
            'rh.portail',
        ]);

        // RH Agent — consultation + saisie, pas de validation ni paramétrage
        $rhAgent = Role::firstOrCreate(['name' => 'rh_agent', 'guard_name' => 'web']);
        $rhAgent->syncPermissions([
            'rh.view',
            'rh.employees.view', 'rh.employees.manage',
            'rh.payroll.view', 'rh.payroll.manage',
            'rh.leaves.view',
            'rh.loans.view',
            'rh.portail',
        ]);

        // Employé — portail self-service uniquement
        $employe = Role::firstOrCreate(['name' => 'employe', 'guard_name' => 'web']);
        $employe->syncPermissions(['rh.portail']);

        // ── 3. super_admin reçoit aussi toutes les permissions RH ─────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        foreach (array_keys($permissions) as $perm) {
            if (! $superAdmin->hasPermissionTo($perm)) {
                $superAdmin->givePermissionTo($perm);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('[RH] ' . count($permissions) . ' permissions créées/vérifiées.');
        $this->command->info('[RH] Rôles créés/vérifiés : drh, rh_manager, rh_agent, employe.');
    }
}
