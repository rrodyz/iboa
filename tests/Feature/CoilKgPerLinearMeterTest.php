<?php

/**
 * [PRO — kgPerLinearMeter §12-20] Ordre de priorité strict :
 * 1. bobine, 2. lot, 3. article, 4. déduction physique, 5. fallback géométrique
 * (largeur × épaisseur BOBINE × densité article), 6. null.
 *
 * Formule géométrique vérifiée sur l'acier (densité seedée à 7.850 kg/dm³ dans
 * ArticlesSageSeeder) : kg/m = largeur_mm × épaisseur_mm × densité_kg/dm³ / 1000.
 * Une tôle 1250mm × 0,40mm en acier pèse 3,925 kg/ml — cohérent avec les
 * abaques poids/m² du métier (0,40mm acier ≈ 3,14 kg/m²).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\User;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\CoilConsumptionService;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

function klmDecor(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'KLM'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'KLM Co'], ['email' => 'klm@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id]);
    test()->actingAs($u);

    $mp = Product::factory()->create(['is_stockable' => true]);
    // kg_per_linear_meter absent du $fillable Product : écriture directe SQL.
    DB::table('products')->where('id', $mp->id)->update(['density' => 7.850]);

    return ['co' => $co, 'mp' => $mp->fresh()];
}

it('priorise le facteur explicite de la bobine sur lot, article, physique et géométrique', function () {
    $d = klmDecor();
    $lot = StockLot::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'lot_number' => 'L-KLM-1', 'quantity' => 100, 'initial_quantity' => 100,
        'unit_cost' => 500, 'received_at' => now(), 'status' => 'disponible',
        'kg_per_linear_meter' => 3.0]);
    DB::table('products')->where('id', $d['mp']->id)->update(['kg_per_linear_meter' => 2.0]);

    $coil = Coil::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'stock_lot_id' => $lot->id, 'reference' => 'C-KLM-1',
        'initial_weight' => 1000, 'remaining_weight' => 1000, 'estimated_length' => 250,
        'width' => 1250, 'thickness' => 0.40, 'status' => 'disponible',
        'kg_per_linear_meter' => 5.0]);

    expect(app(CoilConsumptionService::class)->kgPerLinearMeter($coil))->toBe(5.0);
});

it('retombe sur le lot quand la bobine n’a pas de facteur explicite', function () {
    $d = klmDecor();
    $lot = StockLot::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'lot_number' => 'L-KLM-2', 'quantity' => 100, 'initial_quantity' => 100,
        'unit_cost' => 500, 'received_at' => now(), 'status' => 'disponible',
        'kg_per_linear_meter' => 3.0]);
    DB::table('products')->where('id', $d['mp']->id)->update(['kg_per_linear_meter' => 2.0]);

    $coil = Coil::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'stock_lot_id' => $lot->id, 'reference' => 'C-KLM-2',
        'initial_weight' => 1000, 'remaining_weight' => 1000, 'status' => 'disponible']);

    expect(app(CoilConsumptionService::class)->kgPerLinearMeter($coil))->toBe(3.0);
});

it('retombe sur l’article quand bobine et lot n’ont pas de facteur explicite', function () {
    $d = klmDecor();
    DB::table('products')->where('id', $d['mp']->id)->update(['kg_per_linear_meter' => 2.0]);

    $coil = Coil::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'reference' => 'C-KLM-3', 'initial_weight' => 1000, 'remaining_weight' => 1000,
        'status' => 'disponible']);

    expect(app(CoilConsumptionService::class)->kgPerLinearMeter($coil))->toBe(2.0);
});

it('retombe sur la déduction physique (poids initial / longueur estimée) sans aucun explicite', function () {
    $d = klmDecor();

    $coil = Coil::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'reference' => 'C-KLM-4', 'initial_weight' => 1000, 'remaining_weight' => 1000,
        'estimated_length' => 250, 'status' => 'disponible']);

    expect(app(CoilConsumptionService::class)->kgPerLinearMeter($coil))->toBe(4.0);
});

it('retombe sur le fallback géométrique (largeur × épaisseur bobine × densité article) en dernier recours', function () {
    $d = klmDecor();

    $coil = Coil::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'reference' => 'C-KLM-5', 'initial_weight' => 1000, 'remaining_weight' => 1000,
        'width' => 1250, 'thickness' => 0.40, 'status' => 'disponible']);

    // Ni facteur explicite (bobine/lot/article), ni estimated_length : seul le
    // géométrique (1250 × 0,40 × 7,850 / 1000) peut répondre.
    expect(app(CoilConsumptionService::class)->kgPerLinearMeter($coil))->toBe(3.925);
});

it('retourne null quand aucune donnée n’est exploitable', function () {
    $d = klmDecor();
    DB::table('products')->where('id', $d['mp']->id)->update(['density' => null]);

    $coil = Coil::create(['company_id' => $d['co']->id, 'product_id' => $d['mp']->id,
        'reference' => 'C-KLM-6', 'initial_weight' => 1000, 'remaining_weight' => 1000,
        'status' => 'disponible']);

    expect(app(CoilConsumptionService::class)->kgPerLinearMeter($coil))->toBeNull();
});
