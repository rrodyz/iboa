<?php

/**
 * [PROD-01 — Phase 14] Propositions de transfert inter-dépôts : dépôt en
 * excédent local → dépôt en besoin local. Jamais plus que le disponible
 * réel à la source.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\TransferProposalService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function tpsCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'TPS-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TPS Co'], ['email' => 'tps@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function tpsOrderAt(Company $co, Product $p, Warehouse $wh, float $qty): Order
{
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-TPS-'.uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'delivery_warehouse_id' => $wh->id,
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty,
        'delivered_quantity' => 0, 'unit_price' => 1000,
        'line_total_ht' => $qty * 1000, 'line_tax' => 0, 'line_total_ttc' => $qty * 1000,
    ]);

    return $order;
}

it('propose un transfert A -> B : A en excédent local, B en besoin local', function () {
    $co = tpsCompany();
    $a = Warehouse::create(['company_id' => $co->id, 'code' => 'TPS-A', 'name' => 'Dépôt A', 'is_active' => true]);
    $b = Warehouse::create(['company_id' => $co->id, 'code' => 'TPS-B', 'name' => 'Dépôt B', 'is_active' => true]);
    $p = Product::factory()->create(['name' => 'Tôle bac transfert']);

    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $a->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $b->id, 'quantity' => 0, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    tpsOrderAt($co, $p, $a, 20); // demande locale A = 20 -> excédent A = 80
    tpsOrderAt($co, $p, $b, 30); // demande locale B = 30, dispo 0 -> besoin B = 30

    $proposals = app(TransferProposalService::class)->proposals();

    expect($proposals)->toHaveCount(1);
    $row = $proposals->first();
    expect($row['from_warehouse']->id)->toBe($a->id)
        ->and($row['to_warehouse']->id)->toBe($b->id)
        ->and($row['quantity'])->toBe(30.0);
});

it('ne propose jamais plus que le disponible réel à la source, même avec plusieurs besoins', function () {
    $co = tpsCompany();
    $a = Warehouse::create(['company_id' => $co->id, 'code' => 'TPS-A2', 'name' => 'Dépôt A2', 'is_active' => true]);
    $b = Warehouse::create(['company_id' => $co->id, 'code' => 'TPS-B2', 'name' => 'Dépôt B2', 'is_active' => true]);
    $c = Warehouse::create(['company_id' => $co->id, 'code' => 'TPS-C2', 'name' => 'Dépôt C2', 'is_active' => true]);
    $p = Product::factory()->create(['name' => 'Tôle bac rare']);

    // A n'a que 25 disponibles, sans demande locale -> excédent = 25.
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $a->id, 'quantity' => 25, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    tpsOrderAt($co, $p, $b, 40); // besoin B = 40 (dépôt jamais approvisionné)
    tpsOrderAt($co, $p, $c, 40); // besoin C = 40

    $proposals = app(TransferProposalService::class)->proposals();

    // Total transféré depuis A ne doit jamais dépasser 25, quel que soit le nombre de besoins.
    expect($proposals->where('from_warehouse.id', $a->id)->sum('quantity'))->toBe(25.0);
});

it('ne propose rien quand le stock disponible couvre exactement la demande locale de chaque dépôt', function () {
    $co = tpsCompany();
    $a = Warehouse::create(['company_id' => $co->id, 'code' => 'TPS-A3', 'name' => 'Dépôt A3', 'is_active' => true]);
    $p = Product::factory()->create(['name' => 'Tôle bac équilibrée']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $a->id, 'quantity' => 20, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    tpsOrderAt($co, $p, $a, 20);

    expect(app(TransferProposalService::class)->proposals())->toBeEmpty();
});

// [Clôture PROD-01 — section 11] Exemple EXACT de la directive :
// DEP-A 100 dispo, besoin local 20 -> excédent 80.
// DEP-B stock 10, besoin 50 -> déficit 40.
// Attendu : proposition DEP-A -> DEP-B = 40 (min(80,40)), jamais 80.
it('exemple exact DEP-A/DEP-B : excédent 80, déficit 40, transfert proposé = 40 (jamais 80)', function () {
    $co = tpsCompany();
    $depA = Warehouse::create(['company_id' => $co->id, 'code' => 'DEP-A', 'name' => 'DEP-A', 'is_active' => true]);
    $depB = Warehouse::create(['company_id' => $co->id, 'code' => 'DEP-B', 'name' => 'DEP-B', 'is_active' => true]);
    $p = Product::factory()->create(['name' => 'Article DEP-A/B']);

    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $depA->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $depB->id, 'quantity' => 10, 'reserved_quantity' => 0, 'avg_cost' => 1000]);

    tpsOrderAt($co, $p, $depA, 20); // besoin local A = 20 -> excédent = 100 − 20 = 80
    tpsOrderAt($co, $p, $depB, 50); // besoin local B = 50, stock 10 -> déficit = 50 − 10 = 40

    $proposals = app(TransferProposalService::class)->proposals();

    expect($proposals)->toHaveCount(1);
    $row = $proposals->first();
    expect($row['from_warehouse']->id)->toBe($depA->id)
        ->and($row['to_warehouse']->id)->toBe($depB->id)
        ->and($row['quantity'])->toBe(40.0);
});

// Idempotence : un transfert déjà EXÉCUTÉ (stock physiquement déplacé) ne
// doit plus être reproposé — aucun état "proposition" n'est persisté, le
// service recalcule toujours depuis le stock réel, donc intrinsèquement
// idempotent (même principe que les propositions OF du MRP).
it('idempotence : un transfert déjà exécuté (stock déplacé) n’est plus reproposé', function () {
    $co = tpsCompany();
    $depA = Warehouse::create(['company_id' => $co->id, 'code' => 'DEP-A2', 'name' => 'DEP-A2', 'is_active' => true]);
    $depB = Warehouse::create(['company_id' => $co->id, 'code' => 'DEP-B2', 'name' => 'DEP-B2', 'is_active' => true]);
    $p = Product::factory()->create(['name' => 'Article idempotence']);

    $stockA = ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $depA->id, 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    $stockB = ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $depB->id, 'quantity' => 10, 'reserved_quantity' => 0, 'avg_cost' => 1000]);
    tpsOrderAt($co, $p, $depA, 20);
    tpsOrderAt($co, $p, $depB, 50);

    expect(app(TransferProposalService::class)->proposals()->sum('quantity'))->toBe(40.0);

    // Transfert réellement exécuté : 40 déplacées physiquement de A vers B.
    $stockA->decrement('quantity', 40);
    $stockB->increment('quantity', 40);

    expect(app(TransferProposalService::class)->proposals())->toBeEmpty();
});

it('plusieurs dépôts sources en excédent couvrent un même dépôt en besoin, sans dépasser leur propre disponible', function () {
    $co = tpsCompany();
    $a = Warehouse::create(['company_id' => $co->id, 'code' => 'MULTI-A', 'name' => 'Multi A', 'is_active' => true]);
    $b = Warehouse::create(['company_id' => $co->id, 'code' => 'MULTI-B', 'name' => 'Multi B', 'is_active' => true]);
    $c = Warehouse::create(['company_id' => $co->id, 'code' => 'MULTI-C', 'name' => 'Multi C', 'is_active' => true]);
    $p = Product::factory()->create(['name' => 'Article multi-sources']);

    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $a->id, 'quantity' => 30, 'reserved_quantity' => 0, 'avg_cost' => 1000]); // excédent 30
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => $b->id, 'quantity' => 30, 'reserved_quantity' => 0, 'avg_cost' => 1000]); // excédent 30
    tpsOrderAt($co, $p, $c, 50); // besoin C = 50, jamais approvisionné

    $proposals = app(TransferProposalService::class)->proposals();

    expect($proposals->where('to_warehouse.id', $c->id)->sum('quantity'))->toBe(50.0)
        ->and($proposals->where('from_warehouse.id', $a->id)->sum('quantity'))->toBeLessThanOrEqual(30.0)
        ->and($proposals->where('from_warehouse.id', $b->id)->sum('quantity'))->toBeLessThanOrEqual(30.0);
});
