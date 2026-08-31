<?php

/**
 * [A3-UI-V2 — REACT-01B] Migration du Dashboard principal vers Inertia+React.
 * Même route ('dashboard'), même URI (/dashboard), même middleware, mêmes
 * permissions (reports.view) — seul le rendu change (Blade -> Inertia/React).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function dashCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'DASH-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'Dash Co'], ['email' => 'dash@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function dashUserWithReportsView(): User
{
    $co = dashCompany();
    $role = Role::firstOrCreate(['name' => 'dash_reports_role'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']));
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}

it('redirige le visiteur non authentifié', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('l’utilisateur authentifié avec reports.view reçoit le composant Dashboard/Index', function () {
    test()->actingAs(dashUserWithReportsView());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('hasReportsView', true)
        );
});

it('toutes les props requises sont présentes pour un profil reports.view', function () {
    test()->actingAs(dashUserWithReportsView());

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('kpis')
            ->has('counters')
            ->has('charts')
            ->has('recentActivity')
            ->has('alertesVigilance')
            ->has('links')
            ->has('company')
            ->etc()
        );
});

it('les 4 KPI financiers du contrôleur sont bien transmis', function () {
    test()->actingAs(dashUserWithReportsView());

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('kpis.caHtMois')
            ->has('kpis.soldeTresorerie')
            ->has('kpis.encaissementsMois')
            ->has('kpis.decaissementsMois')
            ->etc()
        );
});

it('un utilisateur sans reports.view reçoit hasReportsView=false et aucune donnée financière', function () {
    $co = dashCompany();
    $role = Role::firstOrCreate(['name' => 'dash_no_reports_role'], ['guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    // [SEC §15] Ce profil n'a pas dashboard.view : redirigé (comportement
    // inchangé, UserHomeRoute::canSeeDashboard() reste l'autorité).
    $this->get(route('dashboard'))->assertRedirect();
});

it('un profil avec dashboard.view mais sans reports.view voit le dashboard neutre, sans KPI', function () {
    $co = dashCompany();
    $role = Role::firstOrCreate(['name' => 'dash_neutral_role'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('hasReportsView', false)
            ->missing('kpis')
            ->missing('counters')
        );
});

it('super_admin voit le dashboard complet malgré getAllPermissions() vide — via Gate::before()', function () {
    $co = dashCompany();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->where('hasReportsView', true)
            ->where('auth.is_super_admin', true)
        );
});

it('les liens transmis pointent vers les mêmes routes que l’ancien Blade', function () {
    test()->actingAs(dashUserWithReportsView());

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.auditUrl', route('audit.index'))
            ->where('links.validationsUrl', route('validations.index'))
            ->where('counters.commandesUrl', route('ventes.commandes.index'))
            ->where('counters.ofUrl', route('production.orders.index'))
            ->where('counters.stockUrl', route('stocks.index'))
            ->etc()
        );
});

it('la route dashboard reste GET /dashboard sous le même middleware — aucune route business modifiée', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'dashboard');

    expect($route->uri())->toBe('dashboard');
    expect($route->methods())->toContain('GET');
});

it('les anciennes actions protégées (ex: dashboard.kpis JSON réservé à reports.view) restent protégées', function () {
    $co = dashCompany();
    $role = Role::firstOrCreate(['name' => 'dash_kpis_role'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('dashboard.kpis'))->assertForbidden();
});
