<?php

/**
 * [FIX P1-D3 — quantification canonique des réservations matière]
 *
 * Root cause (prouvé par tests réels, rapport « P1-D ABSOLUTE FINAL GATE ») :
 * allocateMaterialLot() stockait StockReservation.quantity ARRONDI (cast
 * decimal:2) mais créditait product_stocks.reserved_quantity avec la valeur
 * BRUTE (jusqu'à 4 décimales, issue du calcul BOM). Les deux représentations
 * de la même réservation divergeaient dès l'allocation :
 *   - une réservation totalement consommée ne passait jamais au statut
 *     « consumed » (écart résiduel entre quantity arrondie et consumed_
 *     quantity brute) ;
 *   - après N cycles allocate→release, product_stocks.reserved_quantity ne
 *     revenait jamais exactement à 0 (résidu mesuré : 0,019 kg sur N=100).
 *
 * Fix (Option A — quantification canonique à 2 décimales, cf. rapport P1-D3
 * QUANTITY PRECISION FINAL pour la justification complète) :
 * ReservationService::canonicalizeQuantity() est le point UNIQUE d'arrondi,
 * appliqué à l'entrée d'allocateMaterialLot() ET de
 * CoilConsumptionService::consume() — la MÊME valeur canonique alimente
 * StockReservation.quantity/consumed_quantity ET le delta product_stocks.
 * reserved_quantity, à chaque étape (allocation, consommation, release).
 * Aucune migration : 2 décimales est déjà l'échelle réelle de
 * stock_reservations ET la précision physique réelle des bobines
 * (coils.remaining_weight, production_consumptions.weight_consumed — toutes
 * deux DECIMAL(12,2) bien avant P1-D).
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
use App\Modules\Production\Services\ReservationService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mrpSociete(string $suffix): Company
{
    $fy = FiscalYear::create(['label' => 'MRP'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'MRP Co'.$suffix, 'email' => 'mrp'.$suffix.'@mrp.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

/** Scénario coil-managed avec besoin BOM naturel à 4 décimales (12.6663). */
function mrpScenario(string $suffix, float $coilWeight = 1000): array
{
    $co = mrpSociete($suffix);
    $wh = Warehouse::create(['code' => 'MRP-WH'.$suffix, 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $cat = ItemCategory::create(['code' => 'MRP_BOB'.$suffix, 'name' => 'MRP Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => $coilWeight, 'reserved_quantity' => 0]);
    $lot = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-MRP'.$suffix, 'quantity' => $coilWeight, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coil = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lot->id, 'reference' => 'COIL-MRP'.$suffix, 'initial_weight' => $coilWeight, 'remaining_weight' => $coilWeight, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM MRP'.$suffix, 'is_active' => true]);
    $line = $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1.2345, 'waste_rate' => 2.5, 'depot_sortie_id' => $wh->id]);
    $order = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-MRP'.$suffix, 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 10.01, 'status' => 'en_cours', 'depot_matiere_id' => $wh->id]);

    $required = round((float) $line->quantity_per_meter * (float) $order->quantity_requested * (1 + (float) $line->waste_rate / 100), 4);

    return compact('co', 'wh', 'mp', 'pf', 'order', 'lot', 'coil', 'required');
}

function mrpSnapshot(ProductionOrder $order, Product $mp, Warehouse $wh): array
{
    $res = StockReservation::where('production_order_id', $order->id)->first();
    $ps = ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->first();

    return [
        'reservation_quantity' => $res ? (float) $res->quantity : null,
        'consumed_quantity' => $res ? (float) $res->consumed_quantity : null,
        'remaining_reserved' => $res ? $res->remainingReserved() : null,
        'status' => $res?->status,
        'ps_reserved' => $ps ? (float) $ps->reserved_quantity : null,
    ];
}

it('P1-D3-01 — allocation fractionnaire : detail == aggregate dès l’allocation', function () {
    ['mp' => $mp, 'wh' => $wh, 'order' => $order, 'lot' => $lot, 'coil' => $coil, 'required' => $required] = mrpScenario('01');

    $reservation = app(ReservationService::class)->allocateMaterialLot($order, $lot, $required, $coil);
    $snap = mrpSnapshot($order, $mp, $wh);

    $canonical = round($required, 2);
    $difference = round($snap['reservation_quantity'] - $snap['ps_reserved'], 4);

    dump(['P1D3_01_ALLOCATION' => [
        'raw_required' => $required,
        'canonical' => $canonical,
        'reservation_quantity' => $snap['reservation_quantity'],
        'ps_reserved' => $snap['ps_reserved'],
        'difference' => $difference,
    ]]);

    expect($reservation->quantity)->toEqual($canonical);
    expect($snap['ps_reserved'])->toBe($canonical);
    expect($difference)->toBe(0.0);
});

