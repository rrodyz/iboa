<?php

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;

uses(\Tests\Concerns\RefreshDatabase::class);

beforeEach(function () {
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    Company::firstOrCreate(['name' => 'Factory Co'], ['email' => 'fac@iboa.test', 'current_fiscal_year_id' => $fy->id]);
});

it('builds production factories', function () {
    expect(ProductionMachine::factory()->create()->exists)->toBeTrue();
    expect(ProductionLine::factory()->create()->machine_id)->not->toBeNull();
    expect(BillOfMaterial::factory()->create()->is_active)->toBeTrue();

    $coil = Coil::factory()->create();
    expect((float) $coil->remaining_weight)->toEqual((float) $coil->initial_weight);
    expect((float) $coil->cost_per_kg)->toBeGreaterThan(0);
});

it('supports production order states', function () {
    expect(ProductionOrder::factory()->create()->status)->toBe('brouillon');
    expect(ProductionOrder::factory()->inProgress()->create()->status)->toBe('en_cours');

    $done = ProductionOrder::factory()->finished()->create();
    expect($done->status)->toBe('termine');
    expect($done->finished_at)->not->toBeNull();
});

it('mass-produces via factory count', function () {
    ProductionMachine::factory()->count(5)->create();
    expect(ProductionMachine::count())->toBe(5);
});

it('builds child factories', function () {
    expect(\App\Modules\Production\Models\BomLine::factory()->create()->bill_of_material_id)->not->toBeNull();
    expect(\App\Modules\Production\Models\ProductionConsumption::factory()->create()->cost)->toBeGreaterThan(0);
    expect((float) \App\Modules\Production\Models\ProductionOutput::factory()->create()->total_meters)->toBeGreaterThan(0);
    expect(\App\Modules\Production\Models\ProductionWaste::factory()->create()->weight)->not->toBeNull();
    expect(\App\Modules\Production\Models\ProductionQualityControl::factory()->create()->status)->toBe('conforme');
    expect(\App\Modules\Production\Models\ProductionQualityControl::factory()->nonConforme()->create()->status)->toBe('non_conforme');
    expect(\App\Modules\Production\Models\ProductionCost::factory()->create()->total_cost)->toBeGreaterThan(0);
});
