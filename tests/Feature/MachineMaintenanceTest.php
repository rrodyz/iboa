<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Services\MaintenanceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MT'], ['email' => 'mt@mt.io', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

function mtMachine(): ProductionMachine
{
    return ProductionMachine::create(['company_id' => Company::first()->id, 'code' => 'MX' . rand(100, 999), 'name' => 'Profileuse', 'type' => 'profilage', 'status' => 'active', 'is_active' => true]);
}

it('starts and finishes an intervention, toggling machine status', function () {
    $this->actingAs(mtAdmin());
    $machine = mtMachine();
    $m = MachineMaintenance::create(['company_id' => $machine->company_id, 'machine_id' => $machine->id, 'type' => 'corrective', 'title' => 'Panne moteur', 'status' => 'planifie']);

    $svc = app(MaintenanceService::class);
    $svc->start($m);
    expect($m->fresh()->status)->toBe('en_cours');
    expect($machine->fresh()->status)->toBe('maintenance');

    $svc->finish($m, 120, 45000);
    expect($m->fresh()->status)->toBe('termine');
    expect((float) $m->fresh()->downtime_minutes)->toEqual(120.0);
    expect($machine->fresh()->status)->toBe('active');
});

it('computes availability, MTBF and MTTR', function () {
    $this->actingAs(mtAdmin());
    $machine = mtMachine();
    // 2 pannes correctives terminées, 60 min downtime chacune (total 120)
    foreach ([1, 2] as $i) {
        MachineMaintenance::create(['company_id' => $machine->company_id, 'machine_id' => $machine->id, 'type' => 'corrective', 'title' => "P$i", 'status' => 'termine', 'downtime_minutes' => 60, 'ended_at' => now()->subDays($i)]);
    }
    $k = app(MaintenanceService::class)->machineKpis($machine, 30);
    expect($k['failures'])->toBe(2);
    // période 30j = 43200 min ; uptime = 43200-120 = 43080 ; dispo = 99.7%
    expect($k['availability'])->toEqual(99.7);
    expect($k['mttr_h'])->toEqual(1.0); // 120 min / 2 = 60 min = 1 h
});

it('flags preventive maintenance due', function () {
    $this->actingAs(mtAdmin());
    $machine = mtMachine();
    $machine->update(['maintenance_frequency_days' => 30]);
    // dernière préventive il y a 40 j -> due
    MachineMaintenance::create(['company_id' => $machine->company_id, 'machine_id' => $machine->id, 'type' => 'preventive', 'title' => 'Graissage', 'status' => 'termine', 'ended_at' => now()->subDays(40)]);

    $due = app(MaintenanceService::class)->dueList();
    expect(collect($due)->pluck('id'))->toContain($machine->id);
});

it('renders maintenance index', function () {
    $this->actingAs(mtAdmin());
    mtMachine();
    $this->get(route('production.maintenance.index'))->assertOk()->assertSee('Maintenance machines');
});
