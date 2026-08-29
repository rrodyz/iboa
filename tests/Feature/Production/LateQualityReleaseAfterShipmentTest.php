<?php

/**
 * [P7.2 — trouvaille en rehearsal] Même cause racine que LateOfValuationTest,
 * un cran plus tôt dans le cycle : QualityReleaseService::releaseFinishedGoods()
 * exigeait le stock physique complet à l'entrepôt de production pour
 * transférer vers l'entrepôt de libération — bloquant si le PF avait déjà
 * quitté cet entrepôt (transfert manuel, expédition) avant la décision
 * qualité posée a posteriori. Reproduit en rejouant OF-2026-0002 sur copie
 * isolée de iboa_erp (voir rapport P7.2) — même symptôme :
 * "Stock insuffisant : 0,00 unité(s) disponible(s), 30,00 demandée(s)."
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Quality\Services\QualityReleaseService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function lqrCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'LQR-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'LQR Co'], ['email' => 'lqr@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function lqrAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => lqrCompany()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    return $u;
}

/** OF avec sortie PF (20 unités) déjà entièrement déplacée hors de son entrepôt de production. */
function lqrSetupAlreadyShipped(): array
{
    $co = lqrCompany();
    $whProd = Warehouse::create(['code' => 'WH-LQR-PROD', 'name' => 'Prod LQR', 'company_id' => $co->id, 'is_active' => true]);
    $whRelease = Warehouse::create(['code' => 'WH-LQR-REL', 'name' => 'Release LQR', 'company_id' => $co->id, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-LQR-' . uniqid(),
        'status' => 'en_cours', 'quantity_requested' => 20, 'quantity_produced' => 20, 'product_id' => $product->id,
        'controle_qualite_obligatoire' => true,
    ]);

    $movement = StockMovement::create([
        'product_id' => $product->id, 'warehouse_id' => $whProd->id, 'type' => 'entree',
        'quantity' => 20, 'unit_cost' => 1_000, 'total_cost' => 20_000, 'occurred_at' => now(),
    ]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $whProd->id, 'quantity' => 20, 'reserved_quantity' => 0, 'avg_cost' => 1_000]);

    $output = ProductionOutput::create([
        'company_id' => $co->id, 'production_order_id' => $of->id, 'product_id' => $product->id,
        'quantity' => 20, 'warehouse_id' => $whProd->id, 'release_warehouse_id' => $whRelease->id,
        'status' => 'validee', 'validated_at' => now(), 'stock_movement_id' => $movement->id,
    ]);

    $batch = ProductionBatch::create([
        'company_id' => $co->id, 'production_order_id' => $of->id, 'batch_number' => 'LOT-LQR-' . uniqid(),
        'quantity' => 20, 'status' => 'en_cours',
    ]);

    \App\Modules\Production\Models\ProductionQualityControl::create([
        'company_id' => $co->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);

    // Le PF quitte déjà entièrement l'entrepôt de production AVANT toute libération qualité —
    // exactement le scénario réel OF-2026-0002 (transfert manuel puis expédition).
    ProductStock::where('product_id', $product->id)->where('warehouse_id', $whProd->id)
        ->update(['quantity' => 0]);
    ProductStock::firstOrCreate(
        ['product_id' => $product->id, 'warehouse_id' => $whRelease->id],
        ['quantity' => 0, 'reserved_quantity' => 0]
    );

    return [$of, $batch, $product, $whProd, $whRelease];
}

it('libère qualité sans exception même quand le PF a déjà quitté l\'entrepôt de production, sans recréer de stock', function () {
    $this->actingAs(lqrAdmin());
    [, $batch, $product, $whProd, $whRelease] = lqrSetupAlreadyShipped();

    // Ne lève plus "Stock insuffisant".
    $release = app(QualityReleaseService::class)->decide($batch, 'libere', 'Libération tardive après expédition');

    expect($release->status)->toBe('libere')
        ->and($batch->fresh()->status)->toBe('conforme');

    // Aucun stock recréé nulle part : ni à la source (toujours 0), ni à la
    // destination (rien à transférer, elle reste 0 aussi — pas de stock fantôme).
    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whProd->id)->value('quantity'))->toBe(0.0);
    expect((float) (ProductStock::where('product_id', $product->id)->where('warehouse_id', $whRelease->id)->value('quantity') ?? 0))->toBe(0.0);

    // La décision qualité est quand même tracée sur la sortie — c'est le seul
    // champ lu par le garde de livraison et par la clôture d'OF.
    $output = ProductionOutput::where('production_order_id', $batch->production_order_id)->first();
    expect($output->quality_released_at)->not->toBeNull();
});

it('transfère uniquement la quote-part encore disponible quand le PF est partiellement resté', function () {
    $this->actingAs(lqrAdmin());
    [$of, $batch, $product, $whProd, $whRelease] = lqrSetupAlreadyShipped();

    // 8 des 20 unités sont en réalité restées à l'entrepôt de production.
    ProductStock::where('product_id', $product->id)->where('warehouse_id', $whProd->id)->update(['quantity' => 8]);

    app(QualityReleaseService::class)->decide($batch, 'libere', 'Libération partielle');

    expect((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whProd->id)->value('quantity'))->toBe(0.0)
        ->and((float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $whRelease->id)->value('quantity'))->toBe(8.0);
});
