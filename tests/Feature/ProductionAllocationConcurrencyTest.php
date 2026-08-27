<?php

/**
 * [P1-D] Course MySQL RÉELLE sur allocateMaterialLot() — deux OF concurrents
 * tentant chacun d'allouer 700 sur la même bobine de 1000 kg disponible.
 *
 * Même pattern que StockTransferConcurrencyTest.php (P1-F-C) et
 * MySqlCreditConcurrencyTest.php : deux VRAIS processus PHP indépendants,
 * connexions PDO distinctes, départ synchronisé. Attendu : UN SEUL passe
 * (700 ≤ 1000), l'autre est bloqué (700+700 > 1000) — jamais les deux.
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
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

function allocRaceCommitFixture(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/** @return array{codes:array<int,int>,outputs:array<int,string>,stderr:string} */
function allocRaceRun(array $jobs, int $userId, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/allocation_race_worker.php');

    $processes = [];
    foreach ($jobs as [$orderId, $lotId, $coilId, $quantity]) {
        $processes[] = new Process(
            [PHP_BINARY, $worker, (string) $orderId, (string) $lotId, (string) $coilId, (string) $quantity, (string) $userId, (string) $startAt],
            base_path(), null, null, 60
        );
    }
    foreach ($processes as $process) {
        $process->start();
    }
    foreach ($processes as $process) {
        $process->wait();
    }

    return [
        'codes' => array_map(fn (Process $p) => (int) $p->getExitCode(), $processes),
        'outputs' => array_map(fn (Process $p) => $p->getOutput(), $processes),
        'stderr' => trim(implode("\n", array_map(fn (Process $p) => $p->getErrorOutput(), $processes))),
    ];
}

beforeEach(function () {
    if (config('database.default') !== 'mysql') {
        test()->markTestSkipped(
            'Course multi-processus réservée à MySQL : SQLite :memory: n\'est pas '
            .'partageable entre processus et n\'implémente pas SELECT ... FOR UPDATE.'
        );
    }
    expect((string) config('database.connections.mysql.database'))->toContain('test');
});

afterAll(function () {
    RefreshDatabaseState::$migrated = false;
});

it('T-CONCURRENCE — deux allocations concurrentes (700+700 depuis 1000) : une seule passe intégralement', function () {
    $fy = FiscalYear::create(['label' => 'RACE-ALLOC-2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'OA METAL RACE ALLOC', 'email' => 'race-alloc@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $user = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $wh = Warehouse::create(['code' => 'RACE-ALLOC-A', 'name' => 'Dépôt Matière', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $cat = ItemCategory::create(['code' => 'RACE-ALLOC-CAT', 'name' => 'Bobines course', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0]);
    $lot = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-RACE-ALLOC', 'quantity' => 1000, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);
    $coil = Coil::create(['company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $wh->id, 'stock_lot_id' => $lot->id, 'reference' => 'COIL-RACE-ALLOC', 'initial_weight' => 1000, 'remaining_weight' => 1000, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive', 'cost_per_kg' => 500]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM RACE ALLOC', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1, 'waste_rate' => 0, 'depot_sortie_id' => $wh->id]);

    $of1 = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-RACE-ALLOC-1', 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 700, 'status' => 'brouillon', 'depot_matiere_id' => $wh->id]);
    $of2 = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-RACE-ALLOC-2', 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 700, 'status' => 'brouillon', 'depot_matiere_id' => $wh->id]);

    allocRaceCommitFixture();
    $race = allocRaceRun([
        [$of1->id, $lot->id, $coil->id, 700],
        [$of2->id, $lot->id, $coil->id, 700],
    ], $user->id);
    DB::reconnect();

    $codes = $race['codes'];
    sort($codes);

    expect($race['stderr'])->toBe('');
    expect(implode(' ', $race['outputs']))->not->toContain('SQLSTATE');
    expect($codes)->toBe([0, 2]);

    $totalReserved = (float) StockReservation::where('coil_id', $coil->id)->where('status', 'reserved')->sum('quantity');
    expect($totalReserved)->toBe(700.0)
        ->and($totalReserved)->toBeLessThanOrEqual(1000.0);

    $psReserved = (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $wh->id)->value('reserved_quantity');
    expect($psReserved)->toBe(700.0);
});
