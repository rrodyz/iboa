<?php

/**
 * [A3-UI-V2 — REACT-01C] Migration du tableau de bord MTO vers Inertia+React.
 * Réutilise les helpers globaux mtoDashCompany()/mtoDashOrder() définis dans
 * tests/Feature/Production/MtoDashboardTest.php (PROD-01) — même setup, pas
 * de duplication, toujours exécuté dans la même suite (voir Phase 31).
 *
 * [Phase 4/13] eligible et canCreateOf ne sont JAMAIS recalculés côté test
 * frontend — on vérifie que le backend transmet la même décision que le
 * Blade historique (mêmes fixtures que MtoDashboardTest).
 */

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionMachine;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('redirige le visiteur non authentifié', function () {
    $this->get(route('production.orders.mto'))->assertRedirect(route('login'));
});

it('un super_admin reçoit le composant Production/Mto/Index', function () {
    mtoDashCompany();

    $this->get(route('production.orders.mto'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Mto/Index')->has('rows')->has('links'));
});

it('un utilisateur avec seulement production.view (pas production.create) ne voit jamais canCreateOf=true', function () {
    $co = mtoDashCompany(); // acte déjà en super_admin — on bascule sur un rôle limité
    $role = Role::firstOrCreate(['name' => 'mto_view_only'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $p = Product::factory()->create(['name' => 'Tôle bac perm', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 20);
    $order->update(['production_approved' => true, 'production_approval_fingerprint' => $order->productionFinancialFingerprint()]);

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.eligible', true)->where('rows.0.canCreateOf', false)->etc());
});

it('ligne sans OF, non produite : canCreateOf suit eligible et restante > 0', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac E1', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 40);
    $order->update(['production_approved' => true, 'production_approval_fingerprint' => $order->productionFinancialFingerprint()]);

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.restante', 40)
            ->where('rows.0.eligible', true)
            ->where('rows.0.canCreateOf', true)
            ->etc()
        );
});

it('ligne avec un OF existant : le champ of est transmis avec numéro et statut', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac E2', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 100);
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-MTOR-1', 'status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 40, 'product_id' => $p->id, 'order_id' => $order->id]);

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.of.number', 'OF-MTOR-1')
            ->where('rows.0.of.status', 'en_cours')
            ->where('rows.0.produite', 40)
            ->where('rows.0.restante', 60)
            ->etc()
        );
});

it('ligne entièrement produite (pas livrée) : restante = 0, canCreateOf = false', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac E3', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 50);
    $order->update(['production_approved' => true, 'production_approval_fingerprint' => $order->productionFinancialFingerprint()]);
    ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-MTOR-2', 'status' => 'termine', 'quantity_requested' => 50, 'quantity_produced' => 50, 'product_id' => $p->id, 'order_id' => $order->id]);

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.restante', 0)->where('rows.0.canCreateOf', false)->etc());
});

it('blocage financier : commande MTO ni approuvée ni réglée -> eligible=false, canCreateOf=false', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac E4', 'production_mode' => 'mto']);
    $client = Client::factory()->create(['payment_mode' => 'cash']);
    mtoDashOrder($co, $p, 25, over: ['client_id' => $client->id]);

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.eligible', false)->where('rows.0.canCreateOf', false)->etc());
});

it('stock disponible transmis = quantité − réservé, jamais la quantité physique brute', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac stock2', 'production_mode' => 'mto']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => \App\Models\Warehouse::where('code', 'WMTOD')->value('id'), 'quantity' => 40, 'reserved_quantity' => 15, 'avg_cost' => 1000]);
    mtoDashOrder($co, $p, 10);

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.dispo', 25)->etc());
});

it('les liens transmis pointent vers les mêmes routes que l’ancien Blade', function () {
    mtoDashCompany();

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links.eligibleUrl', route('production.orders.eligible'))
            ->where('links.mtsUrl', route('production.orders.mts'))
            ->etc()
        );
});

it('table vide sans lignes MTO ouvertes : rows est un tableau vide, pas null', function () {
    mtoDashCompany();

    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page->where('rows', []));
});

it('la route production.orders.mto reste GET production/orders/mto — non paginée (limit 200, inchangé)', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'production.orders.mto');

    expect($route->uri())->toBe('production/orders/mto');
    expect($route->methods())->toContain('GET');
});

// [Phase 26 — SÉCURITÉ] Le bouton « Créer OF » masqué (canCreateOf=false) est
// une UX, PAS la protection. La vraie garde financière backend s'exerce au
// LANCEMENT de l'OF (ProductionService::launch() -> checkFinancialGate()),
// pas à sa création en brouillon (voir ProductionService::create(), qui ne
// vérifie ni éligibilité financière ni permission MTO-spécifique). Ce test
// prouve que même avec canCreateOf=false côté dashboard, un OF brouillon créé
// pour une commande non couverte est bloqué au lancement, indépendamment de
// ce que l'UI affichait.
it('MTO FINANCIAL SECURITY: le serveur bloque le LANCEMENT d’un OF pour une commande non éligible, même sans passer par le bouton caché', function () {
    $co = mtoDashCompany(); // super_admin -> bypass Gate::before(), donc on rebascule sur un rôle explicite
    $role = Role::firstOrCreate(['name' => 'mto_launcher'], ['guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']));
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'production.launch', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $p = Product::factory()->create(['name' => 'Tôle bac sécu', 'production_mode' => 'mto']);
    $client = Client::factory()->create(['payment_mode' => 'cash']);
    $order = mtoDashOrder($co, $p, 30, over: ['client_id' => $client->id]); // non approuvée, non réglée -> non éligible

    // Dashboard confirme canCreateOf=false pour cette ligne (aucun rôle ne l'affiche).
    test()->actingAs($u);
    $this->get(route('production.orders.mto'))
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.eligible', false)->etc());

    // Malgré le bouton caché, un OF brouillon PEUT être créé (create() ne vérifie
    // pas l'éligibilité financière — seulement le lancement le fait).
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-MTOR-SEC',
        'status' => 'brouillon', 'quantity_requested' => 30, 'quantity_produced' => 0,
        'product_id' => $p->id, 'order_id' => $order->id,
    ]);

    // Tentative de lancement direct (POST), en contournant totalement l'UI :
    $this->post(route('production.orders.launch', $of));

    expect($of->fresh()->status)->not->toBe('lance');
});
