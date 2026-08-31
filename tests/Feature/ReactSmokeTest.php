<?php

/**
 * [A3-UI-V2 — REACT-01A] Validation de l'infrastructure Inertia/React,
 * isolée de toute page métier. Aucune donnée métier créée.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function smokeCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SMOKE-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'Smoke Co'], ['email' => 'smoke@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function smokeUser(array $permissions = ['production.view']): User
{
    $co = smokeCompany();
    $role = Role::firstOrCreate(['name' => 'smoke_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('redirige un visiteur non connecté vers le login', function () {
    $this->get(route('ui-v2.smoke'))->assertRedirect(route('login'));
});

it('rend un composant Inertia pour un utilisateur connecté', function () {
    smokeUser();

    // [Comportement Inertia standard] Le premier chargement (visite complète,
    // pas un fetch du client JS Inertia) ne porte jamais X-Inertia en
    // réponse — seulement les navigations suivantes, qui envoient ce header
    // en REQUÊTE. assertInertia() simule cela correctement.
    $this->get(route('ui-v2.smoke'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ReactSmoke/Index'));
});

it('le composant rendu est bien ReactSmoke/Index', function () {
    smokeUser();

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page->component('ReactSmoke/Index'));
});

it('partage auth.user pour un utilisateur connecté', function () {
    $u = smokeUser();

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $u->id)
            ->where('auth.user.name', $u->name)
        );
});

it('partage auth.permissions avec la permission réelle accordée', function () {
    smokeUser(['production.view']);

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('hasExamplePermission', true)
        );

    // Vérifie aussi le shared prop brut auth.permissions (pas seulement la prop de page).
    $response = $this->get(route('ui-v2.smoke'));
    $props = $response->viewData('page')['props'];
    expect($props['auth']['permissions'])->toContain('production.view');
});

it('un utilisateur sans la permission voit hasExamplePermission=false, jamais bloqué par React', function () {
    smokeUser(['ventes.view']); // sans production.view

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page->where('hasExamplePermission', false));
});

it('le flash success est partagé après une action', function () {
    smokeUser();

    $this->post(route('ui-v2.smoke.flash'))->assertRedirect();

    $this->get(route('ui-v2.smoke'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Flash déclenché depuis React-01A — pont Laravel -> Inertia -> React confirmé.')
        );
});

it('une route Blade existante répond toujours en Blade, jamais en Inertia', function () {
    // dashboard.view : sinon UserHomeRoute redirige (comportement métier
    // réel, hors périmètre React-01A) — ici on veut vraiment tester la page.
    smokeUser(['dashboard.view']);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    expect($response->headers->has('X-Inertia'))->toBeFalse();
});

it('une route protégée par permission reste bloquée par Laravel, indépendamment de React', function () {
    smokeUser(['ventes.view']); // sans production.update

    $this->get(route('production.mrp'))->assertForbidden();
});
