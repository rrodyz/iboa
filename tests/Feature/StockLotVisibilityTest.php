<?php

/**
 * [FIX P1-E — consultation stock par lot / traçabilité / disponible réel]
 *
 * Couvre la page stocks/lots (StockController::lots() + StockLotQueryService) :
 * filtres (article, dépôt, lot/série, statut, qualité, disponibilité), KPI
 * agrégés indépendants de la pagination, réservé/disponible calculés depuis
 * stock_reservations (source canonique P1-D/P1-D3 — jamais stock_lots.
 * reserved_quantity, jamais recalculé depuis product_stocks), cohérence avec
 * P1-D (allocation/consommation réelles) et P1-F (transfert réel), isolation
 * société. Utilise systématiquement les VRAIS services métier
 * (allocateMaterialLot, CoilConsumptionService::consume, StockTransferService)
 * — jamais d'écriture directe des tables de réservation/mouvement.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ReservationService;
use App\Services\StockTransferService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function slvSociete(string $suffix): array
{
    $fy = FiscalYear::create(['label' => 'SLV'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'SLV Co'.$suffix, 'email' => 'slv'.$suffix.'@slv.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return [$co, $u];
}

/** Article + dépôt + catégorie coil-managed, prêts pour allocation/consommation P1-D réelles. */
function slvArticle(Company $co, string $suffix): array
{
    $wh = Warehouse::create(['code' => 'SLV-WH'.$suffix, 'name' => 'Dépôt A'.$suffix, 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $cat = ItemCategory::create(['code' => 'SLV_BOB'.$suffix, 'name' => 'SLV Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id, 'is_active' => true]);

    return [$wh, $mp];
}

it('E01 — page lots accessible avec permission stocks.view', function () {
    slvSociete('01');

    $response = test()->get(route('stocks.lots'));

    $response->assertOk();
    $response->assertSee('Lots');
});

it('E02/E04 — filtre product_id seul, puis combiné avec warehouse_id', function () {
    [$co] = slvSociete('02');
    [$whA, $mpA] = slvArticle($co, '02A');
    [$whB, $mpB] = slvArticle($co, '02B');

    StockLot::create(['product_id' => $mpA->id, 'warehouse_id' => $whA->id, 'lot_number' => 'LOT-A', 'quantity' => 100, 'unit_cost' => 500, 'status' => 'disponible']);
    StockLot::create(['product_id' => $mpB->id, 'warehouse_id' => $whB->id, 'lot_number' => 'LOT-B', 'quantity' => 200, 'unit_cost' => 700, 'status' => 'disponible']);

    $onlyA = test()->get(route('stocks.lots', ['product_id' => $mpA->id]));
    $onlyA->assertSee('LOT-A')->assertDontSee('LOT-B');

    $combined = test()->get(route('stocks.lots', ['product_id' => $mpA->id, 'warehouse_id' => $whA->id]));
    $combined->assertSee('LOT-A');

    $mismatched = test()->get(route('stocks.lots', ['product_id' => $mpA->id, 'warehouse_id' => $whB->id]));
    $mismatched->assertDontSee('LOT-A')->assertDontSee('LOT-B');
});

it('E05 — recherche par numéro de lot', function () {
    [$co] = slvSociete('05');
    [$wh, $mp] = slvArticle($co, '05');
    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-UNIQUE-XYZ', 'quantity' => 50, 'status' => 'disponible']);

    $response = test()->get(route('stocks.lots', ['search' => 'UNIQUE-XYZ']));

    $response->assertSee('LOT-UNIQUE-XYZ');
});

it('E07/E08 — lot épuisé invisible par défaut, retrouvable via filtre disponibilité "épuisés"', function () {
    [$co] = slvSociete('07');
    [$wh, $mp] = slvArticle($co, '07');
    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-VIDE', 'quantity' => 0, 'status' => 'consomme']);
    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-PLEIN', 'quantity' => 300, 'status' => 'disponible']);

    $withStock = test()->get(route('stocks.lots', ['availability' => 'with_stock']));
    $withStock->assertSee('LOT-PLEIN')->assertDontSee('LOT-VIDE');

    $exhausted = test()->get(route('stocks.lots', ['availability' => 'exhausted']));
    $exhausted->assertSee('LOT-VIDE')->assertDontSee('LOT-PLEIN');

    $all = test()->get(route('stocks.lots'));
    $all->assertSee('LOT-VIDE')->assertSee('LOT-PLEIN');
});

it('E18 — même numéro de lot sur 2 dépôts = 2 lignes distinctes, jamais fusionnées', function () {
    [$co] = slvSociete('18');
    [$whA, $mp] = slvArticle($co, '18A');
    $whB = Warehouse::create(['code' => 'SLV-WH18B', 'name' => 'Dépôt B18', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);

    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $whA->id, 'lot_number' => 'LOT-X', 'quantity' => 200, 'status' => 'disponible']);
    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $whB->id, 'lot_number' => 'LOT-X', 'quantity' => 100, 'status' => 'disponible']);

    $response = test()->get(route('stocks.lots', ['product_id' => $mp->id]));
    $response->assertOk();

    $lots = $response->viewData('lots');
    expect($lots->total())->toBe(2);
    expect((float) $lots->sum(fn ($l) => (float) $l->quantity))->toBe(300.0);
});

it('E20/E21/E09/E10/E11/E12/E13 — allocation + consommation réelles (P1-D) reflétées : réservé/disponible corrects à chaque étape', function () {
    [$co] = slvSociete('20');
    [$wh, $mp] = slvArticle($co, '20');
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => 1500, 'reserved_quantity' => 0]);
    $lotA = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-A20', 'quantity' => 1000, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coilA = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lotA->id, 'reference' => 'COIL-A20', 'initial_weight' => 1000, 'remaining_weight' => 1000, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);
    $lotB = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-B20', 'quantity' => 500, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coilB = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lotB->id, 'reference' => 'COIL-B20', 'initial_weight' => 500, 'remaining_weight' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM SLV20', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1, 'waste_rate' => 0, 'depot_sortie_id' => $wh->id]);
    $order = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-SLV20', 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 1200, 'status' => 'en_cours', 'depot_matiere_id' => $wh->id]);

    // --- Avant allocation ---
    $before = test()->get(route('stocks.lots', ['product_id' => $mp->id]))->viewData('kpi');
    expect($before['physical'])->toBe(1500.0)->and($before['reserved'])->toBe(0.0)->and($before['available'])->toBe(1500.0);

    // --- Allocation réelle (P1-D) ---
    app(ReservationService::class)->allocateMaterialLot($order, $lotA, 1000, $coilA);
    app(ReservationService::class)->allocateMaterialLot($order, $lotB, 200, $coilB);

    $afterAlloc = test()->get(route('stocks.lots', ['product_id' => $mp->id]));
    $kpiAlloc = $afterAlloc->viewData('kpi');
    expect($kpiAlloc['physical'])->toBe(1500.0)->and($kpiAlloc['reserved'])->toBe(1200.0)->and($kpiAlloc['available'])->toBe(300.0);

    $rowsAlloc = $afterAlloc->viewData('lots')->keyBy('lot_number');
    expect((float) $rowsAlloc['LOT-A20']->reserved_quantity)->toBe(1000.0);
    expect((float) $rowsAlloc['LOT-A20']->quantity - (float) $rowsAlloc['LOT-A20']->reserved_quantity)->toBe(0.0);
    expect((float) $rowsAlloc['LOT-B20']->reserved_quantity)->toBe(200.0);
    expect((float) $rowsAlloc['LOT-B20']->quantity - (float) $rowsAlloc['LOT-B20']->reserved_quantity)->toBe(300.0);

    // --- Consommation réelle (P1-D2) ---
    app(CoilConsumptionService::class)->consume($order, $coilA, 1000);
    app(CoilConsumptionService::class)->consume($order, $coilB, 200);

    $afterConsume = test()->get(route('stocks.lots', ['product_id' => $mp->id]));
    $kpiConsume = $afterConsume->viewData('kpi');
    expect($kpiConsume['reserved'])->toBe(0.0); // E21 : consommation totale → réservé revient à 0

    $rowsConsume = $afterConsume->viewData('lots')->keyBy('lot_number');
    expect((float) $rowsConsume['LOT-A20']->quantity)->toBe(0.0); // stock_lots.quantity mis à jour par recordMovement (P1-D2/P1-D3)
    expect((float) $rowsConsume['LOT-A20']->reserved_quantity)->toBe(0.0);
    expect((float) $rowsConsume['LOT-B20']->quantity)->toBe(300.0);
    expect((float) $rowsConsume['LOT-B20']->reserved_quantity)->toBe(0.0);
});

