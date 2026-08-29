<?php

/**
 * [PROD-01 — Phase 10] Navigation traçabilité amont — réutilise
 * StockController::lotTraceability() (aucune généalogie recréée) : le bon
 * de livraison affiche désormais un lien depuis chaque n° de lot vers la
 * fiche de traçabilité de ce lot. Facture→BL→lot est déjà couvert : la
 * fiche facture liait déjà son BL avant cette mission.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function btnCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'BTN-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'BTN Co'], ['email' => 'btn@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'btn_role', 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::firstOrCreate(['name' => 'deliveries.view', 'guard_name' => 'web']));
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $co;
}

it('affiche un lien traçabilité depuis le n° de lot d’une ligne de BL', function () {
    $co = btnCompany();
    $wh = Warehouse::firstOrCreate(['code' => 'WBTN'], ['name' => 'WBTN', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['name' => 'Tôle bac traçée']);
    $lot = StockLot::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-BTN-1', 'quantity' => 10, 'unit_cost' => 1000, 'received_at' => '2026-01-01', 'status' => 'disponible']);

    $delivery = DeliveryNote::create(['company_id' => $co->id, 'client_id' => Client::factory()->create()->id, 'number' => 'BL-BTN-1', 'status' => 'brouillon', 'warehouse_id' => $wh->id, 'issued_at' => now()]);
    $delivery->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 5, 'unit_price' => 1000, 'lot_number' => 'LOT-BTN-1']);

    $response = $this->get(route('ventes.bons-livraison.show', $delivery))->assertOk();
    $response->assertSee(route('stocks.lots.traceability', $lot), false);
    $response->assertSee('LOT-BTN-1');
});

it('n’affiche aucun lien quand le n° de lot de la ligne ne correspond à aucun StockLot connu', function () {
    $co = btnCompany();
    $wh = Warehouse::firstOrCreate(['code' => 'WBTN2'], ['name' => 'WBTN2', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['name' => 'Tôle bac orpheline']);

    $delivery = DeliveryNote::create(['company_id' => $co->id, 'client_id' => Client::factory()->create()->id, 'number' => 'BL-BTN-2', 'status' => 'brouillon', 'warehouse_id' => $wh->id, 'issued_at' => now()]);
    $delivery->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 5, 'unit_price' => 1000, 'lot_number' => 'LOT-INCONNU']);

    $response = $this->get(route('ventes.bons-livraison.show', $delivery))->assertOk();
    $response->assertSee('LOT-INCONNU');
    $response->assertDontSee('Traçabilité amont du lot', false);
});
