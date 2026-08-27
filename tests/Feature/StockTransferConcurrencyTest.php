<?php

/**
 * [P1-F-C] Course MySQL RÉELLE sur ship() — deux transferts concurrents
 * consommant le même ProductStock + StockLot source.
 *
 * Même pattern que tests/Feature/MySqlCreditConcurrencyTest.php : deux VRAIS
 * processus PHP indépendants, chacun avec sa propre connexion PDO, départ
 * synchronisé. Un test mono-processus ne prouverait rien du verrouillage —
 * il rejouerait la logique applicative en série, jamais en concurrence réelle.
 *
 * Scénario (§22/§6 du prompt P1-F-C) :
 *   ProductStock source = 300, StockLot LOT-RACE = 300.
 *   T1 transfère 200, T2 transfère 200, EN MÊME TEMPS.
 *   Attendu : UN SEUL passe (200 ≤ 300), l'autre est bloqué (200+200 > 300).
 *   Interdit : les deux passent (stock négatif, ProductStock ≠ StockLot).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

/** Le décor doit être COMMITÉ : les workers ont leurs propres connexions. */
function transferRaceCommitFixture(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/**
 * Lance les workers en parallèle, départ synchronisé.
 *
 * @param  array<int,int>  $transferIds
 * @return array{codes:array<int,int>,outputs:array<int,string>,stderr:string}
 */
function transferRaceRun(array $transferIds, int $userId, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/transfer_race_worker.php');

    $processes = [];
    foreach ($transferIds as $id) {
        $processes[] = new Process(
            [PHP_BINARY, $worker, (string) $id, (string) $userId, (string) $startAt],
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

/**
 * EXCLUSION DÉCLARÉE — réservé à MySQL, même motif que MySqlCreditConcurrencyTest :
 * SQLite :memory: n'est pas partageable entre processus et n'implémente pas
 * SELECT ... FOR UPDATE, précisément le mécanisme évalué ici.
 */
beforeEach(function () {
    if (config('database.default') !== 'mysql') {
        test()->markTestSkipped(
            'Course multi-processus réservée à MySQL : SQLite :memory: n\'est pas '
            .'partageable entre processus et n\'implémente pas SELECT ... FOR UPDATE.'
        );
    }

    expect((string) config('database.connections.mysql.database'))->toContain('test');
});

/** Même remède que MySqlCreditConcurrencyTest : le décor commité doit être purgé. */
afterAll(function () {
    RefreshDatabaseState::$migrated = false;
});

it('§22 — deux ship() concurrents (200+200 depuis 300) : un seul passe, ProductStock=StockLot après course', function () {
    $fy = FiscalYear::create(['label' => 'RACE-XFER-2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $company = Company::create(['name' => 'OA METAL RACE XFER', 'email' => 'race-xfer@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

    $a = Warehouse::create(['code' => 'RACE-A', 'name' => 'Dépôt Race A', 'company_id' => $company->id, 'is_active' => true, 'can_stock' => true]);
    $b = Warehouse::create(['code' => 'RACE-B', 'name' => 'Dépôt Race B', 'company_id' => $company->id, 'is_active' => true, 'can_stock' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'has_lot_number' => true]);

    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 300, 'reserved_quantity' => 0]);
    StockLot::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'lot_number' => 'LOT-RACE', 'quantity' => 300, 'unit_cost' => 500]);

    // Deux transferts en brouillon, chacun 200, prêts à être expédiés par les workers.
    $t1 = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 200, 'lot_number' => 'LOT-RACE']],
    ]);
    $t2 = app(StockTransferService::class)->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 200, 'lot_number' => 'LOT-RACE']],
    ]);

    transferRaceCommitFixture();
    $race = transferRaceRun([$t1->id, $t2->id], $user->id);
    DB::reconnect();

    $codes = $race['codes'];
    sort($codes);

    expect($race['stderr'])->toBe('');
    expect(implode(' ', $race['outputs']))->not->toContain('SQLSTATE');
    // Exactement un ship() réussit (0), l'autre est bloqué par la validation
    // métier "stock insuffisant" (2) — jamais les deux à 0, jamais une erreur
    // SQL brute (3, qui signerait une race non maîtrisée / deadlock non géré).
    expect($codes)->toBe([0, 2]);

    $stockA = (float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $a->id)->value('quantity');
    $lotA = (float) StockLot::where('product_id', $product->id)->where('warehouse_id', $a->id)->where('lot_number', 'LOT-RACE')->value('quantity');

    // Un seul des deux transferts de 200 est passé : 300 - 200 = 100. JAMAIS
    // 300-400=-100 (négatif) : c'est exactement le risque que ce fix ferme.
    expect($stockA)->toBe(100.0);
    expect($lotA)->toBe(100.0);
    // Invariant central P1-F-C : ProductStock et StockLot restent synchronisés
    // même à l'issue d'une course réelle, pas seulement en exécution séquentielle.
    expect($stockA)->toBe($lotA);
});