it('E19 — transfert inter-dépôts réel (P1-F) reflété correctement, total inchangé', function () {
    [$co] = slvSociete('19');
    [$whA, $mp] = slvArticle($co, '19A');
    $whB = Warehouse::create(['code' => 'SLV-WH19B', 'name' => 'Dépôt B19', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);

    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $whA->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $whA->id, 'lot_number' => 'LOT-X19', 'quantity' => 300, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $whA->id, 'to_warehouse_id' => $whB->id,
        'items' => [['product_id' => $mp->id, 'quantity' => 100, 'lot_number' => 'LOT-X19']],
    ]);
    app(StockTransferService::class)->ship($transfer);
    app(StockTransferService::class)->receive($transfer->fresh());

    $response = test()->get(route('stocks.lots', ['product_id' => $mp->id]));
    $lots = $response->viewData('lots')->keyBy(fn ($l) => $l->lot_number.'-'.$l->warehouse_id);

    expect((float) $lots['LOT-X19-'.$whA->id]->quantity)->toBe(200.0);
    expect((float) $lots['LOT-X19-'.$whB->id]->quantity)->toBe(100.0);
    expect($response->viewData('kpi')['physical'])->toBe(300.0);
});

it('E22 — coût unitaire lu depuis StockLot.unit_cost, jamais recalculé', function () {
    [$co] = slvSociete('22');
    [$wh, $mp] = slvArticle($co, '22');
    StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-COST', 'quantity' => 100, 'unit_cost' => 750000, 'status' => 'disponible']);

    $response = test()->get(route('stocks.lots', ['product_id' => $mp->id]));

    $response->assertSee('750');
    $response->assertSee('75 000 000'); // 100 x 750 000
});

