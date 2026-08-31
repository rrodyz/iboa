<?php

/**
 * [A3-UI-V2 — REACT-01B Phase 1] super_admin autorise via Gate::before(),
 * jamais via des permissions Spatie assignées — auth.is_super_admin comble
 * l'écart côté UX sans toucher au mécanisme d'autorisation serveur.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function suaCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SA-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SA Co'], ['email' => 'sa@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

it('un utilisateur régulier a des permissions effectives normales, is_super_admin=false', function () {
    $co = suaCompany();
    $role = Role::firstOrCreate(['name' => 'sa_regular', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.is_super_admin', false)
            ->has('auth.permissions', 1)
        );
});

it('super_admin a is_super_admin=true même si getAllPermissions() est vide', function () {
    $co = suaCompany();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    // Preuve de la trouvaille elle-même : aucune permission Spatie assignée.
    expect($u->getAllPermissions())->toHaveCount(0);

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.is_super_admin', true));
});

it('le serveur reste protégé pour super_admin comme pour tout rôle — Gate::before(), pas la donnée frontend', function () {
    $co = suaCompany();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    // Une route protégée par permission:production.update reste accessible
    // à super_admin — mais via Gate::before(), jamais via auth.is_super_admin
    // (qui n'existe côté serveur que dans le payload Inertia, jamais consulté
    // par le middleware permission:*).
    $this->get(route('production.mrp'))->assertOk();
});
