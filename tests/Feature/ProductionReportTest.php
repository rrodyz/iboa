<?php

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function rpCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(['name' => 'Rep Co'], ['email' => 'rep@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function rpAdmin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create(['company_id' => rpCompany()->id, 'email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

function rpSeed(): void
{
    $co      = rpCompany();
    $product = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $wh      = Warehouse::firstOrCreate(['code' => 'WH-RP'], ['name' => 'RP', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-2026-6000',
        'status' => 'en_cours', 'quantity_requested' => 10, 'product_id' => $product->id,
    ]);
    $coil = Coil::create([
        'company_id' => $co->id, 'reference' => 'BOB-RP', 'initial_weight' => 1000, 'remaining_weight' => 1000,
        'cost_per_kg' => 500, 'purchase_price' => 500000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($order, $coil, 100);
    app(ProductionStockService::class)->recordOutput($order, ['warehouse_id' => $wh->id, 'length' => 6, 'quantity' => 10, 'unit_cost' => 3000]);
    app(ProductionStockService::class)->recordWaste($order, ['type' => 'rebut', 'weight' => 10]);
}

it('renders the production dashboard', function () {
    $this->actingAs(rpAdmin());
    rpSeed();

    $this->get(route('production.dashboard'))->assertOk()->assertSee('Tableau de bord production');
});

it('renders each report type', function () {
    $this->actingAs(rpAdmin());
    rpSeed();

    foreach (['production', 'consommation', 'rendement', 'pertes', 'couts', 'client', 'machine', 'operateur'] as $type) {
        $this->get(route('production.reports', ['type' => $type]))->assertOk();
    }
});

it('exports a report to excel', function () {
    $this->actingAs(rpAdmin());
    rpSeed();

    $res = $this->get(route('production.reports', ['type' => 'consommation', 'export' => 'excel']));
    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('.xlsx');
});

it('exports a report to pdf', function () {
    $this->actingAs(rpAdmin());
    rpSeed();

    $res = $this->get(route('production.reports', ['type' => 'couts', 'export' => 'pdf']));
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('blocks reports without permission', function () {
    $co   = rpCompany();
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'production.view', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'production.report.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'viewer_only', 'guard_name' => 'web']);
    $role->syncPermissions(['production.view']);
    $user = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $this->get(route('production.reports'))->assertForbidden();
});