it('E17 — KPI porte sur TOUT le résultat filtré, indépendamment de la pagination', function () {
    [$co] = slvSociete('17');
    [$wh, $mp] = slvArticle($co, '17');

    for ($i = 0; $i < 30; $i++) {
        StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-PG-'.$i, 'quantity' => 10, 'status' => 'disponible']);
    }

    $response = test()->get(route('stocks.lots', ['product_id' => $mp->id]));
    $lots = $response->viewData('lots');
    $kpi = $response->viewData('kpi');

    expect($lots->count())->toBeLessThan(30); // page 1 paginée (25)
    expect($kpi['lots'])->toBe(30);
    expect($kpi['physical'])->toBe(300.0); // 30 x 10, pas seulement la page
});

it('E23 — isolation société : un lot d\'une autre société n\'apparaît jamais', function () {
    [$coA] = slvSociete('23A');
    [$whA, $mpA] = slvArticle($coA, '23A');
    StockLot::create(['product_id' => $mpA->id, 'warehouse_id' => $whA->id, 'lot_number' => 'LOT-SOCIETE-A', 'quantity' => 100, 'status' => 'disponible']);

    [$coB, $userB] = slvSociete('23B');
    [$whB, $mpB] = slvArticle($coB, '23B');
    StockLot::create(['product_id' => $mpB->id, 'warehouse_id' => $whB->id, 'lot_number' => 'LOT-SOCIETE-B', 'quantity' => 200, 'status' => 'disponible']);

    // Toujours authentifié société B (dernier acteur défini par slvSociete).
    $response = test()->get(route('stocks.lots'));

    $response->assertSee('LOT-SOCIETE-B');
    $response->assertDontSee('LOT-SOCIETE-A');
});

it('E24 — filtres conservés dans la pagination', function () {
    [$co] = slvSociete('24');
    [$wh, $mp] = slvArticle($co, '24');
    for ($i = 0; $i < 30; $i++) {
        StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-QS-'.$i, 'quantity' => 5, 'status' => 'disponible']);
    }

    $response = test()->get(route('stocks.lots', ['product_id' => $mp->id]));

    $response->assertOk();
    $response->assertSee('product_id='.$mp->id, false);
});
