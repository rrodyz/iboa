<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\RoutingService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function rtAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'RT'], ['email' => 'rt@rt.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

it('creates a routing with operations', function () {
    $this->actingAs(rtAdmin());
    $co = Company::first();
    $wc = WorkCenter::factory()->create(['company_id' => $co->id]);

    $this->post(route('production.routings.store'), [
        'code' => 'G1', 'name' => 'Gamme bac', 'is_active' => '1',
        'operations' => [
            ['name' => 'Découpe', 'work_center_id' => $wc->id, 'sequence' => 10, 'setup_minutes' => 15, 'run_minutes_per_unit' => 2],
            ['name' => 'Profilage', 'sequence' => 20, 'setup_minutes' => 10, 'run_minutes_per_unit' => 1.5],
        ],
    ])->assertRedirect(route('production.routings.index'));

    $r = Routing::where('code', 'G1')->first();
    expect($r->operations()->count())->toBe(2);
});

it('generates work orders from the BOM routing', function () {
    $this->actingAs(rtAdmin());
    $co = Company::first();
    $bom = BillOfMaterial::factory()->create(['company_id' => $co->id]);
    $routing = Routing::factory()->create(['company_id' => $co->id, 'bill_of_material_id' => $bom->id]);
    $routing->operations()->create(['sequence' => 10, 'name' => 'Découpe', 'setup_minutes' => 20, 'run_minutes_per_unit' => 3]);
    $routing->operations()->create(['sequence' => 20, 'name' => 'Soudure', 'setup_minutes' => 10, 'run_minutes_per_unit' => 2]);

    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-RT1', 'status' => 'en_cours', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 10]);

    $n = app(RoutingService::class)->generateWorkOrders($of);
    expect($n)->toBe(2);
    // Découpe planned = 20 + 3*10 = 50
    $op = $of->operations()->where('name', 'Découpe')->first();
    expect((float) $op->planned_minutes)->toEqual(50.0);
    expect($op->status)->toBe('pending');
});

it('runs work order lifecycle and computes progress', function () {
    $this->actingAs(rtAdmin());
    $co = Company::first();
    $bom = BillOfMaterial::factory()->create(['company_id' => $co->id]);
    $routing = Routing::factory()->create(['company_id' => $co->id, 'bill_of_material_id' => $bom->id]);
    $routing->operations()->create(['sequence' => 10, 'name' => 'A', 'setup_minutes' => 5, 'run_minutes_per_unit' => 1]);
    $routing->operations()->create(['sequence' => 20, 'name' => 'B', 'setup_minutes' => 5, 'run_minutes_per_unit' => 1]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-RT2', 'status' => 'en_cours', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 5]);

    $svc = app(RoutingService::class);
    $svc->generateWorkOrders($of);
    $opA = $of->operations()->where('name', 'A')->first();

    $svc->start($opA);
    expect($opA->fresh()->status)->toBe('in_progress');
    $svc->finish($opA, 12);
    expect($opA->fresh()->status)->toBe('done');
    expect((float) $opA->fresh()->real_minutes)->toEqual(12.0);

    $p = $svc->progress($of);
    expect($p['total'])->toBe(2);
    expect($p['done'])->toBe(1);
    expect($p['percent'])->toBe(50);
});

it('generates work orders via route', function () {
    $this->actingAs(rtAdmin());
    $co = Company::first();
    $bom = BillOfMaterial::factory()->create(['company_id' => $co->id]);
    $routing = Routing::factory()->create(['company_id' => $co->id, 'bill_of_material_id' => $bom->id]);
    $routing->operations()->create(['sequence' => 10, 'name' => 'X', 'setup_minutes' => 5, 'run_minutes_per_unit' => 1]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-RT3', 'status' => 'en_cours', 'bill_of_material_id' => $bom->id, 'quantity_requested' => 3]);

    $this->post(route('production.orders.operations', $of))->assertRedirect();
    expect($of->operations()->count())->toBe(1);
    // second call blocked (already generated)
    $this->post(route('production.orders.operations', $of))->assertSessionHas('error');
});
