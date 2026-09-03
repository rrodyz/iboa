<?php

/**
 * [GLOBAL-E2E-PROD-QA §20-26,87] Invariants stock sur dataset QA :
 * AVAILABLE = PHYSICAL - RESERVED, ajustement +/-, blocage négatif, inventaire
 * avec écart, transfert inter-dépôts (total global inchangé), idempotence
 * (double clic sur le même mouvement via idempotency_key).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\InventorySession;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\StockService;
use App\Services\StockTransferService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qaStkCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-E2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    return Company::firstOrCreate(['name' => 'QA-E2E-PROD Co'], ['email' => 'qa-e2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function qaStkAdmin(Company $co): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    return $u;
}

it('QA — ajustement positif +20, négatif -20, retour à la valeur initiale, traçabilité (auteur/date/motif)', function () {
    $co = qaStkCompany();
    $user = qaStkAdmin($co);
    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-ADJ'], ['name' => 'Dépôt QA Ajustement', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['reference' => 'QA-MP-ADJ', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0]);

    $svc = app(StockService::class);
    $mvtPlus = $svc->recordMovement([
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'ajustement',
        'quantity' => 20, 'unit_cost' => 0, 'notes' => 'QA INVENTORY CORRECTION +20',
    ]);
    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toBe(1020.0)
        ->and($mvtPlus->created_by)->toBe($user->id)
        ->and($mvtPlus->notes)->toBe('QA INVENTORY CORRECTION +20')
        ->and($mvtPlus->occurred_at)->not->toBeNull();

    $svc->recordMovement([
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'ajustement',
        'quantity' => -20, 'unit_cost' => 0, 'notes' => 'QA INVENTORY CORRECTION -20',
    ]);
    $stock->refresh();
    expect((float) $stock->quantity)->toBe(1000.0); // retour exact, aucun stock fantôme.
});

it('QA — ajustement négatif interdit au-delà du disponible : BLOCK, pas de stock négatif', function () {
    $co = qaStkCompany();
    qaStkAdmin($co);
    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-NEG'], ['name' => 'Dépôt QA Négatif', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['reference' => 'QA-MP-NEG', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0]);

    $svc = app(StockService::class);
    expect(fn () => $svc->recordMovement([
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'ajustement',
        'quantity' => -1500, 'unit_cost' => 0, 'notes' => 'QA tentative sur-ajustement',
    ]))->toThrow(ValidationException::class);

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toBe(1000.0)->toBeGreaterThanOrEqual(0.0);
});

it('QA — inventaire : écart système 1000 vs compté 995, écart -5 appliqué, audit trail présent', function () {
    $co = qaStkCompany();
    $user = qaStkAdmin($co);
    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-INV'], ['name' => 'Dépôt QA Inventaire', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['reference' => 'QA-MP-INV', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 1000, 'reserved_quantity' => 0]);

    $session = InventorySession::create([
        'company_id' => $co->id, 'warehouse_id' => $wh->id,
        'number' => 'INV-QA-001', 'type' => 'complet', 'status' => 'en_cours',
        'started_at' => now(), 'created_by' => $user->id,
    ]);
    $item = $session->items()->create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'theoretical_quantity' => 1000, 'counted_quantity' => null, 'unit_cost' => 500]);

    app(InventoryService::class)->saveCount($session, [['id' => $item->id, 'counted_quantity' => '995']]);
    expect((float) $item->fresh()->variance_quantity)->toBe(-5.0);

    app(InventoryService::class)->validate($session->fresh());
    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toBe(995.0);

    $mvt = \App\Models\StockMovement::where('product_id', $product->id)->where('type', 'inventaire')->first();
    expect($mvt)->not->toBeNull()
        ->and((float) $mvt->quantity)->toBe(-5.0)
        ->and($mvt->created_by)->not->toBeNull();
});

it('QA — transfert 200kg entre deux dépôts QA : totaux locaux corrects, total global inchangé', function () {
    $co = qaStkCompany();
    qaStkAdmin($co);
    $a = Warehouse::firstOrCreate(['code' => 'QA-WH-MP'], ['name' => 'Dépôt QA MP', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $b = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-2'], ['name' => 'Dépôt QA MP 2', 'company_id' => $co->id, 'is_active' => true]);
    $product = Product::factory()->create(['reference' => 'QA-MP-TRF', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $a->id, 'quantity' => 995, 'reserved_quantity' => 0]);

    $svc = app(StockTransferService::class);
    $transfer = $svc->create([
        'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id,
        'items' => [['product_id' => $product->id, 'quantity' => 200]],
    ]);
    $svc->ship($transfer);
    $svc->receive($transfer->fresh());

    $qtyA = (float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $a->id)->value('quantity');
    $qtyB = (float) ProductStock::where('product_id', $product->id)->where('warehouse_id', $b->id)->value('quantity');
    expect($qtyA)->toBe(795.0)
        ->and($qtyB)->toBe(200.0)
        ->and($qtyA + $qtyB)->toBe(995.0); // aucun changement du stock global (phase 25).
});

it('QA — idempotence : même clé de mouvement rejouée deux fois → un seul mouvement, pas de double comptage (phase 38/87)', function () {
    $co = qaStkCompany();
    qaStkAdmin($co);
    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-IDEM'], ['name' => 'Dépôt QA Idempotence', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['reference' => 'QA-MP-IDEM', 'is_stockable' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'reserved_quantity' => 0]);

    $svc = app(StockService::class);
    $payload = [
        'product_id' => $product->id, 'warehouse_id' => $wh->id, 'type' => 'entree',
        'quantity' => 50, 'unit_cost' => 100, 'idempotency_key' => 'qa-idem-test-001',
    ];
    $mvt1 = $svc->recordMovement($payload);
    $mvt2 = $svc->recordMovement($payload); // double clic simulé — même clé.

    expect($mvt1->id)->toBe($mvt2->id) // même mouvement retourné, pas un doublon.
        ->and(\App\Models\StockMovement::where('idempotency_key', 'qa-idem-test-001')->count())->toBe(1);

    $stock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $stock->quantity)->toBe(150.0)->not->toBe(200.0); // pas de double crédit.
});
