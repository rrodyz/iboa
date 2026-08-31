<?php

/**
 * [PROD-01 — Phase 16] 5 scénarios UAT OA Metal, bout-en-bout sur les vrais
 * services/écrans livrés cette mission — pas des tests unitaires isolés.
 *
 * 1. Tôle bac MTO : commande client → tableau de bord MTO → éligibilité.
 * 2. Fer à béton MTS : écran de planification → génération d'OF.
 * 3. Rupture bobine → MRP → demande d'achat → réception → couverture.
 * 4. Commande client → besoin PF → explosion BOM → besoin net matière.
 * 5. Deux OF sur la même machine → conflit d'ordonnancement détecté.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\MrpPeggingService;
use App\Modules\Production\Services\MrpService;
use App\Modules\Production\Services\SchedulingConflictService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function uatCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'UAT-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'OA Metal UAT'], ['email' => 'uat@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'W-UAT'], ['name' => 'Dépôt UAT', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'uat_full', 'guard_name' => 'web']);
    foreach (['production.view', 'production.create', 'production.update'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $co;
}

it('UAT-1 — tôle bac MTO : commande client visible au tableau de bord MTO avec éligibilité', function () {
    $co = uatCompany();
    $toleBac = Product::factory()->create(['name' => 'Tôle bac galvanisée', 'production_mode' => 'mto']);

    $commande = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-UAT-TOLE',
        'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-09-30', 'total_ttc' => 200000,
    ]);
    $commande->items()->create([
        'product_id' => $toleBac->id, 'description' => $toleBac->name, 'quantity' => 50,
        'delivered_quantity' => 0, 'unit_price' => 4000,
        'line_total_ht' => 200000, 'line_tax' => 0, 'line_total_ttc' => 200000,
    ]);
    $commande->update(['production_approved' => true, 'production_approval_fingerprint' => $commande->productionFinancialFingerprint()]);

    // [REACT-01C] Le tableau de bord MTO est Inertia depuis cette mission —
    // plus de rendu Blade serveur des libellés "Éligible"/"Créer OF" (pas de
    // SSR). On vérifie les données transmises dont ces libellés dérivent.
    $rows = $this->get(route('production.orders.mto'))
        ->assertOk()->inertiaProps('rows');
    $row = collect($rows)->firstWhere(fn ($r) => $r['orderNumber'] === 'CMD-UAT-TOLE');

    expect($row)->not->toBeNull()
        ->and($row['productName'])->toBe('Tôle bac galvanisée')
        ->and($row['eligible'])->toBeTrue()
        ->and($row['canCreateOf'])->toBeTrue();
});

it('UAT-2 — fer à béton MTS : écran de planification propose et génère l’OF', function () {
    $co = uatCompany();
    $fer = Product::factory()->create([
        'name' => 'Fer à béton HA12', 'production_mode' => 'mts', 'is_stockable' => true, 'is_manufacturable' => true,
        'stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0,
    ]);
    ProductStock::create(['product_id' => $fer->id, 'warehouse_id' => Warehouse::where('code', 'W-UAT')->value('id'), 'quantity' => 100, 'reserved_quantity' => 0, 'avg_cost' => 350]);
    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $fer->id, 'name' => 'BOM Fer à béton', 'is_active' => true])
        ->lines()->create(['label' => 'Billette acier', 'quantity_per_meter' => 1]);

    // [REACT-01D] Écran MTS Inertia — même principe qu'UAT-1 : on vérifie la
    // donnée transmise (besoin net), pas un texte rendu côté client.
    $rows = $this->get(route('production.orders.mts'))
        ->assertOk()->inertiaProps('rows');
    $row = collect($rows)->firstWhere(fn ($r) => $r['productName'] === 'Fer à béton HA12');
    expect($row['besoin'])->toEqual(400.0); // besoin net (int côté JSON, float côté service — même valeur)

    $result = app(MrpService::class)->generateProductionOrders([$fer->id]);
    expect($result['created'])->toHaveCount(1)
        ->and((float) $result['created'][0]->quantity_requested)->toBe(400.0)
        ->and($result['created'][0]->origin)->toBe('mrp');

    // Idempotence : le besoin est désormais couvert par l'OF planifié.
    expect(app(MrpService::class)->productionProposals())->toBeEmpty();
});

it('UAT-3 — rupture de bobine : le MRP détecte, génère la demande d’achat, la réception couvre le déficit', function () {
    $co = uatCompany();
    $wh = Warehouse::where('code', 'W-UAT')->first();
    $bobine = Product::factory()->create(['name' => 'Bobine acier galvanisé 1.25', 'stock_min' => 500]);
    $lot = StockLot::create(['product_id' => $bobine->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-UAT-BOB', 'quantity' => 100, 'unit_cost' => 900, 'received_at' => now(), 'status' => 'disponible']);
    Coil::create(['company_id' => $co->id, 'product_id' => $bobine->id, 'stock_lot_id' => $lot->id, 'reference' => 'COIL-UAT-1', 'initial_weight' => 100, 'remaining_weight' => 100, 'cost_per_kg' => 900, 'status' => 'disponible']);

    $shortfalls = app(MrpService::class)->analyze();
    expect($shortfalls)->toHaveCount(1)
        ->and($shortfalls->first()['deficit'])->toBe(400.0);

    $pr = app(MrpService::class)->generatePurchaseRequest([$bobine->id]);
    expect($pr)->not->toBeNull()
        ->and((float) $pr->items->first()->quantity)->toBe(400.0);

    // Réception : nouvelle bobine physique couvrant le déficit.
    $lot2 = StockLot::create(['product_id' => $bobine->id, 'warehouse_id' => $wh->id, 'lot_number' => 'LOT-UAT-BOB-2', 'quantity' => 400, 'unit_cost' => 900, 'received_at' => now(), 'status' => 'disponible']);
    Coil::create(['company_id' => $co->id, 'product_id' => $bobine->id, 'stock_lot_id' => $lot2->id, 'reference' => 'COIL-UAT-2', 'initial_weight' => 400, 'remaining_weight' => 400, 'cost_per_kg' => 900, 'status' => 'disponible']);

    expect(app(MrpService::class)->analyze())->toBeEmpty();
});

it('UAT-4 — commande tôle bac → explosion BOM → besoin net de bobine matière première', function () {
    $co = uatCompany();
    $toleBac = Product::factory()->create(['name' => 'Tôle bac 60/100', 'production_mode' => 'mto', 'is_manufacturable' => true]);
    $bobine = Product::factory()->create(['name' => 'Bobine galvanisée 60/100', 'is_stockable' => true]);

    BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $toleBac->id, 'name' => 'BOM Tôle bac 60/100', 'is_active' => true])
        ->lines()->create(['product_id' => $bobine->id, 'label' => 'Bobine', 'quantity_per_meter' => 3]);

    ProductStock::create(['product_id' => $bobine->id, 'warehouse_id' => Warehouse::where('code', 'W-UAT')->value('id'), 'quantity' => 80, 'reserved_quantity' => 0, 'avg_cost' => 900]);

    $commande = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-UAT-EXPL',
        'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-09-15',
    ]);
    $commande->items()->create([
        'product_id' => $toleBac->id, 'description' => $toleBac->name, 'quantity' => 40,
        'delivered_quantity' => 0, 'unit_price' => 5000,
        'line_total_ht' => 200000, 'line_tax' => 0, 'line_total_ttc' => 200000,
    ]);

    $besoins = app(MrpPeggingService::class)->explodedNetRequirements();

    expect($besoins)->toHaveCount(1);
    $b = $besoins->first();
    expect($b['product']->name)->toBe('Bobine galvanisée 60/100')
        ->and($b['besoin_brut'])->toBe(120.0)   // 40 tôles × 3 bobine/tôle
        ->and($b['dispo'])->toBe(80.0)
        ->and($b['besoin_net'])->toBe(40.0)     // 120 − 80
        ->and($b['sources']->first()['source_label'])->toBe('CMD-UAT-EXPL');
});

it('UAT-5 — deux OF planifiés sur la même machine profileuse : conflit détecté', function () {
    $co = uatCompany();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M1-UAT', 'name' => 'Profileuse M1', 'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true]);
    $ligne = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L-M1-UAT', 'name' => 'Ligne profilage M1', 'is_active' => true]);

    $creerOf = fn (string $num, string $debut, string $fin) => ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => $num,
        'status' => 'lance', 'quantity_requested' => 200, 'production_line_id' => $ligne->id,
        'date_debut_prevue' => explode(' ', $debut)[0], 'heure_debut_prevue' => explode(' ', $debut)[1],
        'date_fin_prevue' => explode(' ', $fin)[0], 'heure_fin_prevue' => explode(' ', $fin)[1],
    ]);

    $creerOf('OF-UAT-TOLE-1', '2026-09-02 08:00', '2026-09-02 12:00');
    $creerOf('OF-UAT-TOLE-2', '2026-09-02 10:00', '2026-09-02 14:00');

    $conflits = app(SchedulingConflictService::class)->detect();

    expect($conflits)->toHaveCount(1)
        ->and($conflits->first()['resource_label'])->toBe('Ligne profilage M1')
        ->and($conflits->first()['overlap_minutes'])->toBe(120.0);

    // [REACT-01E] Page Inertia — on vérifie le conflit transmis dans props,
    // pas un texte "conflit(s) d'ordonnancement détecté(s)" rendu serveur.
    $this->get(route('production.planning'))->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('conflicts.0.resourceLabel', 'Ligne profilage M1')
            ->where('conflicts.0.overlapMinutes', 120)
            ->etc()
        );
});
