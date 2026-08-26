<?php

/**
 * [FIX P1-F — transfert inter-dépôts fiable par lot] Avant ce fix,
 * StockTransferService::ship()/receive() mettait à jour product_stocks
 * (source/destination) mais ne touchait JAMAIS stock_lots (aucune requête
 * StockLot dans ship()/receive()/cancel()). Le lot_number saisi sur la ligne
 * de transfert était déjà transporté jusqu'au StockMovement, mais jamais
 * validé ni répercuté sur le grand livre des lots.
 *
 * Root cause :
 *  - ship()    décrémentait ProductStock source, jamais StockLot source.
 *  - receive() incrémentait ProductStock destination, jamais StockLot
 *    destination (ni création si absent, ni cumul si présent).
 *
 * Correctif : résolution + verrouillage (lockForUpdate) du StockLot source
 * dans ship() (avant toute écriture — bloque lot inconnu / mauvais produit /
 * mauvais dépôt / quantité insuffisante), décrément du lot source, création
 * contrôlée ou cumul du lot destination dans receive(), restauration
 * symétrique dans cancel(). FK stock_movements.stock_lot_id renseignée.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function sltSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SLT'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SLT Co'], ['email' => 'slt@slt.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function sltWarehouses(Company $co, string $suffix = ''): array
{
    $a = Warehouse::firstOrCreate(['code' => 'SLT-A' . $suffix], ['name' => 'Dépôt A' . $suffix, 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $b = Warehouse::firstOrCreate(['code' => 'SLT-B' . $suffix], ['name' => 'Dépôt B' . $suffix, 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);

    return [$a, $b];
}

function sltLotQty(int $productId, int $warehouseId, string $lot): float
{
    return (float) (StockLot::where('product_id', $productId)->where('warehouse_id', $warehouseId)->where('lot_number', $lot)->value('quantity') ?? 0);
}

function sltStockQty(int $productId, int $warehouseId): float
{
    return (float) (ProductStock::where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('quantity') ?? 0);
}

// ═══ T1 — transfert partiel : product_stocks ET stock_lots synchronisés ═══

it('T1 — transfert partiel (300→100) : product_stocks et stock_lots cohérents des deux côtés', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T1');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-TRANSFER-001', 'quantity' => 300, 'unit_cost' => 500]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 100, 'lot_number' => 'LOT-TRANSFER-001']],
    ]);
    app(StockTransferService::class)->ship($transfer);
    app(StockTransferService::class)->receive($transfer->fresh());

    expect(sltStockQty($product->id, $a->id))->toBe(200.0);
    expect(sltStockQty($product->id, $b->id))->toBe(100.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-TRANSFER-001'))->toBe(200.0);
    expect(sltLotQty($product->id, $b->id, 'LOT-TRANSFER-001'))->toBe(100.0);

    // Invariants globaux
    expect(sltStockQty($product->id, $a->id) + sltStockQty($product->id, $b->id))->toBe(300.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-TRANSFER-001') + sltLotQty($product->id, $b->id, 'LOT-TRANSFER-001'))->toBe(300.0);

    // Traçabilité : coût préservé, FK stock_lot_id renseignée, lot_number présent des 2 côtés
    $destLot = StockLot::where('product_id', $product->id)->where('warehouse_id', $b->id)->where('lot_number', 'LOT-TRANSFER-001')->first();
    expect((float) $destLot->unit_cost)->toBe(500.0);
    expect((float) $destLot->initial_quantity)->toBe(100.0);

    $movOut = StockMovement::where('type', 'sortie')->where('reference_id', $transfer->id)->first();
    $movIn = StockMovement::where('type', 'entree')->where('reference_id', $transfer->id)->first();
    expect($movOut->lot_number)->toBe('LOT-TRANSFER-001');
    expect($movOut->stock_lot_id)->not->toBeNull();
    expect($movIn->lot_number)->toBe('LOT-TRANSFER-001');
    expect($movIn->stock_lot_id)->toBe($destLot->id);
});

// ═══ T2 — transfert complet : convention "reste à 0", pas de suppression ═══

it('T2 — transfert complet (300→300) : source reste à 0 (non supprimée), destination = 300', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T2');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    $sourceLot = StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-FULL-002', 'quantity' => 300, 'unit_cost' => 700]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 300, 'lot_number' => 'LOT-FULL-002']],
    ]);
    app(StockTransferService::class)->ship($transfer);
    app(StockTransferService::class)->receive($transfer->fresh());

    expect(sltStockQty($product->id, $a->id))->toBe(0.0);
    expect(sltStockQty($product->id, $b->id))->toBe(300.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-FULL-002'))->toBe(0.0);
    expect(sltLotQty($product->id, $b->id, 'LOT-FULL-002'))->toBe(300.0);

    // Convention : la ligne source N'EST PAS supprimée (cohérent avec ProductStock,
    // qui ne supprime jamais non plus une ligne à 0 — préserve la traçabilité et
    // les FK stock_movements.stock_lot_id existantes pointant vers cette ligne).
    expect(StockLot::where('id', $sourceLot->id)->exists())->toBeTrue();
});

// ═══ T11 — annulation APRÈS ship() mais AVANT receive() : restaure le lot source, aucun crédit destination ═══

it('T11 — cancel() après ship() : ProductStock et StockLot source restaurés, aucun crédit destination', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T11');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-CANCEL', 'quantity' => 300, 'unit_cost' => 450]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 100, 'lot_number' => 'LOT-CANCEL']],
    ]);
    app(StockTransferService::class)->ship($transfer);

    // Après ship() seul (avant receive()) : source débitée, rien encore côté B.
    expect(sltStockQty($product->id, $a->id))->toBe(200.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-CANCEL'))->toBe(200.0);
    expect(StockLot::where('product_id', $product->id)->where('warehouse_id', $b->id)->exists())->toBeFalse();

    app(StockTransferService::class)->cancel($transfer->fresh(), 'Test T11 annulation après expédition');

    // Restauration intégrale côté source, aucun crédit destination créé.
    expect(sltStockQty($product->id, $a->id))->toBe(300.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-CANCEL'))->toBe(300.0);
    expect(sltStockQty($product->id, $b->id))->toBe(0.0);
    expect(StockLot::where('product_id', $product->id)->where('warehouse_id', $b->id)->exists())->toBeFalse();
    expect($transfer->fresh()->status)->toBe('annule');
});

// ═══ T3 — lot destination déjà existant : cumule, ne duplique jamais ═══

it('T3 — lot destination déjà existant (A=200,B=50) : transfert 100 → A=100, B=150, pas de doublon', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T3');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 200, 'reserved_quantity' => 0]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $b->id, 'quantity' => 50, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-X', 'quantity' => 200, 'unit_cost' => 300]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $b->id, 'lot_number' => 'LOT-X', 'quantity' => 50, 'unit_cost' => 300]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 100, 'lot_number' => 'LOT-X']],
    ]);
    app(StockTransferService::class)->ship($transfer);
    app(StockTransferService::class)->receive($transfer->fresh());

    expect(sltLotQty($product->id, $a->id, 'LOT-X'))->toBe(100.0);
    expect(sltLotQty($product->id, $b->id, 'LOT-X'))->toBe(150.0);
    expect(StockLot::where('product_id', $product->id)->where('warehouse_id', $b->id)->where('lot_number', 'LOT-X')->count())->toBe(1);
});

// ═══ T5 — sur-transfert : BLOCK, zéro effet de bord ═══

it('T5 — sur-transfert (301 depuis 300) : bloqué, aucun effet de bord', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T5');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-OVER', 'quantity' => 300, 'unit_cost' => 400]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 301, 'lot_number' => 'LOT-OVER']],
    ]);

    $movBefore = StockMovement::count();
    expect(fn () => app(StockTransferService::class)->ship($transfer))->toThrow(\RuntimeException::class);

    expect(sltStockQty($product->id, $a->id))->toBe(300.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-OVER'))->toBe(300.0);
    expect(StockMovement::count())->toBe($movBefore);
    expect(StockLot::where('product_id', $product->id)->where('warehouse_id', $b->id)->exists())->toBeFalse();
    expect($transfer->fresh()->status)->toBe('brouillon');
});

// ═══ T6 — lot source inconnu : BLOCK, aucune substitution automatique ═══

it('T6 — lot source inconnu : bloqué, aucun autre lot substitué', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T6');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    // Aucun StockLot créé pour LOT-GHOST — le produit a du stock global mais pas ce lot précis.

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 50, 'lot_number' => 'LOT-GHOST']],
    ]);

    expect(fn () => app(StockTransferService::class)->ship($transfer))->toThrow(\RuntimeException::class);
    expect(sltStockQty($product->id, $a->id))->toBe(300.0);
});

// ═══ T7 — lot d'un autre article : BLOCK ═══

it('T7 — lot appartenant à un autre article : bloqué', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T7');
    $productX = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);
    $productY = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $productX->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    // LOT-Y-ONLY appartient à productY, pas productX.
    StockLot::create(['product_id' => $productY->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-Y-ONLY', 'quantity' => 300, 'unit_cost' => 200]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $productX->id, 'quantity' => 50, 'lot_number' => 'LOT-Y-ONLY']],
    ]);

    expect(fn () => app(StockTransferService::class)->ship($transfer))->toThrow(\RuntimeException::class);
    expect(sltLotQty($productY->id, $a->id, 'LOT-Y-ONLY'))->toBe(300.0);
});

// ═══ T8 — lot d'un autre dépôt (pas le dépôt source) : BLOCK ═══

it('T8 — lot appartenant à un autre dépôt que le dépôt source déclaré : bloqué', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T8');
    $c = Warehouse::firstOrCreate(['code' => 'SLT-C-T8'], ['name' => 'Dépôt C', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    // LOT-ELSEWHERE existe au dépôt C, pas au dépôt A (source déclarée du transfert).
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $c->id, 'lot_number' => 'LOT-ELSEWHERE', 'quantity' => 300, 'unit_cost' => 250]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 50, 'lot_number' => 'LOT-ELSEWHERE']],
    ]);

    expect(fn () => app(StockTransferService::class)->ship($transfer))->toThrow(\RuntimeException::class);
    expect(sltLotQty($product->id, $c->id, 'LOT-ELSEWHERE'))->toBe(300.0);
});

// ═══ T9 — article NON loté : comportement existant inchangé ═══

it('T9 — article non loté : transfert fonctionne comme avant, stock_lots jamais touché', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T9');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => false]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 150, 'reserved_quantity' => 0]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 60]], // pas de lot_number
    ]);
    app(StockTransferService::class)->ship($transfer);
    app(StockTransferService::class)->receive($transfer->fresh());

    expect(sltStockQty($product->id, $a->id))->toBe(90.0);
    expect(sltStockQty($product->id, $b->id))->toBe(60.0);
    expect(StockLot::where('product_id', $product->id)->exists())->toBeFalse();
});

// ═══ T44 — transfert aller-retour : même lot, deux transferts successifs ═══

it('§44 — transfert retour (A→B=100 puis B→A=40) : A=240, B=60, même lot métier', function () {
    $co = sltSociete();
    [$a, $b] = sltWarehouses($co, '-T44');
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-ROUNDTRIP', 'quantity' => 300, 'unit_cost' => 600]);

    $t1 = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 100, 'lot_number' => 'LOT-ROUNDTRIP']],
    ]);
    app(StockTransferService::class)->ship($t1);
    app(StockTransferService::class)->receive($t1->fresh());

    $t2 = app(StockTransferService::class)->create([
        'from_warehouse_id' => $b->id, 'to_warehouse_id' => $a->id,
        'items' => [['product_id' => $product->id, 'quantity' => 40, 'lot_number' => 'LOT-ROUNDTRIP']],
    ]);
    app(StockTransferService::class)->ship($t2);
    app(StockTransferService::class)->receive($t2->fresh());

    expect(sltStockQty($product->id, $a->id))->toBe(240.0);
    expect(sltStockQty($product->id, $b->id))->toBe(60.0);
    expect(sltLotQty($product->id, $a->id, 'LOT-ROUNDTRIP'))->toBe(240.0);
    expect(sltLotQty($product->id, $b->id, 'LOT-ROUNDTRIP'))->toBe(60.0);
    expect(sltStockQty($product->id, $a->id) + sltStockQty($product->id, $b->id))->toBe(300.0);
});

// ═══ T10 — non-régression 581d16f / P1-C : réception fournisseur intacte ═══

it('T10 — non-régression 581d16f/P1-C : réception fournisseur (1000+500) toujours cohérente après fix P1-F', function () {
    $co = sltSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'SLT-PO-T10'], ['name' => 'Dépôt PO', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
    $supplier = \App\Models\Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur SLT', 'code' => 'FSLT10']);
    $cat = \App\Models\ItemCategory::firstOrCreate(['code' => 'SLT_BOBINE'], ['name' => 'SLT Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $bobine = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);

    $po = \App\Models\PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id, 'number' => 'PO-SLT10-' . uniqid(),
        'status' => 'confirme', 'currency_code' => 'XOF', 'issued_at' => now(), 'ordered_at' => now(),
    ]);
    $po->items()->create([
        'product_id' => $bobine->id, 'description' => $bobine->name,
        'quantity' => 1500, 'unit_price' => 500, 'line_total_ht' => 750000, 'line_tax' => 0,
        'line_total_ttc' => 750000, 'received_quantity' => 0,
    ]);

    $rec1 = app(\App\Services\PurchaseOrderService::class)->createReception($po->fresh());
    app(\App\Services\PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000, 'lot_number' => 'LOT-SLT10-001']]);
    $rec2 = app(\App\Services\PurchaseOrderService::class)->createReception($po->fresh());
    expect((float) $rec2->items()->first()->received_quantity)->toBe(500.0);
    app(\App\Services\PurchaseReceptionService::class)->validate($rec2, $wh->id, [$rec2->items()->first()->id => ['received_quantity' => 500, 'lot_number' => 'LOT-SLT10-002']]);

    $ps = sltStockQty($bobine->id, $wh->id);
    $sumLots = (float) StockLot::where('product_id', $bobine->id)->sum('quantity');
    $sumCoils = (float) \App\Modules\Production\Models\Coil::where('product_id', $bobine->id)->sum('remaining_weight');

    expect($ps)->toBe(1500.0);
    expect($sumLots)->toBe(1500.0);
    expect($sumCoils)->toBe(1500.0);
});
