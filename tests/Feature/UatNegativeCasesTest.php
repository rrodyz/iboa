<?php

/**
 * [P3 — Phase 7] Cas métier négatifs, sur les mécanismes RÉELS de l'ERP.
 * Chaque test documente le comportement observé — si un garde-fou attendu par
 * la directive n'existe pas, le test le PROUVE (échoue) plutôt que de forcer
 * artificiellement le résultat, conformément à l'interdiction de contournement.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ReservationService;
use App\Services\DeliveryNoteService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function uatNegSociete(string $suffix): array
{
    $fy = FiscalYear::create(['label' => 'UATNEG'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'UAT Neg Co'.$suffix, 'email' => 'uatneg'.$suffix.'@uat.io', 'current_fiscal_year_id' => $fy->id]);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return [$co, $u];
}

it('quantité 0 sur une ligne de commande est refusée', function () {
    [$co] = uatNegSociete('qty0');
    $client = Client::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_sellable' => true]);

    $response = test()->post(route('ventes.commandes.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 0, 'unit_price' => 1000]],
    ]);

    $response->assertSessionHasErrors();
    expect(Order::where('client_id', $client->id)->exists())->toBeFalse();
});

it('stock MP insuffisant bloque le lancement de l\'OF (sans dérogation)', function () {
    [$co] = uatNegSociete('stockmp');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    $wh = Warehouse::create(['code' => 'WH-NEG-STOCKMP', 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => false]);
    $pf = Product::factory()->create(['is_manufacturable' => true, 'production_mode' => 'mto']);

    // Stock MP réellement épuisé (ligne product_stocks à 0 — l'état réel après
    // consommation totale, PAS l'absence de ligne : materialShortages() ignore
    // délibérément un composant SANS AUCUNE ligne product_stocks, cf. commentaire
    // « composant non suivi en product_stocks (bobines…) — pas d'alerte » — situation
    // normale pour une bobine, mais qui ne représente pas un MP classique épuisé).
    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => 0, 'reserved_quantity' => 0]);
    $bom = \App\Modules\Production\Models\BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM STOCKMP', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1, 'waste_rate' => 0, 'depot_sortie_id' => $wh->id]);

    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-STOCKMP', 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100_000]);
    OrderItem::create(['order_id' => $order->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 100_000, 'line_tax' => 0, 'line_total_ttc' => 100_000]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'number' => 'OF-STOCKMP', 'quantity_requested' => 10, 'status' => 'brouillon', 'depot_matiere_id' => $wh->id]);

    expect(fn () => app(ProductionService::class)->launch($of->fresh()))->toThrow(ValidationException::class);
    expect($of->fresh()->status)->toBe('brouillon');
});

it('allouer un lot inexistant est refusé sans effet de bord', function () {
    [$co] = uatNegSociete('lotko');
    $wh = Warehouse::create(['code' => 'WH-NEG-LOTKO', 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);
    $pf = Product::factory()->create(['is_manufacturable' => true]);
    $bom = \App\Modules\Production\Models\BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM LOTKO', 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'number' => 'OF-LOTKO', 'quantity_requested' => 5, 'status' => 'brouillon', 'depot_matiere_id' => $wh->id]);

    $fakeLot = new StockLot(['id' => 999999, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'INEXISTANT', 'quantity' => 100]);
    $fakeLot->exists = true; // simule une instance détachée d'une ligne supprimée entre-temps

    expect(fn () => app(ReservationService::class)->allocateMaterialLot($of, $fakeLot, 10))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('livraison sans aucun contrôle qualité sur l\'OF est bloquée (aucune dérogation)', function () {
    [$co] = uatNegSociete('qcblock');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    $wh = Warehouse::create(['code' => 'WH-NEG-QC', 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $pf = Product::factory()->create(['is_manufacturable' => false, 'is_stockable' => true, 'production_mode' => 'mto']);

    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-QCBLOCK', 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 50_000]);
    $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 5, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 50_000, 'line_tax' => 0, 'line_total_ttc' => 50_000, 'delivered_quantity' => 0]);

    // OF existe, "en_cours", mais AUCUN contrôle qualité n'a jamais été passé.
    $of = ProductionOrder::create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $pf->id, 'number' => 'OF-QCBLOCK', 'quantity_requested' => 5, 'status' => 'en_cours']);
    ProductStock::create(['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0]);

    $dn = \App\Models\DeliveryNote::create(['company_id' => $co->id, 'order_id' => $order->id, 'client_id' => $client->id, 'number' => 'BL-QCBLOCK', 'status' => 'brouillon', 'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now()]);
    $dn->items()->create(['order_item_id' => $item->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 5]);

    expect(fn () => app(DeliveryNoteService::class)->validate($dn->fresh()))
        ->toThrow(\RuntimeException::class, 'aucun contrôle qualité');
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('livraison au-delà de la quantité produite conforme est bloquée', function () {
    [$co] = uatNegSociete('overdel');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    $wh = Warehouse::create(['code' => 'WH-NEG-OVERDEL', 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $pf = Product::factory()->create(['is_manufacturable' => false, 'is_stockable' => true, 'production_mode' => 'mto']);

    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-OVERDEL', 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100_000]);
    $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 100_000, 'line_tax' => 0, 'line_total_ttc' => 100_000]);

    $of = ProductionOrder::create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $pf->id, 'number' => 'OF-OVERDEL', 'quantity_requested' => 10, 'status' => 'en_cours']);
    // Seulement 5 déclarés visés + libérés qualité — le BL en demande 10.
    $of->outputs()->create(['company_id' => $co->id, 'product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'status' => 'validee', 'validated_at' => now(), 'quality_released_at' => now(), 'unit_cost' => 1000]);
    \App\Modules\Production\Models\ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controlled_at' => now()]);
    ProductStock::create(['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 5, 'reserved_quantity' => 0]);

    $dn = \App\Models\DeliveryNote::create(['company_id' => $co->id, 'order_id' => $order->id, 'client_id' => $client->id, 'number' => 'BL-OVERDEL', 'status' => 'brouillon', 'warehouse_id' => $wh->id, 'issued_at' => now(), 'delivery_date' => now()]);
    $dn->items()->create(['order_item_id' => $item->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 10]);

    expect(fn () => app(DeliveryNoteService::class)->validate($dn->fresh()))->toThrow(\RuntimeException::class);
    expect($dn->fresh()->status)->toBe('brouillon');
});

it('client inactif : création de commande refusée (P3 — bug confirmé et corrigé)', function () {
    [$co] = uatNegSociete('clientinactif');
    $client = Client::factory()->create(['is_active' => false]);
    $product = Product::factory()->create(['is_sellable' => true]);

    $response = test()->post(route('ventes.commandes.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $response->assertSessionHasErrors('client_id');
    expect(Order::where('client_id', $client->id)->exists())->toBeFalse();
});

it('client inactif : création de devis refusée (P3 — bug confirmé et corrigé)', function () {
    [$co] = uatNegSociete('clientinactifdevis');
    $client = Client::factory()->create(['is_active' => false]);
    $product = Product::factory()->create(['is_sellable' => true]);

    $response = test()->post(route('ventes.devis.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $response->assertSessionHasErrors('client_id');
    expect(\App\Models\Quote::where('client_id', $client->id)->exists())->toBeFalse();
});
