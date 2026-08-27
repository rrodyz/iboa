<?php

/**
 * [P1-D QA gate — §8] Course MySQL RÉELLE croisant DEUX SERVICES DIFFÉRENTS sur
 * le même produit/lot/dépôt : ReservationService::allocateMaterialLot() (P1-D)
 * et StockTransferService::ship() (P1-F). Scénario métier légitime — un lot peut
 * simultanément être demandé pour allocation à un OF ET pour un transfert vers
 * un autre dépôt, par deux utilisateurs différents.
 *
 * Avant correctif (audit "P1-D FINAL GATE") : allocateMaterialLot() verrouillait
 * StockLot AVANT ProductStock ; ship()/receive()/cancel() verrouillent TOUJOURS
 * ProductStock AVANT StockLot — inversion d'ordre structurelle, deadlock
 * potentiel prouvé par lecture de code. Correctif : allocateMaterialLot()
 * verrouille désormais ProductStock avant StockLot, même ordre partout.
 *
 * Ce test ne prouve PAS un deadlock (il prouverait l'ABSENCE de deadlock après
 * correctif — le reproduire nécessiterait de revenir à l'ancien ordre). Il
 * prouve le résultat attendu d'une vraie course : exactement une opération
 * passe, l'autre est proprement bloquée (jamais un SQLSTATE brut / deadlock non
 * géré), et les invariants ProductStock/StockLot restent cohérents.
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
use App\Modules\Production\Models\ProductionOrder;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

function cfCommitFixture(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/** @return array{codes:array<int,int>,outputs:array<int,string>,stderr:string} */
function cfRaceRun(array $jobs, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/cross_flow_race_worker.php');

    $processes = [];
    foreach ($jobs as $args) {
        $processes[] = new Process(
            [PHP_BINARY, $worker, ...array_map('strval', $args), (string) $startAt],
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

it('T-CROSS-FLOW — allocateMaterialLot() (P1-D) vs ship() (P1-F) concurrents sur le même lot : jamais de deadlock, jamais les deux', function () {
    $fy = FiscalYear::create(['label' => 'RACE-XFLOW-2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'OA METAL RACE XFLOW', 'email' => 'race-xflow@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $user = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $w = Warehouse::create(['code' => 'RACE-XFLOW-W', 'name' => 'Dépôt matière', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $w2 = Warehouse::create(['code' => 'RACE-XFLOW-W2', 'name' => 'Dépôt B', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    // Article loté NON coil-managed : allocateMaterialLot() sans bobine, au
    // niveau lot seul — suffisant pour isoler le risque d'ordre de verrous
    // ProductStock/StockLot, sans complexité Coil superflue pour ce scénario.
    $mp = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);
    $pf = Product::factory()->create(['is_manufacturable' => true]);

    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $w->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    $lot = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $w->id, 'lot_number' => 'LOT-XFLOW', 'quantity' => 300, 'unit_cost' => 500, 'status' => 'disponible', 'valuation_status' => 'valorisation_definitive']);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM XFLOW', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mp->id, 'quantity_per_meter' => 1, 'waste_rate' => 0, 'depot_sortie_id' => $w->id]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'number' => 'OF-XFLOW-1', 'product_id' => $pf->id, 'bill_of_material_id' => $bom->id, 'quantity_requested' => 200, 'status' => 'brouillon', 'depot_matiere_id' => $w->id]);

    $transfer = app(StockTransferService::class)->create([
        'from_warehouse_id' => $w->id, 'to_warehouse_id' => $w2->id,
        'items' => [['product_id' => $mp->id, 'quantity' => 200, 'lot_number' => 'LOT-XFLOW']],
    ]);

    cfCommitFixture();
    $race = cfRaceRun([
        ['allocate', $of->id, $lot->id, 200, $user->id],
        ['ship', $transfer->id, $user->id],
    ]);
    DB::reconnect();

    $codes = $race['codes'];
    sort($codes);

    // Preuve centrale : jamais d'erreur inattendue (3 = SQLSTATE/deadlock brut
    // non intercepté). L'un des deux passe (0), l'autre est bloqué proprement (2).
    expect($race['stderr'])->toBe('');
    expect(implode(' ', $race['outputs']))->not->toContain('SQLSTATE')->not->toContain('Deadlock');
    expect($codes)->toBe([0, 2]);

    $stockAfter = (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $w->id)->value('quantity');
    $reservedAfter = (float) ProductStock::where('product_id', $mp->id)->where('warehouse_id', $w->id)->value('reserved_quantity');
    $lotAfter = (float) StockLot::where('id', $lot->id)->value('quantity');

    // Invariant ProductStock == StockLot préservé quel que soit le gagnant.
    expect($stockAfter)->toBe($lotAfter);

    if ($transfer->fresh()->status === 'en_transit') {
        // Le transfert a gagné : 200 physiquement sortis, rien alloué.
        expect($stockAfter)->toBe(100.0)->and($reservedAfter)->toBe(0.0);
    } else {
        // L'allocation a gagné : rien sorti physiquement, 200 réservés.
        expect($stockAfter)->toBe(300.0)->and($reservedAfter)->toBe(200.0);
        expect($transfer->fresh()->status)->toBe('brouillon');
    }
});
