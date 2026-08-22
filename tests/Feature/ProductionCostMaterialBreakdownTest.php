<?php

/**
 * [Ventilation analytique — CDC §6 récupérable/perdu] La déclaration de chute
 * (ProductionWaste) ne génère aucun mouvement de stock : elle valorise, au coût
 * déjà consommé, une PARTIE de la matière déjà comptée dans material_cost — elle
 * ne s'y ajoute jamais. Preuve empirique : TmpMaterialBalanceTest (bobine_restant_kg
 * et material_cost strictement identiques avant/après déclaration de chute).
 *
 * Ce test verrouille l'invariant : gross_material_cost = material_cost,
 * useful_material_cost = gross - waste_cost, et surtout material_cost/total_cost
 * restent INCHANGÉS par la déclaration de chute (jamais 400000 + 80000 = 480000).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionCostService;
use App\Modules\Production\Services\ProductionStockService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('ventile material_cost en gross/waste/useful sans jamais additionner la chute au total', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'MCB'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MCB Co'], ['email' => 'mcb@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    $wh = Warehouse::firstOrCreate(['company_id' => $co->id, 'code' => 'WH-MCB'], [
        'name' => 'WH MCB', 'is_active' => true, 'is_default' => true,
    ]);

    $mp = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);
    $pf = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp']);

    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $mp->id, 'reference' => 'COIL-MCB-001',
        'initial_weight' => 1000, 'remaining_weight' => 1000, 'cost_per_kg' => 500,
        'purchase_price' => 500000, 'status' => 'disponible',
    ]);

    $order = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'OF-MCB-001', 'status' => 'en_cours',
        'quantity_requested' => 100, 'quantity_produced' => 0, 'product_id' => $pf->id,
    ]);

    app(CoilConsumptionService::class)->consume($order, $coil, 800.0);

    $avantChute = app(ProductionCostService::class)->compute($order->fresh(), ['overhead_rate' => 0]);
    expect((int) $avantChute->material_cost)->toBe(400000);
    expect((int) $avantChute->gross_material_cost)->toBe(400000);
    expect((int) $avantChute->waste_cost)->toBe(0);
    expect((int) $avantChute->useful_material_cost)->toBe(400000);

    app(ProductionStockService::class)->recordWaste($order->fresh(), [
        'type' => 'non_reutilisable', 'weight' => 160,
        'reason' => 'Chute de refente 1250 → 1000 utiles',
    ]);

    $apresChute = app(ProductionCostService::class)->compute($order->fresh(), ['overhead_rate' => 0]);

    // Jamais 400000 + 80000. material_cost/total_cost inchangés par la chute.
    expect((int) $apresChute->material_cost)->toBe(400000);
    expect((int) $apresChute->total_cost)->toBe((int) $avantChute->total_cost);

    // La ventilation, elle, bouge : gross reste 400000, waste=80000, useful=320000.
    expect((int) $apresChute->gross_material_cost)->toBe(400000);
    expect((int) $apresChute->waste_cost)->toBe(80000);
    expect((int) $apresChute->useful_material_cost)->toBe(320000);
});
