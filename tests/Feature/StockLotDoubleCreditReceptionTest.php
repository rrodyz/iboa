<?php

/**
 * [FIX P1 — double crédit stock_lots à la réception] Un article coil-managed
 * recevait son StockLot crédité DEUX FOIS pour une seule entrée physique :
 *
 *   1. StockService::upsertLot() — mécanisme générique déclenché par
 *      recordMovement() dès qu'un lot_number est présent sans stock_lot_id
 *      (ReplayReceptionStockSync::enter() ne passe jamais stock_lot_id) ;
 *   2. CoilReceptionService::createFromReception() — recrédite le MÊME lot
 *      (même clé product_id+warehouse_id+lot_number) via
 *      $lot->increment('quantity', $weight) dans la branche $alreadyStocked.
 *
 * 1000 kg reçus finissaient à stock_lots.quantity=2000, alors que
 * coils.remaining_weight et product_stocks restaient corrects à 1000 —
 * seule la table stock_lots divergeait.
 *
 * Correctif : ReplayReceptionStockSync signale skip_lot_upsert=true pour un
 * article coil-managed ; StockService n'upsert alors plus le lot lui-même,
 * laissant CoilReceptionService seul propriétaire du crédit pour ces
 * articles. Aucun changement pour les articles lotés non-bobine.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceptionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function slcSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SLC'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SLC Co'], ['email' => 'slc@slc.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function slcCommandeEtLigne(Company $co, Supplier $supplier, Product $article, float $qte, float $prixUnitaire): PurchaseOrder
{
    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id, 'number' => 'PO-SLC-'.uniqid(),
        'status' => 'confirme', 'currency_code' => 'XOF', 'issued_at' => now(), 'ordered_at' => now(),
    ]);
    $po->items()->create([
        'product_id' => $article->id, 'description' => $article->name,
        'quantity' => $qte, 'unit_price' => $prixUnitaire,
        'line_total_ht' => $qte * $prixUnitaire, 'line_tax' => 0, 'line_total_ttc' => $qte * $prixUnitaire,
        'received_quantity' => 0,
    ]);

    return $po;
}

it('ne crédite le lot qu\'une fois pour un article coil-managed reçu en une réception', function () {
    $co = slcSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'SLC-A'], ['name' => 'Dépôt SLC', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur SLC', 'code' => 'FSLC']);
    $cat = ItemCategory::firstOrCreate(['code' => 'SLC_BOBINE'], ['name' => 'SLC Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $bobine = Product::factory()->create(['name' => 'Bobine SLC', 'is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    expect($bobine->isCoilManaged())->toBeTrue();

    $po = slcCommandeEtLigne($co, $supplier, $bobine, 1000, 500);
    $rec = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item = $rec->items()->first();

    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $item->id => ['received_quantity' => 1000, 'lot_number' => 'LOT-SLC-001'],
    ]);

    $lot = StockLot::where('product_id', $bobine->id)->where('lot_number', 'LOT-SLC-001')->first();
    $coil = Coil::where('product_id', $bobine->id)->where('lot_number', 'LOT-SLC-001')->first();
    $ps = (float) ProductStock::where('product_id', $bobine->id)->where('warehouse_id', $wh->id)->value('quantity');

    expect((float) $lot->quantity)->toBe(1000.0)->not->toBe(2000.0);
    expect((float) $lot->initial_quantity)->toBe(1000.0);
    expect((float) $coil->remaining_weight)->toBe(1000.0);
    expect($ps)->toBe(1000.0);
});

it('répartit 2 réceptions du même PO en 2 lots distincts, sans double comptage sur aucun des deux', function () {
    $co = slcSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'SLC-B'], ['name' => 'Dépôt SLC B', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur SLC B', 'code' => 'FSLCB']);
    $cat = ItemCategory::firstOrCreate(['code' => 'SLC_BOBINE_B'], ['name' => 'SLC Bobines B', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $bobine = Product::factory()->create(['name' => 'Bobine SLC B', 'is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);

    $po = slcCommandeEtLigne($co, $supplier, $bobine, 1500, 500);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item1 = $rec1->items()->first();
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [
        $item1->id => ['received_quantity' => 1000, 'lot_number' => 'LOT-A'],
    ]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item2 = $rec2->items()->first();
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [
        $item2->id => ['received_quantity' => 500, 'lot_number' => 'LOT-B'],
    ]);

    $lotA = StockLot::where('product_id', $bobine->id)->where('lot_number', 'LOT-A')->first();
    $lotB = StockLot::where('product_id', $bobine->id)->where('lot_number', 'LOT-B')->first();
    $sumLots = (float) StockLot::where('product_id', $bobine->id)->sum('quantity');
    $sumCoils = (float) Coil::where('product_id', $bobine->id)->sum('remaining_weight');
    $ps = (float) ProductStock::where('product_id', $bobine->id)->where('warehouse_id', $wh->id)->value('quantity');

    expect((float) $lotA->quantity)->toBe(1000.0);
    expect((float) $lotB->quantity)->toBe(500.0);
    expect($sumLots)->toBe(1500.0);
    expect($sumCoils)->toBe(1500.0);
    expect($ps)->toBe(1500.0);
});

it('continue de créditer normalement le lot d\'un article NON coil-managed (pas de régression)', function () {
    $co = slcSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'SLC-C'], ['name' => 'Dépôt SLC C', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur SLC C', 'code' => 'FSLCC']);
    // Catégorie SANS coil_managed — article loté "classique" (marchandise, consommable...).
    $cat = ItemCategory::firstOrCreate(['code' => 'SLC_STANDARD'], ['name' => 'SLC Standard', 'company_id' => $co->id, 'coil_managed' => false, 'lot_managed' => true, 'is_active' => true]);
    $article = Product::factory()->create(['name' => 'Article loté standard SLC', 'is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    expect($article->isCoilManaged())->toBeFalse();

    $po = slcCommandeEtLigne($co, $supplier, $article, 200, 1000);
    $rec = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item = $rec->items()->first();
    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $item->id => ['received_quantity' => 200, 'lot_number' => 'LOT-STD-001'],
    ]);

    $lot = StockLot::where('product_id', $article->id)->where('lot_number', 'LOT-STD-001')->first();
    $ps = (float) ProductStock::where('product_id', $article->id)->where('warehouse_id', $wh->id)->value('quantity');

    // Aucun Coil ne doit être créé pour un article non coil-managed — c'est
    // uniquement StockService::upsertLot() qui crédite ce lot, comme avant.
    expect(Coil::where('product_id', $article->id)->count())->toBe(0);
    expect((float) $lot->quantity)->toBe(200.0);
    expect($ps)->toBe(200.0);
});
