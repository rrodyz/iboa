<?php

/**
 * [P7.2 — Phase 3] Régularisation de coût à la clôture d'un OF selon l'état
 * du stock du produit fini au moment de la clôture. Couvre les 4 cas de la
 * mission : stock intact (A), partiellement expédié (B), entièrement
 * expédié (C — le cas réel qui bloquait OF-2026-0001), et idempotence (D).
 *
 * Ne teste jamais via UPDATE SQL direct : quantités/coûts sont produits par
 * le vrai code applicatif (ProductionStockService::recordOutput,
 * FinishedGoodsValuationService::revalue).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockValuationAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\FinishedGoodsValuationService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function lovCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'LOV-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'LOV Co'], ['email' => 'lov@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function lovAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => lovCompany()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    return $u;
}

/** Prépare un OF avec sortie PF (10 unités, coût provisoire 1000/u) et son ProductionCost (coût réel 15 000). */
function lovSetup(): array
{
    $co = lovCompany();
    $wh = Warehouse::firstOrCreate(['code' => 'WH-LOV'], ['name' => 'Dépôt LOV', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-LOV-' . uniqid(),
        'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 0, 'product_id' => $product->id,
    ]);

    $output = app(ProductionStockService::class)->recordOutput($of, [
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 10, 'length' => 1, 'unit_cost' => 1_000,
    ]);

    ProductionCost::create([
        'production_order_id' => $of->id, 'company_id' => $co->id,
        'material_cost' => 15_000, 'total_cost' => 15_000, 'standard_total' => 0,
        'cost_per_unit' => 1_500, 'cost_per_meter' => 1_500,
    ]);

    return [$of->fresh(['outputs']), $product, $wh, $output];
}

it('CAS A — PF encore totalement en stock : régularisation complète normale', function () {
    $this->actingAs(lovAdmin());
    [$of, $product, $wh] = lovSetup();

    app(FinishedGoodsValuationService::class)->revalue($of);

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toBe(10.0)
        ->and((float) $stock->avg_cost)->toBe(1_500.0); // (10*1000 + 5000)/10

    $adj = StockValuationAdjustment::where('production_order_id', $of->id)->first();
    expect((float) $adj->value_delta)->toBe(5_000.0);

    $movement = StockMovement::where('idempotency_key', 'production-valuation-adjustment:' . $of->id . ':' . $of->outputs->first()->id)->first();
    expect((float) $movement->total_cost)->toBe(5_000.0) // appliqué en totalité
        ->and((float) $movement->quantity)->toBe(0.0); // jamais de mouvement physique
});

it('CAS B — PF partiellement livré : régularisation proportionnelle au stock restant, sortie historique intacte', function () {
    $this->actingAs(lovAdmin());
    [$of, $product, $wh, $output] = lovSetup();

    // Livraison de 6 des 10 unités (sortie réelle, hors flux commercial complet — juste le mouvement de stock).
    $stockBefore = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    $stockBefore->update(['quantity' => 4]); // 10 - 6 expédiées
    StockMovement::create([
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'sortie', 'quantity' => 6,
        'unit_cost' => 1_000, 'total_cost' => 6_000, 'occurred_at' => now(),
    ]);
    $sortieCountBefore = StockMovement::where('type', 'sortie')->where('product_id', $product->id)->count();

    app(FinishedGoodsValuationService::class)->revalue($of->fresh(['outputs']));

    // Sortie historique jamais modifiée.
    expect(StockMovement::where('type', 'sortie')->where('product_id', $product->id)->count())->toBe($sortieCountBefore);

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toBe(4.0); // jamais recréé de stock

    // Delta total = 5000 sur 10 unités → 500/u. Quote-part appliquée = 4 unités restantes = 2000.
    $movement = StockMovement::where('idempotency_key', 'production-valuation-adjustment:' . $of->id . ':' . $output->id)->first();
    expect((float) $movement->total_cost)->toBe(2_000.0)
        ->and((float) $movement->quantity)->toBe(0.0);

    // L'audit trace l'écart COMPLET (5000), pas seulement la part appliquée.
    $adj = StockValuationAdjustment::where('production_order_id', $of->id)->first();
    expect((float) $adj->value_delta)->toBe(5_000.0);
});

it('CAS C — PF entièrement livré : clôture ne lève plus d\'exception, aucun stock négatif, écart tracé sans être appliqué', function () {
    $this->actingAs(lovAdmin());
    [$of, $product, $wh, $output] = lovSetup();

    // Expédition complète des 10 unités.
    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    $stock->update(['quantity' => 0]);
    StockMovement::create([
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'sortie', 'quantity' => 10,
        'unit_cost' => 1_000, 'total_cost' => 10_000, 'occurred_at' => now(),
    ]);

    // Ne lève plus l'ancienne ValidationException bloquante (P7.1 → P7.2).
    app(FinishedGoodsValuationService::class)->revalue($of->fresh(['outputs']));

    $stock->refresh();
    expect((float) $stock->quantity)->toBe(0.0) // jamais négatif, jamais recréé
        ->and((float) $stock->quantity)->toBeGreaterThanOrEqual(0);

    $movement = StockMovement::where('idempotency_key', 'production-valuation-adjustment:' . $of->id . ':' . $output->id)->first();
    expect((float) $movement->total_cost)->toBe(0.0); // rien à appliquer, stock disponible = 0

    // L'écart réel (5000) reste tracé intégralement pour audit, même non appliqué au stock.
    $adj = StockValuationAdjustment::where('production_order_id', $of->id)->first();
    expect((float) $adj->value_delta)->toBe(5_000.0);
});

it('CAS D — clôture/régularisation répétée : idempotente, aucune double écriture', function () {
    $this->actingAs(lovAdmin());
    [$of, $product, $wh] = lovSetup();

    app(FinishedGoodsValuationService::class)->revalue($of->fresh(['outputs']));
    app(FinishedGoodsValuationService::class)->revalue($of->fresh(['outputs'])); // second appel

    expect(StockValuationAdjustment::where('production_order_id', $of->id)->count())->toBe(1);
    expect(StockMovement::where('type', 'valuation_adjustment')->where('reference_id', $of->id)->count())->toBe(1);
});
