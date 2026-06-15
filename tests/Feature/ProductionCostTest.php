<?php

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function costCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'Cost Co'], ['email' => 'cost@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function costAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => costCompany()->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('computes a full cost of production', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();

    $machine = ProductionMachine::create([
        'company_id' => $co->id, 'code' => 'MX', 'name' => 'Profileuse',
        'type' => 'profilage', 'hourly_cost' => 6000, 'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L', 'name' => 'L1', 'is_active' => true]);
    $bom  = BillOfMaterial::create([
        'company_id' => $co->id, 'name' => 'Bac', 'labor_per_unit' => 200, 'machine_time_per_unit' => 3,
        'consumption_per_meter' => 3, 'standard_waste_rate' => 5, 'is_active' => true,
    ]);
    $product = Product::factory()->create(['sale_price' => 5000]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7000',
        'status' => 'en_cours', 'quantity_requested' => 10, 'quantity_produced' => 10,
        'product_id' => $product->id, 'bill_of_material_id' => $bom->id, 'production_line_id' => $line->id,
    ]);

    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-C1', 'initial_weight' => 1000,
        'remaining_weight' => 1000, 'cost_per_kg' => 500, 'purchase_price' => 500000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 100); // material = 100*500 = 50000

    // output meters for cost/meter
    $order->outputs()->create([
        'company_id' => $co->id, 'product_id' => $product->id, 'length' => 6, 'quantity' => 10,
        'total_meters' => 60, 'produced_at' => now(),
    ]);

    $cost = app(ProductionCostService::class)->compute($order, ['overhead_rate' => 10]);

    expect($cost->material_cost)->toBe(50000);          // consommé
    expect($cost->labor_cost)->toBe(2000);              // 200 * 10
    expect($cost->machine_cost)->toBe(3000);            // (3min*10)/60 * 6000 = 0.5h * 6000
    expect($cost->overhead_cost)->toBe(5500);           // 10% of (50000+2000+3000)
    expect($cost->total_cost)->toBe(60500);
    expect((float) $cost->cost_per_meter)->toEqual(round(60500 / 60, 2));
    expect((float) $cost->cost_per_unit)->toEqual(6050.0);
    expect($cost->margin)->toBe(5000 * 10 - 60500);     // revenue 50000 - 60500 = -10500
});

it('persists cost via the compute route and is idempotent', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();
    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7001',
        'status' => 'termine', 'quantity_requested' => 5, 'quantity_produced' => 5,
    ]);

    $this->post(route('production.orders.cost', $order), ['overhead_rate' => 0])->assertRedirect();
    $this->post(route('production.orders.cost', $order), ['overhead_rate' => 0])->assertRedirect();

    expect(\App\Modules\Production\Models\ProductionCost::where('production_order_id', $order->id)->count())->toBe(1);
});

it('records a quality control', function () {
    $this->actingAs(costAdmin());
    $co = costCompany();
    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-7002',
        'status' => 'en_cours', 'quantity_requested' => 5,
    ]);

    $this->post(route('production.orders.quality', $order), [
        'thickness_ok' => '1', 'length_ok' => '1', 'color_ok' => '1', 'visual_ok' => '1',
        'status' => 'conforme',
    ])->assertRedirect();

    $qc = $order->qualityControls()->first();
    expect($qc->status)->toBe('conforme');
    expect($qc->thickness_ok)->toBeTrue();
});
