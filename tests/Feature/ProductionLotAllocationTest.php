<?php

/**
 * [FIX P1-D — allocation formelle des matières par lot/bobine avant consommation]
 *
 * P1-D1 : StockReservation étendue (stock_lot_id, coil_id, consumed_quantity) —
 * une allocation économique unique peut se ventiler en plusieurs lignes
 * physiques (plusieurs lots/bobines pour le même besoin), jamais deux niveaux
 * de réservation indépendants pour la même matière.
 *
 * P1-D2 : CoilConsumptionService::consume() est fail-closed pour toute matière
 * RÉELLEMENT coil-managed (itemCategory.coil_managed) — consommer une bobine
 * sans allocation active (production_order_id + coil_id) est refusé. Chaque
 * consommation réduit consumed_quantity de l'allocation correspondante
 * (jamais la ligne entière si consommation partielle) et répercute la baisse
 * sur product_stocks.reserved_quantity via ReservationService::adjustReserved()
 * — propriétaire unique, aucun second chemin d'écriture.
 *
 * Root cause (rappel) : avant ce fix, reserveMaterialsForOrder() réservait au
 * niveau produit+dépôt (jamais lot/bobine), et CoilConsumptionService::consume()
 * ne libérait JAMAIS cette réservation — réservation orpheline, disponible
 * pouvant devenir négatif.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ReservationService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function plaSociete(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PLA'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PLA Co'], ['email' => 'pla@pla.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

/** @return array{co:Company,wh:Warehouse,mp:Product,pf:Product,order:ProductionOrder,lotA:StockLot,coilA:Coil,lotB:StockLot,coilB:Coil} */
function plaScenario(int $qtyRequested = 1200, float $lotAQty = 1000, float $lotBQty = 500, string $suffix = ''): array
{
    $co = plaSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'PLA-MP' . $suffix], ['name' => 'Dépôt Matière', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $cat = ItemCategory::firstOrCreate(['code' => 'PLA_BOBINE' . $suffix], ['name' => 'PLA Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    $pf = Product::factory()->create(['is_manufacturable' => true, 'is_sellable' => true]);

    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => $lotAQty + $lotBQty, 'reserved_quantity' => 0]);
    $lotA = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-A' . $suffix, 'quantity' => $lotAQty, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coilA = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lotA->id, 'reference' => 'COIL-A' . $suffix, 'initial_weight' => $lotAQty, 'remaining_weight' => $lotAQty, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);
    $lotB = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-B' . $suffix, 'quantity' => $lotBQty, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coilB = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lotB->id, 'reference' => 'COIL-B' . $suffix, 'initial_weight' => $lotBQty, 'remaining_weight' => $lotBQty, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM PLA' . $suffix, 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1, 'waste_rate' => 0, 'depot_sortie_id' => $wh->id]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'number' => 'OF-PLA' . $suffix . '-' . uniqid(),
        'product_id' => $pf->id, 'bill_of_material_id' => $bom->id,
        'quantity_requested' => $qtyRequested, 'status' => 'brouillon',
        'depot_matiere_id' => $wh->id,
    ]);

    return compact('co', 'wh', 'mp', 'pf', 'order', 'lotA', 'coilA', 'lotB', 'coilB');
}

function plaStockSums(Product $mp, Warehouse $wh): array
{
    return [
        'physical' => (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('quantity'),
        'reserved' => (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'),
        'sum_lots' => (float) StockLot::where('product_id', $mp->id)->sum('quantity'),
        'sum_coils' => (float) Coil::where('product_id', $mp->id)->sum('remaining_weight'),
    ];
}

// ═══ T-FAIL-CLOSED — preuve permanente du contrat P1-D2 ═══

it('T-FAIL-CLOSED — consommer une bobine coil-managed sans allocation active est bloqué, zéro effet de bord', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'coilA' => $coil] = plaScenario();
    $order->update(['status' => 'en_cours']);

    $movBefore = \App\Models\StockMovement::count();
    $resBefore = StockReservation::count();

    expect(fn () => app(CoilConsumptionService::class)->consume($order, $coil, 100))
        ->toThrow(ValidationException::class);

    expect((float) $coil->fresh()->remaining_weight)->toBe(1000.0)
        ->and(\App\Models\StockMovement::count())->toBe($movBefore)
        ->and(StockReservation::count())->toBe($resBefore)
        ->and(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 0.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);
});

// ═══ T1/T8 — allocation simple, aucune sortie physique ═══

it('T1/T8 — allocation formelle : aucune baisse de stock physique, reserved augmente', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario();

    $reservation = app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);

    expect($reservation->status)->toBe('reserved')
        ->and((float) $reservation->quantity)->toBe(1000.0)
        ->and((float) $reservation->consumed_quantity)->toBe(0.0)
        ->and(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 1000.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);
});

