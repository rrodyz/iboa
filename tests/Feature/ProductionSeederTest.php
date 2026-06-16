<?php

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Models\ProductStock;
use App\Models\Warehouse;
use Database\Seeders\ProductionSeeder;

uses(\Tests\Concerns\RefreshDatabase::class);

beforeEach(function () {
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'Seed Co'], ['email' => 'seed@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WH-MAIN'], ['name' => 'Principal', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
});

it('seeds the full production dataset', function () {
    $this->seed(ProductionSeeder::class);

    expect(ProductionMachine::count())->toBe(5);
    expect(ProductionLine::count())->toBe(3);
    expect(Coil::count())->toBe(50);
    expect(BillOfMaterial::count())->toBe(10);
    expect(ProductionOrder::count())->toBe(100);
})->group('seed');

it('produces coherent execution data and stock movements', function () {
    $this->seed(ProductionSeeder::class);

    // statuts variés
    expect(ProductionOrder::where('status', 'brouillon')->count())->toBe(15);
    expect(ProductionOrder::where('status', 'termine')->count())->toBe(40);
    expect(ProductionOrder::where('status', 'annule')->count())->toBe(10);

    // exécution réelle sur OF produits
    expect(ProductionConsumption::count())->toBeGreaterThan(0);
    expect(ProductionOutput::count())->toBeGreaterThan(0);
    expect(ProductionCost::count())->toBeGreaterThan(0);

    // entrée stock produit fini réelle
    expect(ProductStock::where('quantity', '>', 0)->count())->toBeGreaterThan(0);

    // bobines partiellement consommées
    expect(Coil::where('status', 'en_production')->count())->toBeGreaterThan(0);

    // coût total cohérent (matière+mo+machine+indirect)
    $cost = ProductionCost::where('total_cost', '>', 0)->first();
    expect($cost->total_cost)->toBe($cost->material_cost + $cost->labor_cost + $cost->machine_cost + $cost->overhead_cost);
})->group('seed');

it('is idempotent', function () {
    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    expect(ProductionMachine::count())->toBe(5);
    expect(ProductionOrder::count())->toBe(100);
})->group('seed');