it('P1-D3-02 — consommation complète : statut passe à consumed, résidu nul', function () {
    ['mp' => $mp, 'wh' => $wh, 'order' => $order, 'lot' => $lot, 'coil' => $coil, 'required' => $required] = mrpScenario('02');

    $reservation = app(ReservationService::class)->allocateMaterialLot($order, $lot, $required, $coil);
    $canonical = (float) $reservation->quantity;

    app(CoilConsumptionService::class)->consume($order, $coil, $required);
    $snap = mrpSnapshot($order, $mp, $wh);

    dump(['P1D3_02_FULL_CONSUMPTION' => [
        'allocated_canonical' => $canonical,
        'consumed_input_raw' => $required,
        'after' => $snap,
    ]]);

    expect($snap['consumed_quantity'])->toBe($canonical);
    expect($snap['remaining_reserved'])->toBe(0.0);
    expect($snap['status'])->toBe('consumed');
    expect($snap['ps_reserved'])->toBe(0.0);
});

it('P1-D3-03 — consommation partielle : remaining et aggregate coïncident exactement', function () {
    ['mp' => $mp, 'wh' => $wh, 'order' => $order, 'lot' => $lot, 'coil' => $coil, 'required' => $required] = mrpScenario('03');

    $reservation = app(ReservationService::class)->allocateMaterialLot($order, $lot, $required, $coil);
    $canonical = (float) $reservation->quantity;

    $partial = 7.1111;
    app(CoilConsumptionService::class)->consume($order, $coil, $partial);
    $snap = mrpSnapshot($order, $mp, $wh);

    $expectedRemaining = round($canonical - round($partial, 2), 2);
    $difference = round($snap['ps_reserved'] - $snap['remaining_reserved'], 4);

    dump(['P1D3_03_PARTIAL_CONSUMPTION' => [
        'allocated' => $canonical,
        'consumed_input_raw' => $partial,
        'remaining_reserved' => $snap['remaining_reserved'],
        'ps_reserved' => $snap['ps_reserved'],
        'expected_remaining' => $expectedRemaining,
        'difference' => $difference,
    ]]);

    expect($snap['status'])->toBe('reserved');
    expect($snap['remaining_reserved'])->toBe($expectedRemaining);
    expect($snap['ps_reserved'])->toBe($expectedRemaining);
    expect($difference)->toBe(0.0);
});

it('P1-D3-04 — release après consommation partielle : pas de sur-libération, résidu nul', function () {
    ['mp' => $mp, 'wh' => $wh, 'order' => $order, 'lot' => $lot, 'coil' => $coil, 'required' => $required] = mrpScenario('04');

    $reservation = app(ReservationService::class)->allocateMaterialLot($order, $lot, $required, $coil);

    $partial = 7.1111;
    app(CoilConsumptionService::class)->consume($order, $coil, $partial);
    app(ReservationService::class)->release($reservation->fresh());
    $snap = mrpSnapshot($order, $mp, $wh);

    dump(['P1D3_04_RELEASE_AFTER_PARTIAL' => [
        'allocated' => (float) $reservation->quantity,
        'consumed_input_raw' => $partial,
        'ps_reserved_final' => $snap['ps_reserved'],
        'status' => $snap['status'],
    ]]);

    expect($snap['ps_reserved'])->toBe(0.0);
    expect($snap['status'])->toBe('released');
});

it('P1-D3-05 — 100 allocations fractionnaires : accumulation contrôlée, résidu nul après libération totale', function () {
    $co = mrpSociete('05');
    $wh = Warehouse::create(['code' => 'MRP-WH05', 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $cat = ItemCategory::create(['code' => 'MRP_BOB05', 'name' => 'MRP Bobines', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    $bigWeight = 100000.0;
    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => $bigWeight, 'reserved_quantity' => 0]);
    $lot = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-MRP05', 'quantity' => $bigWeight, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coil = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lot->id, 'reference' => 'COIL-MRP05', 'initial_weight' => $bigWeight, 'remaining_weight' => $bigWeight, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM MRP05', 'is_active' => true]);
    $line = $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1.2345, 'waste_rate' => 2.5, 'depot_sortie_id' => $wh->id]);

    $n = 100;
    $canonicalTotal = 0.0;
    $orders = [];
    for ($i = 0; $i < $n; $i++) {
        $qtyRequested = 10.01 + ($i * 0.037);
        $required = round((float) $line->quantity_per_meter * $qtyRequested * (1 + (float) $line->waste_rate / 100), 4);
        $order = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-MRP05-'.$i, 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => $qtyRequested, 'status' => 'brouillon', 'depot_matiere_id' => $wh->id]);
        $reservation = app(ReservationService::class)->allocateMaterialLot($order, $lot, $required, $coil);
        $canonicalTotal += (float) $reservation->quantity;
        $orders[] = $order;
    }

    $reservationTotal = (float) StockReservation::where('coil_id', $coil->id)->sum('quantity');
    $aggregateTotal = (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('reserved_quantity');

    foreach ($orders as $order) {
        app(ReservationService::class)->releaseForProductionOrder($order);
    }
    $finalResidual = (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('reserved_quantity');

    dump(['P1D3_05_ACCUMULATION_N100' => [
        'n' => $n,
        'canonical_total' => round($canonicalTotal, 2),
        'reservation_detail_total' => $reservationTotal,
        'productstock_aggregate_total' => $aggregateTotal,
        'final_residual_after_release' => $finalResidual,
    ]]);

    expect($reservationTotal)->toBe($aggregateTotal);
    expect($finalResidual)->toBe(0.0);
});