// ═══ T2 — scénario multi-lot central du prompt ═══

it('T2 — scénario multi-lot central : besoin 1200 = 1000(A)+200(B), physique inchangé, reserved=1200', function () {
    $s = plaScenario();
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA, 'lotB' => $lotB, 'coilB' => $coilB] = $s;

    expect(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 0.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);

    $allocA = app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    $allocB = app(ReservationService::class)->allocateMaterialLot($order, $lotB, 200, $coilB);

    expect((float) $allocA->quantity)->toBe(1000.0)->and((float) $allocB->quantity)->toBe(200.0);
    expect(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 1200.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);
    // Une seule réservation économique par lot, jamais de réservation générique produit+dépôt en plus.
    expect(StockReservation::where('production_order_id', $order->id)->where('product_id', $mp->id)->count())->toBe(2);

    // ═══ T-CONSUME — consommation totale : physique 300, reserved 0 ═══
    $order->update(['status' => 'en_cours']);
    app(CoilConsumptionService::class)->consume($order, $coilA->fresh(), 1000);
    app(CoilConsumptionService::class)->consume($order, $coilB->fresh(), 200);

    expect(plaStockSums($mp, $wh))->toBe(['physical' => 300.0, 'reserved' => 0.0, 'sum_lots' => 300.0, 'sum_coils' => 300.0]);
    expect($allocA->fresh()->status)->toBe('consumed')->and($allocB->fresh()->status)->toBe('consumed');
});

// ═══ T4/T5/T6 — validations avant allocation ═══

it('T4 — sur-allocation bloquée : lot 1000, OF1 alloue 800, OF2 tente 300 → BLOCK', function () {
    $s = plaScenario();
    $of1 = $s['order'];
    ['co' => $co, 'wh' => $wh, 'mp' => $mp, 'lotA' => $lotA, 'coilA' => $coilA] = $s;
    $of2 = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-PLA-2-' . uniqid(), 'product_id' => $s['pf']->id, 'quantity_requested' => 300, 'status' => 'brouillon', 'depot_matiere_id' => $wh->id]);

    app(ReservationService::class)->allocateMaterialLot($of1, $lotA, 800, $coilA);

    expect(fn () => app(ReservationService::class)->allocateMaterialLot($of2, $lotA->fresh(), 300, $coilA->fresh()))
        ->toThrow(ValidationException::class);

    expect(plaStockSums($mp, $wh)['reserved'])->toBe(800.0);
    expect(StockReservation::where('production_order_id', $of2->id)->exists())->toBeFalse();
});

it('T5 — lot d’un autre article bloqué', function () {
    $s = plaScenario();
    ['order' => $order, 'lotA' => $lotA, 'wh' => $wh, 'co' => $co] = $s;
    $autreArticle = Product::factory()->create(['is_stockable' => true]);
    $lotEtranger = StockLot::create(['product_id' => $autreArticle->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-ETRANGER', 'quantity' => 100, 'unit_cost' => 100, 'status' => 'disponible']);

    expect(fn () => app(ReservationService::class)->allocateMaterialLot($order, $lotEtranger, 50))
        ->toThrow(ValidationException::class);
});

it('T6 — lot d’un autre dépôt que celui attendu par l’OF bloqué', function () {
    $s = plaScenario();
    ['order' => $order, 'mp' => $mp, 'co' => $co] = $s;
    $ailleurs = Warehouse::firstOrCreate(['code' => 'PLA-AILLEURS'], ['name' => 'Ailleurs', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $lotAilleurs = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $ailleurs->id, 'lot_number' => 'LOT-AILLEURS', 'quantity' => 100, 'unit_cost' => 500, 'status' => 'disponible']);

    expect(fn () => app(ReservationService::class)->allocateMaterialLot($order, $lotAilleurs, 50))
        ->toThrow(ValidationException::class);
});

it('T7 — bobine bloquée qualité ne peut pas être allouée', function () {
    $s = plaScenario();
    ['order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = $s;
    $coilA->update(['quality_status' => Coil::QUALITY_QUARANTINED]);

    expect(fn () => app(ReservationService::class)->allocateMaterialLot($order, $lotA->fresh(), 100, $coilA->fresh()))
        ->toThrow(ValidationException::class);
});

// ═══ T9/T10 — consommation partielle, reste réservé exact ═══

it('T9/T10 — consommation partielle (700 sur 1000) : reste réservé 300, reserved_quantity -=700 seulement', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario();
    $alloc = app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    $order->update(['status' => 'en_cours']);

    app(CoilConsumptionService::class)->consume($order, $coilA->fresh(), 700);

    $alloc->refresh();
    expect((float) $alloc->quantity)->toBe(1000.0)
        ->and((float) $alloc->consumed_quantity)->toBe(700.0)
        ->and($alloc->remainingReserved())->toBe(300.0)
        ->and($alloc->status)->toBe('reserved'); // pas encore consumed : reste 300

    expect(plaStockSums($mp, $wh))->toBe(['physical' => 800.0, 'reserved' => 300.0, 'sum_lots' => 800.0, 'sum_coils' => 800.0]);
});

// ═══ T13 — consommation supérieure à l'allocation restante : BLOCK, zéro effet de bord ═══

it('T13 — consommer plus que le reste alloué (301 sur 300 restant) est bloqué sans effet de bord', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario();
    app(ReservationService::class)->allocateMaterialLot($order, $lotA, 300, $coilA);
    $order->update(['status' => 'en_cours']);

    $movBefore = \App\Models\StockMovement::count();
    expect(fn () => app(CoilConsumptionService::class)->consume($order, $coilA->fresh(), 301))
        ->toThrow(ValidationException::class);

    expect(\App\Models\StockMovement::count())->toBe($movBefore);
    expect(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 300.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);
});

// ═══ T11 — désallocation restaure la disponibilité, physique inchangé ═══

it('T11 — désallocation (release) restaure reserved à 0, physique inchangé', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario();
    $alloc = app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    expect(plaStockSums($mp, $wh)['reserved'])->toBe(1000.0);

    app(ReservationService::class)->release($alloc->fresh());

    expect($alloc->fresh()->status)->toBe('released');
    expect(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 0.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);
});

