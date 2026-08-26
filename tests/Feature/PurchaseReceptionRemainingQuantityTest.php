<?php

/**
 * [FIX P1-C — reliquat de réception fournisseur] createReception() préremplissait
 * TOUJOURS la quantité totale commandée sur la nouvelle réception, jamais le
 * reliquat réel (commandé − déjà validé) — un PO de 1500kg avec 1000kg déjà
 * reçus proposait encore 1500kg pour la réception suivante.
 *
 * Correctif (2 fichiers) :
 *  - PurchaseOrderService::createReception() : préremplit avec
 *    max(0, quantity - received_quantity), plus la quantité totale.
 *  - PurchaseReceptionService::validate() : verrouille PurchaseOrderItem
 *    (lockForUpdate, protection concurrence) et REFUSE explicitement
 *    (RuntimeException) toute quantité soumise dépassant le reliquat réel —
 *    remplace le plafonnement silencieux min() qui absorbait l'excédent
 *    sans jamais avertir personne.
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

function prqSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PRQ'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PRQ Co'], ['email' => 'prq@prq.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function prqWarehouse(Company $co, string $code = 'PRQ-A'): Warehouse
{
    return Warehouse::firstOrCreate(['code' => $code], ['name' => 'Dépôt ' . $code, 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
}

function prqPO(Company $co, Supplier $supplier, array $lignes): PurchaseOrder
{
    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id, 'number' => 'PO-PRQ-' . uniqid(),
        'status' => 'confirme', 'currency_code' => 'XOF', 'issued_at' => now(), 'ordered_at' => now(),
    ]);
    foreach ($lignes as $l) {
        $po->items()->create([
            'product_id' => $l['product']->id, 'description' => $l['product']->name,
            'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'] ?? 500,
            'line_total_ht' => $l['quantity'] * ($l['unit_price'] ?? 500), 'line_tax' => 0,
            'line_total_ttc' => $l['quantity'] * ($l['unit_price'] ?? 500),
            'received_quantity' => 0,
        ]);
    }

    return $po;
}

// ═══ T1/T2/T3 — préremplissage reflète le reliquat, pas la quantité totale ═══

it('T1 — première réception : reliquat = quantité totale commandée', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ1']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);

    $rec = app(PurchaseOrderService::class)->createReception($po->fresh());
    expect((float) $rec->items()->first()->received_quantity)->toBe(1500.0);
});

it('T2 — RÉGRESSION PRINCIPALE : après R1=1000 validée, la 2e réception propose le RELIQUAT (500), pas la quantité totale (1500)', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ2']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [
        $rec1->items()->first()->id => ['received_quantity' => 1000],
    ]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $prefill = (float) $rec2->items()->first()->received_quantity;

    expect($prefill)->toBe(500.0)->not->toBe(1500.0);
});

it('T3 — après réception complète (1000+500), le PO passe « reçu » et ne propose plus de nouvelle réception', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ3']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000]]);
    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [$rec2->items()->first()->id => ['received_quantity' => 500]]);

    expect($po->fresh()->status)->toBe('recu');
    expect(fn () => app(PurchaseOrderService::class)->createReception($po->fresh()))->toThrow(\RuntimeException::class);
});

// ═══ T4/T5/T6 — protection backend anti-sur-réception ═══

it('T4 — refuse une réception qui dépasse le reliquat (reliquat 500, tentative 501)', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ4']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);
    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000]]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item2 = $rec2->items()->first();

    $movementsAvant = \App\Models\StockMovement::where('product_id', $article->id)->count();
    $stockAvant     = (float) ProductStock::where('product_id', $article->id)->where('warehouse_id', $wh->id)->value('quantity');
    expect(fn () => app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [$item2->id => ['received_quantity' => 501]]))
        ->toThrow(\RuntimeException::class);

    // T11 — AUCUNE écriture métier : ni mouvement de stock, ni cumul PO,
    // ni statut réception, ni quantité en stock. Le backend refuse AVANT
    // toute écriture (le throw se produit dans la Passe 1, avant la
    // Passe 2 qui génère les mouvements de stock).
    expect(\App\Models\StockMovement::where('product_id', $article->id)->count())->toBe($movementsAvant);
    expect((float) $po->fresh()->items()->first()->received_quantity)->toBe(1000.0);
    expect($rec2->fresh()->status)->toBe('brouillon');
    expect((float) ProductStock::where('product_id', $article->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe($stockAvant);
    // Article non coil-managed dans ce test : stock_lots / coils sans objet (N/A),
    // couverts séparément par T8 pour le cas coil-managed.
});

it('T5 — accepte une réception qui consomme exactement le reliquat (500)', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ5']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);
    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000]]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [$rec2->items()->first()->id => ['received_quantity' => 500]]);

    expect((float) $po->fresh()->items()->first()->received_quantity)->toBe(1500.0);
});

it('T6 — accepte une réception volontairement inférieure au reliquat (300 sur 500), remaining après = 200', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ6']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);
    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000]]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [$rec2->items()->first()->id => ['received_quantity' => 300]]);

    expect((float) $po->fresh()->items()->first()->received_quantity)->toBe(1300.0);

    $rec3 = app(PurchaseOrderService::class)->createReception($po->fresh());
    expect((float) $rec3->items()->first()->received_quantity)->toBe(200.0);
});

// ═══ §16 — réceptions multiples ═══

it('§16 — 3 réceptions successives (400+600+500) épuisent exactement le reliquat à chaque étape', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ7']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 400]]);
    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    expect((float) $rec2->items()->first()->received_quantity)->toBe(1100.0);
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [$rec2->items()->first()->id => ['received_quantity' => 600]]);

    $rec3 = app(PurchaseOrderService::class)->createReception($po->fresh());
    expect((float) $rec3->items()->first()->received_quantity)->toBe(500.0);
    app(PurchaseReceptionService::class)->validate($rec3, $wh->id, [$rec3->items()->first()->id => ['received_quantity' => 500]]);

    expect($po->fresh()->status)->toBe('recu');
    expect((float) $po->fresh()->items()->first()->received_quantity)->toBe(1500.0);
});

// ═══ T7 — multi-ligne PO, reliquats indépendants ═══

it('T7 — PO multi-lignes : chaque article a son propre reliquat, préremplis indépendamment', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ8']);
    $articleA = Product::factory()->create(['is_stockable' => true, 'name' => 'Article A']);
    $articleB = Product::factory()->create(['is_stockable' => true, 'name' => 'Article B']);
    $po = prqPO($co, $supplier, [
        ['product' => $articleA, 'quantity' => 1500],
        ['product' => $articleB, 'quantity' => 800],
    ]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $itemA1 = $rec1->items()->where('product_id', $articleA->id)->first();
    $itemB1 = $rec1->items()->where('product_id', $articleB->id)->first();
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [
        $itemA1->id => ['received_quantity' => 1000],
        $itemB1->id => ['received_quantity' => 200],
    ]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $itemA2 = $rec2->items()->where('product_id', $articleA->id)->first();
    $itemB2 = $rec2->items()->where('product_id', $articleB->id)->first();

    expect((float) $itemA2->received_quantity)->toBe(500.0);
    expect((float) $itemB2->received_quantity)->toBe(600.0);
});

// ═══ T8 — article coil-managed : P1-C fonctionne + 581d16f non cassé ═══

it('T8 — article coil-managed : reliquat correct ET invariant stock_lots préservé (non-régression 581d16f)', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ9']);
    $cat = ItemCategory::firstOrCreate(['code' => 'PRQ_BOBINE'], ['name' => 'PRQ Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $bobine = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    $po = prqPO($co, $supplier, [['product' => $bobine, 'quantity' => 1500]]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item1 = $rec1->items()->first();
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$item1->id => ['received_quantity' => 1000, 'lot_number' => 'LOT-PRQ-001']]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item2 = $rec2->items()->first();
    // P1-C : reliquat correctement proposé pour un article coil-managed aussi.
    expect((float) $item2->received_quantity)->toBe(500.0);
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [$item2->id => ['received_quantity' => 500, 'lot_number' => 'LOT-PRQ-002']]);

    // 581d16f : toujours aucun double crédit après ce nouveau flux de reliquat.
    $ps = (float) ProductStock::where('product_id', $bobine->id)->where('warehouse_id', $wh->id)->value('quantity');
    $sumLots = (float) StockLot::where('product_id', $bobine->id)->sum('quantity');
    $sumCoils = (float) Coil::where('product_id', $bobine->id)->sum('remaining_weight');

    expect($ps)->toBe(1500.0);
    expect($sumLots)->toBe(1500.0);
    expect($sumCoils)->toBe(1500.0);
});

// ═══ §20 — annulation de réception restaure le reliquat ═══

it('§20 — annuler une réception validée restaure le reliquat (mécanisme cancelReception existant)', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ10']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000]]);
    expect((float) $po->fresh()->items()->first()->received_quantity)->toBe(1000.0);
    expect((float) ProductStock::where('product_id', $article->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(1000.0);

    app(PurchaseOrderService::class)->cancelReception($rec1->fresh(), 'Test annulation P1-C');

    // T9 — l'annulation (mécanisme cancelReception EXISTANT, non modifié par
    // P1-C) restaure le reliquat ET contre-passe le stock physique.
    expect((float) $po->fresh()->items()->first()->received_quantity)->toBe(0.0);
    expect((float) ProductStock::where('product_id', $article->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(0.0);
    $recAfterCancel = app(PurchaseOrderService::class)->createReception($po->fresh());
    expect((float) $recAfterCancel->items()->first()->received_quantity)->toBe(1500.0);
});

// ═══ T-UI — le formulaire affiche Commandé / Déjà reçu / Reste à recevoir / Cette réception ═══

it('T-UI — la page de réception affiche commandé, déjà reçu et le reliquat, pas seulement la quantité totale', function () {
    $co = prqSociete();
    $wh = prqWarehouse($co);
    $supplier = Supplier::create(['company_id' => $co->id, 'name' => 'Fournisseur PRQ', 'code' => 'FPRQ11']);
    $article = Product::factory()->create(['is_stockable' => true]);
    $po = prqPO($co, $supplier, [['product' => $article, 'quantity' => 1500]]);

    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [$rec1->items()->first()->id => ['received_quantity' => 1000]]);

    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());

    $response = test()->get(route('achats.receptions.show', $rec2));
    $response->assertOk();
    $response->assertSee('Commandé');
    $response->assertSee('Déjà reçu');
    $response->assertSee('Reste à recevoir');
    $response->assertSee('Cette réception');
    // Commandé = 1 500,00 ; Déjà reçu = 1 000,00 ; Reste à recevoir = 500,00
    $response->assertSee('1 500,00');
    $response->assertSee('1 000,00');
    $response->assertSee('500,00');
});
