<?php

/**
 * [PROD-01 — Phase 1/2/3] Besoin net matière première par explosion de
 * nomenclature, avec pegging vers la commande/proposition source.
 *
 * Reprend l'exemple chiffré de la mission : PF A commandé (CMD-001, 100),
 * nomenclature 1 PF A = 2 MP B → besoin brut B = 200 ; stock B = 50,
 * réception attendue = 20 → besoin net = 130.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Services\MrpPeggingService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function peggingCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PEG-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'Pegging Co'], ['email' => 'pegging@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WPEG'], ['name' => 'WPEG', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

it('explose une commande MTO jusqu\'à la matière première et calcule le besoin net avec pegging', function () {
    $co = peggingCompany();
    $wh = Warehouse::where('code', 'WPEG')->first();

    $pfA = Product::factory()->create(['name' => 'PF A', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $mpB = Product::factory()->create(['name' => 'MP B', 'is_stockable' => true]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pfA->id, 'name' => 'BOM PF A', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mpB->id, 'label' => 'MP B', 'quantity_per_meter' => 2]);

    ProductStock::create(['product_id' => $mpB->id, 'warehouse_id' => $wh->id, 'quantity' => 50, 'reserved_quantity' => 0, 'avg_cost' => 100]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-001', 'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-09-15',
    ]);
    $order->items()->create([
        'product_id' => $pfA->id, 'description' => $pfA->name, 'quantity' => 100,
        'delivered_quantity' => 0, 'unit_price' => 1000,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
    ]);

    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'PO-PEG-1', 'status' => 'confirme', 'currency_code' => 'XOF',
        'issued_at' => now(), 'ordered_at' => now(), 'expected_at' => '2026-09-05',
    ]);
    $po->items()->create([
        'product_id' => $mpB->id, 'description' => $mpB->name, 'quantity' => 20, 'unit_price' => 100,
        'line_total_ht' => 2000, 'line_tax' => 0, 'line_total_ttc' => 2000, 'received_quantity' => 0,
    ]);

    $rows = app(MrpPeggingService::class)->explodedNetRequirements();

    expect($rows)->toHaveCount(1);
    $b = $rows->first();
    expect($b['product']->id)->toBe($mpB->id)
        ->and($b['besoin_brut'])->toBe(200.0)
        ->and($b['dispo'])->toBe(50.0)
        ->and($b['recu'])->toBe(20.0)
        ->and($b['besoin_net'])->toBe(130.0);

    expect($b['sources'])->toHaveCount(1);
    expect($b['sources']->first()['source_type'])->toBe('commande')
        ->and($b['sources']->first()['source_label'])->toBe('CMD-001')
        ->and($b['sources']->first()['quantity'])->toBe(200.0)
        ->and($b['sources']->first()['need_date'])->not->toBeNull();
});

it('agrège deux sources sur la même matière première sans les confondre dans le pegging', function () {
    $co = peggingCompany();

    $pfA = Product::factory()->create(['name' => 'PF A', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $pfC = Product::factory()->create(['name' => 'PF C', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $mpB = Product::factory()->create(['name' => 'MP B', 'is_stockable' => true]);

    $bomA = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pfA->id, 'name' => 'BOM A', 'is_active' => true]);
    $bomA->lines()->create(['product_id' => $mpB->id, 'label' => 'MP B', 'quantity_per_meter' => 2]);
    $bomC = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pfC->id, 'name' => 'BOM C', 'is_active' => true]);
    $bomC->lines()->create(['product_id' => $mpB->id, 'label' => 'MP B', 'quantity_per_meter' => 1]);

    $client = Client::factory()->create();
    foreach ([['CMD-A', $pfA, 10], ['CMD-C', $pfC, 30]] as [$num, $p, $qty]) {
        $o = Order::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'client_id' => $client->id, 'number' => $num, 'status' => 'confirme', 'issued_at' => now(),
        ]);
        $o->items()->create([
            'product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty,
            'delivered_quantity' => 0, 'unit_price' => 1000,
            'line_total_ht' => $qty * 1000, 'line_tax' => 0, 'line_total_ttc' => $qty * 1000,
        ]);
    }

    $rows = app(MrpPeggingService::class)->explodedNetRequirements();

    expect($rows)->toHaveCount(1);
    $b = $rows->first();
    // 10*2 (CMD-A) + 30*1 (CMD-C) = 50, deux sources distinctes conservées.
    expect($b['besoin_brut'])->toBe(50.0)
        ->and($b['sources'])->toHaveCount(2);
    expect($b['sources']->pluck('source_label')->sort()->values()->all())->toBe(['CMD-A', 'CMD-C']);
});

// [PROD-01 — Phase 5] Phasage temporel : une réception attendue APRÈS le
// besoin le plus proche ne doit jamais l'éteindre silencieusement.
it('une réception attendue après le besoin ne couvre pas le besoin à temps', function () {
    $co = peggingCompany();

    $pfA = Product::factory()->create(['name' => 'PF A', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $mpB = Product::factory()->create(['name' => 'MP B', 'is_stockable' => true]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pfA->id, 'name' => 'BOM PF A', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mpB->id, 'label' => 'MP B', 'quantity_per_meter' => 1]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-LATE', 'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-09-01',
    ]);
    $order->items()->create([
        'product_id' => $pfA->id, 'description' => $pfA->name, 'quantity' => 100,
        'delivered_quantity' => 0, 'unit_price' => 1000,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
    ]);

    // Réception attendue APRÈS la date de besoin (2026-09-10 > 2026-09-01).
    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'PO-LATE', 'status' => 'confirme', 'currency_code' => 'XOF',
        'issued_at' => now(), 'ordered_at' => now(), 'expected_at' => '2026-09-10',
    ]);
    $po->items()->create([
        'product_id' => $mpB->id, 'description' => $mpB->name, 'quantity' => 100, 'unit_price' => 100,
        'line_total_ht' => 10000, 'line_tax' => 0, 'line_total_ttc' => 10000, 'received_quantity' => 0,
    ]);

    $b = app(MrpPeggingService::class)->explodedNetRequirements()->first();

    expect($b['late_supply'])->toBeTrue()
        ->and($b['besoin_net'])->toBe(0.0)          // global : la réception couvre bien, tôt ou tard
        ->and($b['besoin_net_a_temps'])->toBe(100.0); // à temps : la réception tardive ne compte pas
});

it('une réception attendue à temps ou avant le besoin couvre normalement', function () {
    $co = peggingCompany();

    $pfA = Product::factory()->create(['name' => 'PF A', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $mpB = Product::factory()->create(['name' => 'MP B', 'is_stockable' => true]);
    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pfA->id, 'name' => 'BOM PF A', 'is_active' => true]);
    $bom->lines()->create(['product_id' => $mpB->id, 'label' => 'MP B', 'quantity_per_meter' => 1]);

    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-OK', 'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-09-10',
    ]);
    $order->items()->create([
        'product_id' => $pfA->id, 'description' => $pfA->name, 'quantity' => 100,
        'delivered_quantity' => 0, 'unit_price' => 1000,
        'line_total_ht' => 100000, 'line_tax' => 0, 'line_total_ttc' => 100000,
    ]);

    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'PO-OK', 'status' => 'confirme', 'currency_code' => 'XOF',
        'issued_at' => now(), 'ordered_at' => now(), 'expected_at' => '2026-09-01',
    ]);
    $po->items()->create([
        'product_id' => $mpB->id, 'description' => $mpB->name, 'quantity' => 100, 'unit_price' => 100,
        'line_total_ht' => 10000, 'line_tax' => 0, 'line_total_ttc' => 10000, 'received_quantity' => 0,
    ]);

    $b = app(MrpPeggingService::class)->explodedNetRequirements()->first();

    expect($b['late_supply'])->toBeFalse()
        ->and($b['besoin_net_a_temps'])->toBe(0.0);
});
