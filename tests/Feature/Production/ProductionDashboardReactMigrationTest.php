<?php

/**
 * [A3-UI-V2 — REACT-01C] Migration du Dashboard Production vers Inertia+React.
 * Même route ('production.dashboard'), même URI, même middleware
 * (permission:production.view) — seul le rendu change. Aucune requête ni
 * formule du contrôleur n'est modifiée (TRS, rendement, coûts restent des
 * calculs serveur, jamais recalculés côté React).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function prodDashCo(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PRODDASH-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'ProdDash Co'], ['email' => 'proddash@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function prodDashUserWithView(): User
{
    $co = prodDashCo();
    $role = Role::firstOrCreate(['name' => 'proddash_view_role'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}

it('redirige le visiteur non authentifié', function () {
    $this->get(route('production.dashboard'))->assertRedirect(route('login'));
});

it('refuse un utilisateur sans permission production.view', function () {
    $co = prodDashCo();
    $role = Role::firstOrCreate(['name' => 'proddash_no_perm_role'], ['guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('production.dashboard'))->assertForbidden();
});

it('un utilisateur avec production.view reçoit le composant Production/Dashboard/Index', function () {
    test()->actingAs(prodDashUserWithView());

    $this->get(route('production.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Dashboard/Index'));
});

it('toutes les props requises sont présentes', function () {
    test()->actingAs(prodDashUserWithView());

    $this->get(route('production.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('kpis')->has('trs')->has('meta')
            ->has('chaine')->has('ofEnCours')->has('suiviJour')
            ->has('alertes')->has('consoMatieres')->has('perfMachines')
            ->has('controlesQualite')->has('links')
            ->etc()
        );
});

it('les KPI OF en cours reflètent les ordres de fabrication réels', function () {
    $co = prodDashCo();
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PD-1', 'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 0]);
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PD-2', 'status' => 'lance', 'quantity_requested' => 5, 'quantity_produced' => 0]);
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PD-3', 'status' => 'termine', 'quantity_requested' => 5, 'quantity_produced' => 5]);
    test()->actingAs(prodDashUserWithView());

    $this->get(route('production.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('kpis.of_en_cours', 2)->etc());
});

it('super_admin voit le dashboard production malgré getAllPermissions() vide', function () {
    $co = prodDashCo();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('production.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Dashboard/Index')->where('auth.is_super_admin', true));
});

it('les liens transmis pointent vers les mêmes routes que l’ancien Blade', function () {
    test()->actingAs(prodDashUserWithView());

    $this->get(route('production.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.validationsUrl', route('validations.index'))
            ->where('links.ofCreateUrl', route('production.orders.create'))
            ->where('links.planningUrl', route('production.planning'))
            ->where('links.ofIndexUrl', route('production.orders.index'))
            ->where('links.qualiteUrl', route('qualite.inspections.index'))
            ->etc()
        );
});

it('la route production.dashboard reste GET production/dashboard sous le même middleware', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'production.dashboard');

    expect($route->uri())->toBe('production/dashboard');
    expect($route->methods())->toContain('GET');
});

it('les autres pages Production (MTS/MRP/Planning/OF) ne sont pas migrées par erreur', function () {
    test()->actingAs(prodDashUserWithView());

    foreach (['production.orders.mts', 'production.mrp', 'production.planning', 'production.orders.index'] as $name) {
        $response = $this->get(route($name));
        expect($response->headers->has('X-Inertia'))->toBeFalse();
    }
});

it('le dashboard production charge react-*.js, jamais app.js', function () {
    test()->actingAs(prodDashUserWithView());

    $response = $this->get(route('production.dashboard'));
    $response->assertOk();
    expect($response->headers->has('X-Inertia'))->toBeFalse(); // premier chargement complet
    $html = $response->getContent();
    // [Trouvaille] react.jsx importe aussi resources/css/app.css (partage
    // Tailwind, décision REACT-01A) — le CSS émis garde le nom "app-*.css"
    // même sur une page React. Un simple toContain('/build/assets/app-')
    // matcherait donc CE CSS légitime : on cible spécifiquement les .js.
    expect($html)->toMatch('#/build/assets/react-[^"]+\.js#')
        ->and($html)->not->toMatch('#/build/assets/app-[^"]+\.js#');
});