// ═══ T15 — annulation OF avant consommation : reserved revient à 0 ═══

it('T15 — annulation OF avant toute consommation : reserved=0, physique inchangé, réservations released', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario();
    app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    expect(plaStockSums($mp, $wh)['reserved'])->toBe(1000.0);

    app(ProductionService::class)->cancel($order->fresh(), 'Test P1-D annulation avant consommation');

    expect(plaStockSums($mp, $wh))->toBe(['physical' => 1500.0, 'reserved' => 0.0, 'sum_lots' => 1500.0, 'sum_coils' => 1500.0]);
    expect(StockReservation::where('production_order_id', $order->id)->where('status', 'reserved')->count())->toBe(0);
});

// ═══ T16 — annulation OF après consommation partielle : seul le reste est libéré ═══

it('T16 — annulation après consommation partielle (700/1000 consommé) : seuls les 300 restants sont libérés, le consommé reste consommé', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario();
    $alloc = app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    $order->update(['status' => 'en_cours']);
    app(CoilConsumptionService::class)->consume($order, $coilA->fresh(), 700);

    expect(plaStockSums($mp, $wh))->toBe(['physical' => 800.0, 'reserved' => 300.0, 'sum_lots' => 800.0, 'sum_coils' => 800.0]);

    // [Note] ProductionService::cancel() refuse tout OF portant une consommation
    // matière déjà engagée (garde métier légitime et PRÉEXISTANTE, sans rapport
    // avec P1-D : « extournez d'abord, ou clôturez avec écart assumé »). Le test
    // cible ici précisément le comportement de libération d'une allocation
    // partiellement consommée — releaseForProductionOrder() est le mécanisme que
    // cancel() appelle une fois SES PROPRES gardes passées ; l'appeler directement
    // isole exactement ce que P1-D2 doit garantir.
    app(ReservationService::class)->releaseForProductionOrder($order->fresh());

    // La consommation physique déjà réalisée N'EST PAS annulée par le release.
    expect(plaStockSums($mp, $wh))->toBe(['physical' => 800.0, 'reserved' => 0.0, 'sum_lots' => 800.0, 'sum_coils' => 800.0]);
    expect($alloc->fresh()->status)->toBe('released')
        ->and((float) $alloc->fresh()->consumed_quantity)->toBe(700.0);
});

// ═══ T17 — finish() après consommation complète ne double-libère pas ═══

