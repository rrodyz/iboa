<?php

/**
 * [PROD-01 — Phase 11] Tableau de bord MTO : remplace le PLACEHOLDER —
 * affiche CHAQUE ligne de commande MTO ouverte, avec ou sans OF, quantité
 * déjà produite / restante, stock disponible, statut financier, date requise.
 * Distinct de l'écran « Éligibles » (P7.4 function #2), qui ne montre QUE
 * les commandes sans OF encore.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtoDashCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTOD-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MtoDash Co'], ['email' => 'mtodash@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    Warehouse::firstOrCreate(['code' => 'WMTOD'], ['name' => 'WMTOD', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function mtoDashOrder(Company $co, Product $product, float $qty, float $delivered = 0, array $over = []): Order
{
    $order = Order::create(array_merge([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id, 'number' => 'CMD-MTOD-'.uniqid(),
        'status' => 'confirme', 'issued_at' => now(), 'delivery_date' => '2026-09-20', 'total_ttc' => $qty * 4000,
    ], $over));
    $order->items()->create([
        'product_id' => $product->id, 'description' => $product->name, 'quantity' => $qty,
        'delivered_quantity' => $delivered, 'unit_price' => 4000,
        'line_total_ht' => $qty * 4000, 'line_tax' => 0, 'line_total_ttc' => $qty * 4000,
    ]);

    return $order;
}

it('affiche une ligne MTO sans OF, quantité produite nulle, non éligible par défaut', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac A', 'production_mode' => 'mto']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => Warehouse::where('code', 'WMTOD')->value('id'), 'quantity' => 15, 'reserved_quantity' => 5, 'avg_cost' => 1000]);
    $order = mtoDashOrder($co, $p, 100);

    $this->get(route('production.orders.mto'))->assertOk()
        ->assertSee($order->number)
        ->assertSee('Tôle bac A')
        ->assertSee('Aucun'); // OF existant
});

it('calcule produite/restante depuis les OF réels liés à la commande et à l’article', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac B', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 100);

    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-MTOD-1',
        'status' => 'en_cours', 'quantity_requested' => 100, 'quantity_produced' => 40,
        'product_id' => $p->id, 'order_id' => $order->id,
    ]);

    $response = $this->get(route('production.orders.mto'))->assertOk();
    $response->assertSeeInOrder(['OF-MTOD-1']);
});

it('n’affiche pas une ligne MTO entièrement livrée', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac C', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 50, delivered: 50);

    $this->get(route('production.orders.mto'))->assertOk()->assertDontSee($order->number);
});

it('n’affiche jamais un article MTS sur le tableau de bord MTO', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Fer à béton', 'production_mode' => 'mts']);
    $order = mtoDashOrder($co, $p, 20);

    $this->get(route('production.orders.mto'))->assertOk()->assertDontSee($order->number);
});

it('marque une commande approuvée gérant comme éligible et affiche le lien Créer OF', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac D', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 30);
    $order->update(['production_approved' => true, 'production_approval_fingerprint' => $order->productionFinancialFingerprint()]);

    $this->get(route('production.orders.mto'))->assertOk()
        ->assertSee('Éligible')
        ->assertSee('Créer OF');
});

// [Clôture PROD-01 — section 8] Commande ENTIÈREMENT produite (quantity_produced
// == quantity commandée) mais PAS ENCORE livrée : reste visible (delivered_quantity
// < quantity, donc pas filtrée), restante = 0, plus de lien "Créer OF" — distinct
// du cas "entièrement livrée" (déjà couvert, celui-là disparaît du tableau).
it('commande entièrement PRODUITE mais pas encore livrée : reste visible, restante = 0, pas de « Créer OF »', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac produite', 'production_mode' => 'mto']);
    $order = mtoDashOrder($co, $p, 50); // delivered_quantity = 0 par défaut
    $order->update(['production_approved' => true, 'production_approval_fingerprint' => $order->productionFinancialFingerprint()]);

    ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-MTOD-PROD',
        'status' => 'termine', 'quantity_requested' => 50, 'quantity_produced' => 50,
        'product_id' => $p->id, 'order_id' => $order->id,
    ]);

    $response = $this->get(route('production.orders.mto'))->assertOk();
    $response->assertSee($order->number)      // toujours visible : pas encore livrée
        ->assertSee('OF-MTOD-PROD');
    $response->assertDontSee('Créer OF');      // restante = 0 -> plus d'action de création
});

// Blocage financier : commande MTO ouverte, NI approuvée gérant NI réglée ->
// visible mais marquée "Non couverte", jamais de lien "Créer OF".
it('blocage financier : commande MTO ni approuvée ni réglée -> « Non couverte », jamais de « Créer OF »', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac bloquée', 'production_mode' => 'mto']);
    // Client CASH explicite : le comptant doit payer d'avance (règle fail-closed
    // de ProductionFinancialEligibilityService) — un client CREDIT par défaut
    // (factory) serait éligible dès lors que sa limite de crédit couvre la
    // commande, ce qui n'est PAS un blocage et aurait donné un faux négatif ici.
    $client = \App\Models\Client::factory()->create(['payment_mode' => 'cash']);
    $order = mtoDashOrder($co, $p, 25, over: ['client_id' => $client->id]); // aucune approbation, aucun règlement

    $response = $this->get(route('production.orders.mto'))->assertOk();
    $response->assertSee($order->number)
        ->assertSee('Non couverte');
    $response->assertDontSee('Créer OF');
});

// Quantité en stock disponible affichée : dispo = quantity − reserved_quantity,
// jamais la quantité physique brute.
it('affiche le stock disponible réel (quantité − réservé), pas la quantité physique brute', function () {
    $co = mtoDashCompany();
    $p = Product::factory()->create(['name' => 'Tôle bac stock', 'production_mode' => 'mto']);
    ProductStock::create(['product_id' => $p->id, 'warehouse_id' => Warehouse::where('code', 'WMTOD')->value('id'), 'quantity' => 40, 'reserved_quantity' => 15, 'avg_cost' => 1000]);
    mtoDashOrder($co, $p, 10);

    // 40 − 15 = 25 disponible, jamais 40.
    $this->get(route('production.orders.mto'))->assertOk()->assertSee('25');
});
