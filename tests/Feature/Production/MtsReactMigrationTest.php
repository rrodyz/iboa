<?php

/**
 * [A3-UI-V2 — REACT-01D] Migration de la planification MTS vers Inertia+React.
 * Même route ('production.orders.mts'), même URI, même middleware
 * (permission:production.view) — seul le rendu change. Réutilise
 * NetRequirementService sans le toucher : chaque terme (dispo/cible/seuil/
 * besoin/etat) est transmis tel quel, jamais recalculé côté React.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtsReactCo(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTSR-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MtsReact Co'], ['email' => 'mtsreact@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WMTSR'], ['name' => 'WMTSR', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    return $co;
}

function mtsReactUser(array $perms = ['production.view']): User
{
    $co = mtsReactCo();
    $role = Role::firstOrCreate(['name' => 'mts_react_role_' . implode('_', $perms)], ['guard_name' => 'web']);
    foreach ($perms as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('redirige le visiteur non authentifié', function () {
    $this->get(route('production.orders.mts'))->assertRedirect(route('login'));
});

it('refuse un utilisateur sans permission production.view', function () {
    $co = mtsReactCo();
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    test()->actingAs($u);

    $this->get(route('production.orders.mts'))->assertForbidden();
});

it('un utilisateur avec production.view reçoit le composant Production/Mts/Index', function () {
    mtsReactUser();

    $this->get(route('production.orders.mts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Mts/Index')->has('rows')->has('links'));
});

it('un utilisateur sans production.create ne voit jamais canCreateOf=true', function () {
    $co = mtsReactCo();
    mtsReactUser();
    $p = Product::factory()->create(['name' => 'Fer MTS view-only', 'production_mode' => 'mts', 'is_stockable' => true, 'is_active' => true, 'stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);

    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $role = Role::firstOrCreate(['name' => 'mts_view_only'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']));
    $u->assignRole($role);
    test()->actingAs($u);

    $rows = $this->get(route('production.orders.mts'))->assertOk()->inertiaProps('rows');
    $row = collect($rows)->firstWhere(fn ($r) => $r['productId'] === $p->id);

    expect($row['besoin'])->toEqual(500.0)
        ->and($row['canCreateOf'])->toBeFalse();
});

it('super_admin voit MTS malgré getAllPermissions() vide', function () {
    $co = mtsReactCo();
    $role = Role::firstOrCreate(['name' => 'super_admin'], ['guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('production.orders.mts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Mts/Index')->where('auth.is_super_admin', true));
});

it('article en rupture : dispo, besoin et état transmis fidèlement', function () {
    $co = mtsReactCo();
    mtsReactUser();
    $p = Product::factory()->create(['name' => 'Fer rupture', 'production_mode' => 'mts', 'is_stockable' => true, 'is_active' => true, 'stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => Warehouse::where('code', 'WMTSR')->value('id'), 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 500]);

    $rows = $this->get(route('production.orders.mts'))->assertOk()->inertiaProps('rows');
    $row = collect($rows)->firstWhere(fn ($r) => $r['productId'] === $p->id);

    expect($row['dispo'])->toEqual(0.0)
        ->and($row['besoin'])->toEqual(500.0)
        ->and($row['etat'])->toBe('rupture');
});

it('article non paramétré : aucun besoin calculable, lien vers la fiche article', function () {
    $co = mtsReactCo();
    mtsReactUser();
    $p = Product::factory()->create(['name' => 'Fer non paramétré', 'production_mode' => 'mts', 'is_stockable' => true, 'is_active' => true, 'stock_max' => null, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);

    $rows = $this->get(route('production.orders.mts'))->assertOk()->inertiaProps('rows');
    $row = collect($rows)->firstWhere(fn ($r) => $r['productId'] === $p->id);

    expect($row['etat'])->toBe('non_parametre')
        ->and($row['editUrl'])->toBe(route('products.edit', $p));
});

it('table vide sans article MTS actif : rows est un tableau vide, pas null', function () {
    mtsReactUser();

    $this->get(route('production.orders.mts'))
        ->assertInertia(fn (Assert $page) => $page->where('rows', []));
});

it('les liens transmis pointent vers les mêmes routes que l’ancien Blade', function () {
    mtsReactUser();

    $this->get(route('production.orders.mts'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.mtoUrl', route('production.orders.mto'))
            ->where('links.eligibleUrl', route('production.orders.eligible'))
            ->where('links.ofIndexUrl', route('production.orders.index'))
            ->etc()
        );
});

it('la route production.orders.mts reste GET production/orders/mts sous le même middleware', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'production.orders.mts');

    expect($route->uri())->toBe('production/orders/mts');
    expect($route->methods())->toContain('GET');
});
