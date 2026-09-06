<?php

/**
 * [R4 — concurrence] Courses MySQL RÉELLES sur les trois points de bascule.
 *
 * Chaque scénario lance de VRAIS processus PHP concurrents, avec leurs propres
 * connexions PDO et un départ synchronisé. Un test mono-processus ne prouverait
 * que la logique applicative : il rejouerait les appels en série, sans jamais
 * exercer les verrous.
 *
 * Ce qui est en jeu, concrètement :
 *   A. deux encaissements atteignent le seuil ensemble → deux bons de
 *      préparation, donc deux autorisations de chargement et deux ordres de
 *      fabrication pour une seule commande ;
 *   B. deux responsables approuvent la même demande → mêmes conséquences ;
 *   C. deux validations du même bon de livraison → deux sorties de stock et
 *      deux factures pour une seule livraison.
 */

use App\Models\BonPreparation;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sales\PreparationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

/** Le décor doit être COMMITÉ : les workers ont leurs propres connexions. */
function r4RaceCommit(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
    DB::disconnect();
}

/**
 * @return array{company:Company,fy:FiscalYear,user:User}
 *
 * Chaque scénario COMMITE son décor — c'est la condition d'une course réelle —
 * et laisse donc derrière lui les documents qu'il a créés. Or les numéros de
 * document sont uniques GLOBALEMENT alors que la séquence repart à 0001 pour
 * chaque société : sans purge, le scénario suivant réclamerait un numéro déjà
 * pris et échouerait sur une contrainte d'unicité, pas sur ce qu'il prétend
 * mesurer. On repart donc d'une table de bons vide à chaque scénario.
 */
