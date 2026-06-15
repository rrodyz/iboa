<?php

use App\Models\Company;
use App\Models\FiscalYear;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use Database\Seeders\ProductionDemoSeeder;

uses(\Tests\Concerns\RefreshDatabase::class);

it('seeds demo production data idempotently', function () {
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    Company::firstOrCreate(['name' => 'Demo Co'], ['email' => 'demo@iboa.test', 'current_fiscal_year_id' => $fy->id]);

    $this->seed(ProductionDemoSeeder::class);
    expect(ProductionMachine::count())->toBe(3);
    expect(ProductionOrder::count())->toBe(5);
    expect(ProductionOrder::where('status', 'termine')->count())->toBe(2);

    // re-run: no duplication
    $this->seed(ProductionDemoSeeder::class);
    expect(ProductionMachine::count())->toBe(3);
});
