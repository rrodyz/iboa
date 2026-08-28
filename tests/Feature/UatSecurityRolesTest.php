<?php

/**
 * [P3 — Phase 9] Contrôle des rôles : un profil non habilité ne doit jamais
 * pouvoir exécuter une action réservée à un autre métier, même via un appel
 * HTTP direct (pas seulement masquée à l'écran). Chaque cas est vérifié par
 * l'appel RÉEL à la route protégée — jamais par lecture statique du seeder
 * seule, qui ne prouve que l'intention, pas le comportement.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
});

function uatSecSociete(string $suffix): Company
{
    $fy = FiscalYear::create(['label' => 'UATSEC'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);

    return Company::create(['name' => 'UAT Sec Co'.$suffix, 'email' => 'uatsec'.$suffix.'@uat.io', 'current_fiscal_year_id' => $fy->id]);
}

function uatSecUser(Company $co, string $role): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::findByName($role, 'web'));

    return $u;
}

function uatSecOrderAndOf(Company $co): array
{
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    $product = Product::factory()->create(['is_manufacturable' => true, 'production_mode' => 'mto']);
    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-SEC-'.uniqid(), 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100_000]);
    OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'description' => 'x', 'quantity' => 5, 'unit_price' => 20_000, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 100_000, 'line_tax' => 0, 'line_total_ttc' => 100_000]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $product->id, 'name' => 'BOM SEC', 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $product->id, 'bill_of_material_id' => $bom->id, 'number' => 'OF-SEC-'.uniqid(), 'quantity_requested' => 5, 'status' => 'brouillon']);

    return [$order, $of, $bom, $client];
}

it('commercial ne peut pas lancer un OF', function () {
    $co = uatSecSociete('01');
    [, $of] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'commercial'));

    $response = test()->post(route('production.orders.launch', $of));

    $response->assertForbidden();
    expect($of->fresh()->status)->toBe('brouillon');
});

it('magasinier ne peut pas accorder une dérogation financière de production', function () {
    $co = uatSecSociete('02');
    [$order] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'magasinier'));

    $response = test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Tentative magasinier']);

    $response->assertForbidden();
    expect($order->fresh()->production_approved)->toBeFalse();
});

it('caissier ne peut pas modifier une nomenclature', function () {
    $co = uatSecSociete('03');
    [, , $bom] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'caissier'));

    $response = test()->put(route('production.bom.update', $bom), ['name' => 'BOM modifiée par caissier', 'is_active' => true]);

    $response->assertForbidden();
    expect($bom->fresh()->name)->toBe('BOM SEC');
});

it('opérateur de production ne peut pas approuver une dérogation financière', function () {
    $co = uatSecSociete('04');
    [$order] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'operateur_production'));

    $response = test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Tentative opérateur']);

    $response->assertForbidden();
    expect($order->fresh()->production_approved)->toBeFalse();
});

// ── Profils AUTORISÉS : vérifie qu'on ne bloque pas tout par excès ────────

it('chef de production PEUT lancer un OF (positif — pas de sur-restriction)', function () {
    $co = uatSecSociete('05');
    [, $of] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'chef_production'));

    $response = test()->post(route('production.orders.launch', $of));

    $response->assertRedirect();
    expect($of->fresh()->status)->not->toBe('brouillon');
});

it('DAF PEUT accorder une dérogation financière (positif)', function () {
    $co = uatSecSociete('06');
    [$order] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'daf'));

    $response = test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Accord DAF UAT']);

    $response->assertRedirect();
    expect($order->fresh()->production_approved)->toBeTrue();
});

it('DG PEUT accorder une dérogation financière (positif)', function () {
    $co = uatSecSociete('07');
    [$order] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'directeur'));

    $response = test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Accord DG UAT']);

    $response->assertRedirect();
    expect($order->fresh()->production_approved)->toBeTrue();
});

it('responsable commercial (Administrateur des ventes) PEUT accorder une dérogation financière (positif)', function () {
    $co = uatSecSociete('08');
    [$order] = uatSecOrderAndOf($co);
    test()->actingAs(uatSecUser($co, 'responsable_commercial'));

    $response = test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Accord Admin Ventes UAT']);

    $response->assertRedirect();
    expect($order->fresh()->production_approved)->toBeTrue();
});