function r4RaceContext(string $suffixe): array
{
    DB::table('bon_preparations')->delete();

    $fy = FiscalYear::create([
        'label' => "R4RACE-{$suffixe}-2026", 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31',
        'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::create([
        'name' => "OA METAL R4RACE {$suffixe}", 'email' => "r4race-{$suffixe}@oa-metal.test",
        'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    // Les workers passent par les services réels : le rôle super_admin leur
    // ouvre les permissions sans multiplier le décor.
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => $company->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return ['company' => $company, 'fy' => $fy, 'user' => $user];
}

function r4RaceOrder(array $ctx, Client $client, int $ttc, string $statut = 'confirme'): Order
{
    return Order::create([
        'company_id' => $ctx['company']->id, 'fiscal_year_id' => $ctx['fy']->id,
        'client_id' => $client->id, 'number' => 'R4RACE-'.uniqid(),
        'status' => $statut, 'issued_at' => now(),
        'subtotal_ht' => (int) round($ttc / 1.18), 'total_ttc' => $ttc,
        'invoiced_amount' => 0, 'created_by' => $ctx['user']->id,
    ]);
}

/**
 * Lance les workers en parallèle, départ synchronisé.
 *
 * @param  array<int,array{0:string,1:int,2:int,3?:int}>  $jobs  [action, id, userId, montant?]
 * @return array{codes:array<int,int>,outputs:array<int,string>,stderr:string}
 */
function r4RaceRun(array $jobs, float $lead = 1.5): array
{
    $startAt = microtime(true) + $lead;
    $worker = base_path('tests/Support/r4_race_worker.php');

    $processes = [];
    foreach ($jobs as $job) {
        [$action, $id, $userId] = $job;
        $montant = (string) ($job[3] ?? 0);
        $processes[] = new Process(
            [PHP_BINARY, $worker, $action, (string) $id, (string) $userId, (string) $startAt, $montant],
            base_path(), null, null, 120,
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
 * EXCLUSION DÉCLARÉE — ces scénarios ne peuvent pas s'exécuter sur SQLite.
 *
 * Raison technique, pas de commodité : sous SQLite `:memory:` chaque processus
 * ouvre sa propre base vide, les workers ne verraient rien du décor, et
 * `SELECT ... FOR UPDATE` — le mécanisme même que l'on évalue — n'existe pas.
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

/**
 * ISOLATION — obligatoire.
 *
 * Committer le décor annule l'isolation transactionnelle habituelle : tout ce
 * que ce fichier écrit survit dans la base de test et contaminerait les tests
 * suivants du même processus. On force donc une reconstruction complète après
 * ce fichier, exactement comme MySqlCreditConcurrencyTest.
 */
afterAll(function () {
    RefreshDatabaseState::$migrated = false;
});

// ---------------------------------------------------------------------------
// Scénario A — deux encaissements atteignent le seuil en même temps
// ---------------------------------------------------------------------------
it('scénario A : deux règlements concurrents ne produisent qu un seul bon de préparation', function () {
    $ctx = r4RaceContext('A');
    $client = Client::factory()->create([
        'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0, 'is_active' => true,
    ]);
    $order = r4RaceOrder($ctx, $client, 1_000_000);
    CashAccount::factory()->create([
        'company_id' => $ctx['company']->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true,
    ]);

    r4RaceCommit();
    $course = r4RaceRun([
        ['cash_payment_bp', $order->id, $ctx['user']->id, 1_000_000],
        ['cash_payment_bp', $order->id, $ctx['user']->id, 1_000_000],
    ]);
    DB::reconnect();

    $codes = $course['codes'];
    sort($codes);

    $bons = BonPreparation::where('order_id', $order->id)->count();

    expect($course['stderr'])->not->toContain('SQLSTATE');
    // Les sorties des workers accompagnent l'assertion : un échec doit dire
    // POURQUOI un worker a refusé, pas seulement qu'il a refusé.
    test()->assertSame([0, 2], $codes, implode("\n", $course['outputs']));
    expect($bons)->toBe(1);               // jamais deux autorisations de chargement

    // Aucune sortie « erreur inattendue » : le refus est métier, pas une collision SQL.
    expect(implode("\n", $course['outputs']))->not->toContain('"result":"error"');
});

// ---------------------------------------------------------------------------
// Scénario B — deux approbations simultanées de la même demande
// ---------------------------------------------------------------------------
it('scénario B : deux approbations concurrentes ne valident qu une fois et n émettent qu un bon', function () {
    $ctx = r4RaceContext('B');
    $client = Client::factory()->create([
        'payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 50_000_000, 'is_active' => true,
    ]);
    $order = r4RaceOrder($ctx, $client, 1_000_000);
    $order->forceFill([
        'preparation_approval_status' => PreparationEligibilityService::APPROVAL_PENDING,
        'preparation_requested_by' => $ctx['user']->id,
        'preparation_requested_at' => now(),
        'preparation_approval_context' => ['credit_limit' => 50_000_000, 'order_amount' => 1_000_000],
    ])->save();

    r4RaceCommit();
    $course = r4RaceRun([
        ['decide_preparation', $order->id, $ctx['user']->id],
        ['decide_preparation', $order->id, $ctx['user']->id],
    ]);
    DB::reconnect();

    $codes = $course['codes'];
    sort($codes);

    expect($course['stderr'])->not->toContain('SQLSTATE');
    test()->assertSame([0, 2], $codes, implode("\n", $course['outputs']));
    expect(BonPreparation::where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->preparation_approval_status)
            ->toBe(PreparationEligibilityService::APPROVAL_APPROVED);

    // Une seule décision inscrite au journal : pas deux approbations empilées.
    expect(DB::table('commercial_validations')
        ->where('document_type', 'order')->where('document_id', $order->id)
        ->where('nouveau_statut', 'preparation_approved')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Scénario C — deux validations simultanées du même bon de livraison
// ---------------------------------------------------------------------------
it('scénario C : deux validations concurrentes du même BL n émettent qu une facture', function () {
    $ctx = r4RaceContext('C');
    $client = Client::factory()->create([
        'payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 50_000_000, 'is_active' => true,
    ]);
    $order = r4RaceOrder($ctx, $client, 590_000);

    $unit = Unit::firstOrCreate(['name' => 'Pièce R4RACE'], ['abbreviation' => 'pr4']);
    $tva = TaxRate::firstOrCreate(['name' => 'TVA 18% R4RACE'], ['short_name' => 'TVAR4', 'rate' => 18, 'is_active' => true]);
    $produit = Product::factory()->create(['is_stockable' => true]);
    $depot = Warehouse::create([
        'company_id' => $ctx['company']->id, 'code' => 'PF-R4RACE', 'name' => 'PF course R4',
        'type' => 'produit_fini', 'can_sale' => true, 'can_delivery' => true,
        'can_stock' => true, 'is_active' => true,
    ]);
    ProductStock::create([
        'product_id' => $produit->id, 'warehouse_id' => $depot->id,
        'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000,
    ]);

    $order->items()->create([
        'product_id' => $produit->id, 'description' => 'Article course C',
        'quantity' => 10, 'unit_price' => 50_000, 'discount_percent' => 0,
        'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        'line_total_ht' => 500_000, 'line_tax' => 90_000, 'line_total_ttc' => 590_000,
        'delivered_quantity' => 0,
    ]);

    // Chargement terminé : sans cela, R4.10 refuse le bon de livraison.
    BonPreparation::create([
        'company_id' => $ctx['company']->id, 'order_id' => $order->id,
        'fiscal_year_id' => $ctx['fy']->id, 'number' => 'BP-R4RACE-C',
        'payment_mode' => 'credit', 'status' => 'charge',
    ]);

    $bl = app(\App\Services\DeliveryNoteService::class)->createFromOrder($order->fresh());
    $bl->update(['warehouse_id' => $depot->id]);

    r4RaceCommit();
    $course = r4RaceRun([
        ['validate_delivery_note', $bl->id, $ctx['user']->id],
        ['validate_delivery_note', $bl->id, $ctx['user']->id],
    ]);
    DB::reconnect();

    $codes = $course['codes'];
    sort($codes);

    expect($course['stderr'])->not->toContain('SQLSTATE');
    test()->assertSame([0, 2], $codes, implode("\n", $course['outputs']));
    expect(Invoice::where('delivery_note_id', $bl->id)->count())->toBe(1)
        ->and($bl->fresh()->status)->toBe('valide');

    // Une seule sortie de stock : la double validation ne double pas le mouvement.
    expect(DB::table('stock_movements')
        ->where('reference_type', 'delivery_note')->where('reference_id', $bl->id)->count())->toBe(1);
});