it('T17 — finish() après consommation complète : allocation déjà « consumed », aucune double décrémentation', function () {
    ['wh' => $wh, 'mp' => $mp, 'order' => $order, 'lotA' => $lotA, 'coilA' => $coilA] = plaScenario(1000);
    app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    $order->update(['status' => 'en_cours', 'quantity_produced' => 1000]);
    app(CoilConsumptionService::class)->consume($order, $coilA->fresh(), 1000);

    expect(plaStockSums($mp, $wh))->toBe(['physical' => 500.0, 'reserved' => 0.0, 'sum_lots' => 500.0, 'sum_coils' => 500.0]);

    // [Note] force=true : contourne la garde « contrôle qualité obligatoire »,
    // garde métier PRÉEXISTANTE sans rapport avec P1-D. Seul le comportement de
    // libération (releaseMaterialReservations) est testé ici.
    app(ProductionService::class)->finish($order->fresh(), true);

    // reserved déjà à 0 avant finish() (consommation totale) : finish() ne doit
    // rien décrémenter de plus (releaseMaterialReservations ne trouve aucune
    // ligne 'reserved' pour ce produit — déjà 'consumed').
    expect(plaStockSums($mp, $wh)['reserved'])->toBe(0.0);
});

// ═══ T18 — régression : composant non loté, comportement historique inchangé ═══

it('T18 — régression non-loté : reserveMaterialsForOrder() continue de réserver au niveau produit+dépôt', function () {
    $co = plaSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'PLA-NONLOT'], ['name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => false]);
    $pf = Product::factory()->create(['is_manufacturable' => true]);
    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => 500, 'reserved_quantity' => 0]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM NONLOT', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1, 'waste_rate' => 0, 'depot_sortie_id' => $wh->id]);
    $order = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-NONLOT-' . uniqid(), 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 200, 'status' => 'brouillon']);

    $reserved = app(ReservationService::class)->reserveMaterialsForOrder($order->fresh());

    expect($reserved)->toBe(200.0);
    expect((float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'))->toBe(200.0);
    expect(StockReservation::where('production_order_id', $order->id)->where('product_id', $mp->id)->whereNull('stock_lot_id')->exists())->toBeTrue();
});

// ═══ T19 — régression réservation vente ═══

it('T19 — régression vente : reserveStockForOrder() et release() fonctionnent identiquement (stock_lot_id/coil_id restent NULL)', function () {
    $co = plaSociete();
    $wh = Warehouse::firstOrCreate(['code' => 'PLA-VENTE'], ['name' => 'Dépôt vente', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true, 'is_default' => true]);
    $pf = Product::factory()->create(['is_stockable' => true, 'is_sellable' => true]);
    ProductStock::create(['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    $client = \App\Models\Client::factory()->create();
    $order = \App\Models\Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-PLA-' . uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'subtotal_ht' => 0, 'total_ttc' => 0,
    ]);
    $order->items()->create(['product_id' => $pf->id, 'description' => 'PF', 'quantity' => 100, 'delivered_quantity' => 0, 'unit_price' => 1000, 'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000]);

    $reserved = app(ReservationService::class)->reserveStockForOrder($order->fresh());

    expect($reserved)->toBeGreaterThan(0);
    $resa = StockReservation::where('order_id', $order->id)->first();
    expect($resa)->not->toBeNull()
        ->and($resa->stock_lot_id)->toBeNull()
        ->and($resa->coil_id)->toBeNull()
        ->and((float) $resa->consumed_quantity)->toBe(0.0);

    app(ReservationService::class)->release($resa);
    expect((float) ProductStock::where('product_id', $pf->id)->where('warehouse_id', $wh->id)->value('reserved_quantity'))->toBe(0.0);
});

// ═══ T20 — régression P1-F (transfert inter-dépôts) ═══

it('T20 — régression P1-F : transfert inter-dépôts d’un lot toujours fonctionnel après P1-D', function () {
    $co = plaSociete();
    $a = Warehouse::firstOrCreate(['code' => 'PLA-XFER-A'], ['name' => 'A', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $b = Warehouse::firstOrCreate(['code' => 'PLA-XFER-B'], ['name' => 'B', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-XFER-PLA', 'quantity' => 300, 'unit_cost' => 500]);

    $transfer = app(\App\Services\StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 100, 'lot_number' => 'LOT-XFER-PLA']],
    ]);
    app(\App\Services\StockTransferService::class)->ship($transfer);
    app(\App\Services\StockTransferService::class)->receive($transfer->fresh());

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $a->id)->value('quantity'))->toBe(200.0);
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $b->id)->value('quantity'))->toBe(100.0);
});

// ═══ T-CONCURRENCE structurelle (verrous) — preuve par lecture de code ═══
//
// Ordre de verrouillage vérifié par relecture (voir rapport final) :
//   allocateMaterialLot() : Coil (si fourni) PUIS StockLot — même ordre que
//   CoilConsumptionService::consume() (« Verrous : bobine puis lot »).
//   Disponibilité (coil.remaining_weight − SUM(remainingReserved actives))
//   recalculée APRÈS lockForUpdate(), jamais avant.
