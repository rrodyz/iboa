<?php

/**
 * [GLOBAL-E2E-PROD-QA §49-57,85] MRP (besoin > stock disponible → shortage
 * → suggestion PR) sur dataset QA. Le cycle MTS complet (seuil → OF SANS
 * commande client → production → stock PF → vente servie sur stock) est déjà
 * PROUVÉ par E2eMtoApprovedAndMtsTest.php scénario C (même repo, mécanique
 * identique) — non reproduit ici pour éviter la redondance ; cité comme
 * preuve dans le rapport final.
 *
 * FINDING confirmé par lecture de code (déjà noté en REACT-01D, revérifié
 * ici) : MrpService::analyze() est un écran de rupture bobine PLAT
 * (Coil.remaining_weight agrégé vs Product.stock_min), PAS un moteur MRP
 * multi-niveau avec gross/net requirements et peggings — ne pas décrire A3
 * comme ayant un MRP multi-niveau dans le rapport final sans le requalifier.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Services\MrpService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qaMrpAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-E2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QA-E2E-PROD Co'], ['email' => 'qa-e2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    return $u;
}

it('QA-MP-COIL — besoin matière > stock disponible : shortage détecté, suggestion PR générée', function () {
    $u = qaMrpAdmin();
    $this->actingAs($u);
    $co = Company::first();

    $matiere = Product::factory()->create(['reference' => 'QA-MP-COIL-MRP', 'name' => 'Bobine acier QA MRP', 'is_stockable' => true, 'stock_min' => 2000]);
    Coil::create(['company_id' => $co->id, 'product_id' => $matiere->id, 'reference' => 'QA-COIL-MRP-1', 'initial_weight' => 1000, 'remaining_weight' => 500, 'cost_per_kg' => 600, 'purchase_price' => 600_000, 'status' => 'disponible']);
    Coil::create(['company_id' => $co->id, 'product_id' => $matiere->id, 'reference' => 'QA-COIL-MRP-2', 'initial_weight' => 1000, 'remaining_weight' => 300, 'cost_per_kg' => 600, 'purchase_price' => 600_000, 'status' => 'disponible']);

    // available = 800 < stock_min 2000 → déficit 1200.
    $shortfalls = app(MrpService::class)->analyze();
    $row = $shortfalls->firstWhere('product_id', $matiere->id);
    expect($row)->not->toBeNull()
        ->and($row['deficit'])->toEqual(1200.0);

    // MRP → Demande d'achat (aucun vrai achat externe déclenché, DA interne seulement).
    $this->post(route('production.mrp.generate'), ['product_ids' => [$matiere->id]])->assertRedirect();
    $pr = PurchaseRequest::latest('id')->first();
    expect($pr)->not->toBeNull()
        ->and($pr->items()->where('product_id', $matiere->id)->exists())->toBeTrue();
});

it('QA — article au-dessus du seuil : aucun shortage déclaré (pas de faux positif)', function () {
    $u = qaMrpAdmin();
    $this->actingAs($u);
    $co = Company::first();

    $matiere = Product::factory()->create(['reference' => 'QA-MP-COIL-OK', 'stock_min' => 500]);
    Coil::create(['company_id' => $co->id, 'product_id' => $matiere->id, 'reference' => 'QA-COIL-OK-1', 'initial_weight' => 1000, 'remaining_weight' => 900, 'cost_per_kg' => 500, 'purchase_price' => 500_000, 'status' => 'disponible']);

    $shortfalls = app(MrpService::class)->analyze();
    expect($shortfalls->firstWhere('product_id', $matiere->id))->toBeNull();
});
