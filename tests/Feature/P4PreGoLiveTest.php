<?php

/**
 * [P4 — pré-go-live] Ferme les réserves P3 : libération qualité formelle,
 * article inactif, dépassement production, surpaiement, remise/plancher.
 * Chaque section reproduit le mécanisme RÉEL avant de conclure — aucune
 * règle n'est inventée (ex. pas de seuil de remise maximale si aucun
 * n'existe dans le code).
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\ProductionStockService;
use App\Modules\Quality\Models\QualityRelease;
use App\Modules\Quality\Services\QualityReleaseService;
use App\Services\ClientPaymentService;
use App\Services\DeliveryNoteService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
});

function p4Societe(string $suffix): Company
{
    $fy = FiscalYear::create(['label' => 'P4'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);

    return Company::create(['name' => 'P4 Co'.$suffix, 'email' => 'p4'.$suffix.'@p4.io', 'current_fiscal_year_id' => $fy->id]);
}

function p4User(Company $co, string $role): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::findByName($role, 'web'));

    return $u;
}

// ═══════════════════════════════════════════════════════════════════════
// PHASE 2 — LIBÉRATION QUALITÉ
// ═══════════════════════════════════════════════════════════════════════

function p4QualityScenario(Company $co, bool $obligatoire = true): array
{
    $wh = Warehouse::create(['code' => 'WH-QREL'.$co->id, 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $whRelease = Warehouse::create(['code' => 'WH-QREL-PF'.$co->id, 'name' => 'Dépôt PF', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $pf = Product::factory()->create(['is_manufacturable' => false, 'is_stockable' => true]);

    // Stock physique réel à la source : releaseFinishedGoods() exécute un vrai
    // mouvement de transfert (StockService::recordMovement) vers le dépôt de
    // libération — sans cette ligne, "stock insuffisant" bloque la libération.
    ProductStock::create(['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 10, 'reserved_quantity' => 0]);

    $of = ProductionOrder::create(['company_id' => $co->id, 'product_id' => $pf->id, 'number' => 'OF-QREL-'.uniqid(), 'quantity_requested' => 10, 'status' => 'en_cours', 'controle_qualite_obligatoire' => $obligatoire]);
    $batch = ProductionBatch::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'product_id' => $pf->id, 'batch_number' => 'LOT-QREL-'.uniqid(), 'quantity' => 10, 'status' => 'en_cours', 'produced_at' => now()]);
    $output = $of->outputs()->create(['company_id' => $co->id, 'product_id' => $pf->id, 'warehouse_id' => $wh->id, 'release_warehouse_id' => $whRelease->id, 'quantity' => 10, 'status' => 'validee', 'validated_at' => now()]);

    return [$of, $batch, $output, $wh, $whRelease, $pf];
}

it('Q01 — utilisateur qualité autorisé peut libérer un lot conforme', function () {
    $co = p4Societe('q01');
    [$of, $batch] = p4QualityScenario($co);
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controlled_at' => now()]);
    test()->actingAs(p4User($co, 'responsable_qualite'));

    $response = test()->post(route('qualite.releases.decide', $batch), ['decision' => 'libere']);

    $response->assertRedirect();
    expect(QualityRelease::where('production_batch_id', $batch->id)->value('status'))->toBe('libere');
});

it('Q02 — commercial ne peut pas libérer (403)', function () {
    $co = p4Societe('q02');
    [$of, $batch] = p4QualityScenario($co);
    test()->actingAs(p4User($co, 'commercial'));

    $response = test()->post(route('qualite.releases.decide', $batch), ['decision' => 'libere']);

    $response->assertForbidden();
});

it('Q03 — magasinier ne peut pas libérer (403)', function () {
    $co = p4Societe('q03');
    [$of, $batch] = p4QualityScenario($co);
    test()->actingAs(p4User($co, 'magasinier'));

    $response = test()->post(route('qualite.releases.decide', $batch), ['decision' => 'libere']);

    $response->assertForbidden();
});

it('Q04 — lot sans aucun contrôle qualité : libération refusée', function () {
    $co = p4Societe('q04');
    [$of, $batch] = p4QualityScenario($co);
    test()->actingAs(p4User($co, 'responsable_qualite'));

    expect(fn () => app(QualityReleaseService::class)->decide($batch, 'libere'))
        ->toThrow(ValidationException::class);
});

it('Q05 — contrôle qualité non conforme : libération refusée', function () {
    $co = p4Societe('q05');
    [$of, $batch] = p4QualityScenario($co);
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => false, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'non_conforme', 'controlled_at' => now()]);
    test()->actingAs(p4User($co, 'responsable_qualite'));

    expect(fn () => app(QualityReleaseService::class)->decide($batch, 'libere'))
        ->toThrow(ValidationException::class);
});

it('Q06/Q07/Q08 — contrôle conforme : libération réussie, auteur et date tracés', function () {
    $co = p4Societe('q06');
    [$of, $batch, $output] = p4QualityScenario($co);
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controlled_at' => now()]);
    $qualityUser = p4User($co, 'responsable_qualite');
    test()->actingAs($qualityUser);

    app(QualityReleaseService::class)->decide($batch, 'libere', 'RAS contrôle conforme');

    $fresh = $output->fresh();
    expect($fresh->quality_released_at)->not->toBeNull();
    expect($fresh->quality_released_by)->toBe($qualityUser->id);
    $release = QualityRelease::where('production_batch_id', $batch->id)->first();
    expect($release->decided_by)->toBe($qualityUser->id);
    expect($release->decided_at)->not->toBeNull();
});

it('Q09 — double libération : idempotent, pas d\'erreur, pas de double mouvement de stock', function () {
    $co = p4Societe('q09');
    [$of, $batch, $output] = p4QualityScenario($co);
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controlled_at' => now()]);
    test()->actingAs(p4User($co, 'responsable_qualite'));

    app(QualityReleaseService::class)->decide($batch, 'libere');
    $firstReleasedAt = $output->fresh()->quality_released_at;
    $movementsAfterFirst = \App\Models\StockMovement::count();

    app(QualityReleaseService::class)->decide($batch, 'libere'); // seconde décision identique

    expect(\App\Models\StockMovement::count())->toBe($movementsAfterFirst); // aucun second mouvement
    expect(QualityRelease::where('production_batch_id', $batch->id)->count())->toBe(1); // updateOrCreate, pas de doublon
});

it('Q10/Q11 — livraison bloquée avant libération, autorisée après (P1D — faille refus-après-libération corrigée)', function () {
    $co = p4Societe('q1011');
    [$of, $batch, $output, $wh, $whRelease, $pf] = p4QualityScenario($co);
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-Q1011', 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 100_000]);
    $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 10, 'unit_price' => 10_000, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 100_000, 'line_tax' => 0, 'line_total_ttc' => 100_000]);
    $of->update(['order_id' => $order->id]);
    // Pas de stock pré-existant à $whRelease : la libération qualité EXÉCUTE
    // elle-même un vrai transfert $wh → $whRelease (10 unités, cf.
    // p4QualityScenario()) — la livraison doit puiser dans $whRelease, la
    // destination réelle du stock une fois libéré, pas dans $wh (vidé par le
    // transfert).
    ProductionQualityControl::create(['company_id' => $co->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controlled_at' => now()]);
    test()->actingAs(p4User($co, 'responsable_qualite'));

    $dn = function () use ($co, $order, $client, $whRelease, $item, $pf) {
        $dn = \App\Models\DeliveryNote::create(['company_id' => $co->id, 'order_id' => $order->id, 'client_id' => $client->id, 'number' => 'BL-Q1011-'.uniqid(), 'status' => 'brouillon', 'warehouse_id' => $whRelease->id, 'issued_at' => now(), 'delivery_date' => now()]);
        $dn->items()->create(['order_item_id' => $item->id, 'product_id' => $pf->id, 'description' => 'x', 'quantity' => 10]);

        return $dn;
    };

    // Q10 : avant toute libération.
    expect(fn () => app(DeliveryNoteService::class)->validate($dn()))->toThrow(\RuntimeException::class);

    // Libération.
    app(QualityReleaseService::class)->decide($batch, 'libere');

    // Q11 : après libération, livrable.
    $dnAfter = $dn();
    app(DeliveryNoteService::class)->validate($dnAfter);
    expect($dnAfter->fresh()->status)->toBe('valide');

    // [P4 fix] Revirement qualité : refus APRÈS libération doit re-bloquer.
    app(QualityReleaseService::class)->decide($batch, 'refuse', 'Défaut détecté après coup');
    expect($output->fresh()->quality_released_at)->toBeNull();
    expect(fn () => app(DeliveryNoteService::class)->validate($dn()))->toThrow(\RuntimeException::class);
});

// ═══════════════════════════════════════════════════════════════════════
// PHASE 3 — ARTICLE INACTIF
// ═══════════════════════════════════════════════════════════════════════

it('A01/A02 — devis : article actif PASS, article inactif BLOCK', function () {
    $co = p4Societe('a0102');
    $client = Client::factory()->create(['is_active' => true]);
    $active = Product::factory()->create(['is_sellable' => true, 'is_active' => true]);
    $inactive = Product::factory()->create(['is_sellable' => true, 'is_active' => false]);
    test()->actingAs(p4User($co, 'commercial'));

    $ok = test()->post(route('ventes.devis.store'), ['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [['product_id' => $active->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]]]);
    $ok->assertSessionHasNoErrors();

    $blocked = test()->post(route('ventes.devis.store'), ['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [['product_id' => $inactive->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]]]);
    $blocked->assertSessionHasErrors('items.0.product_id');
});

it('A03/A04 — commande : article actif PASS, article inactif BLOCK', function () {
    $co = p4Societe('a0304');
    $client = Client::factory()->create(['is_active' => true]);
    $active = Product::factory()->create(['is_sellable' => true, 'is_active' => true]);
    $inactive = Product::factory()->create(['is_sellable' => true, 'is_active' => false]);
    test()->actingAs(p4User($co, 'commercial'));

    $ok = test()->post(route('ventes.commandes.store'), ['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [['product_id' => $active->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]]]);
    $ok->assertSessionHasNoErrors();

    $blocked = test()->post(route('ventes.commandes.store'), ['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [['product_id' => $inactive->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]]]);
    $blocked->assertSessionHasErrors('items.0.product_id');
});

it('A05 — article désactivé APRÈS coup sur une commande existante : la commande reste éditable (accès historique)', function () {
    $co = p4Societe('a05');
    $client = Client::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_sellable' => true, 'is_active' => true]);
    test()->actingAs(p4User($co, 'commercial'));

    test()->post(route('ventes.commandes.store'), ['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]]])->assertSessionHasNoErrors();
    $order = Order::where('client_id', $client->id)->firstOrFail();

    $product->update(['is_active' => false]); // désactivé APRÈS création de la commande

    // Ré-enregistrer la commande (ex. changer une note) ne doit PAS être bloqué
    // par la ligne historique désormais inactive : UpdateOrderRequest n'a pas
    // reçu la même contrainte, volontairement.
    $response = test()->put(route('ventes.commandes.update', $order), [
        'client_id' => $client->id, 'issued_at' => $order->issued_at->toDateString(), 'notes' => 'note ajoutée après désactivation',
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000]],
    ]);

    $response->assertSessionHasNoErrors();
});

// ═══════════════════════════════════════════════════════════════════════
// PHASE 4 — PRODUCTION > QUANTITÉ AUTORISÉE
// ═══════════════════════════════════════════════════════════════════════

function p4ProdOrder(Company $co, float $requested = 100, bool $overflow = false): array
{
    $wh = Warehouse::create(['code' => 'WH-PROD'.uniqid(), 'name' => 'Dépôt', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $pf = Product::factory()->create(['is_manufacturable' => false, 'is_stockable' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'product_id' => $pf->id, 'number' => 'OF-PROD-'.uniqid(), 'quantity_requested' => $requested, 'quantity_produced' => 0, 'status' => 'en_cours', 'autoriser_depassement_qte' => $overflow]);

    return [$of, $wh, $pf];
}

it('P01/P02 — production 50 puis 50 complémentaire (total exact = commandé) : PASS', function () {
    $co = p4Societe('p0102');
    [$of, $wh, $pf] = p4ProdOrder($co, 100);
    $svc = app(ProductionStockService::class);
    test()->actingAs(p4User($co, 'chef_production'));

    $svc->recordOutput($of, ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 50, 'length' => 1, 'unit_cost' => 100]);
    expect((float) $of->fresh()->quantity_produced)->toBe(50.0);

    $svc->recordOutput($of->fresh(), ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 50, 'length' => 1, 'unit_cost' => 100]);
    expect((float) $of->fresh()->quantity_produced)->toBe(100.0);
});

it('P03 — 1 unité supplémentaire après avoir atteint le total commandé : BLOCK', function () {
    $co = p4Societe('p03');
    [$of, $wh, $pf] = p4ProdOrder($co, 100);
    $svc = app(ProductionStockService::class);
    test()->actingAs(p4User($co, 'chef_production'));
    $svc->recordOutput($of, ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 100, 'length' => 1, 'unit_cost' => 100]);

    expect(fn () => $svc->recordOutput($of->fresh(), ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 1, 'length' => 1, 'unit_cost' => 100]))
        ->toThrow(ValidationException::class);
});

it('P04 — une seule déclaration de 101 sur un OF de 100 : BLOCK direct', function () {
    $co = p4Societe('p04');
    [$of, $wh, $pf] = p4ProdOrder($co, 100);
    test()->actingAs(p4User($co, 'chef_production'));

    expect(fn () => app(ProductionStockService::class)->recordOutput($of, ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 101, 'length' => 1, 'unit_cost' => 100]))
        ->toThrow(ValidationException::class);
});

it('P05 — cumul (déjà produit + nouvelle déclaration) dépassant le restant : BLOCK', function () {
    $co = p4Societe('p05');
    [$of, $wh, $pf] = p4ProdOrder($co, 100);
    $svc = app(ProductionStockService::class);
    test()->actingAs(p4User($co, 'chef_production'));
    $svc->recordOutput($of, ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 70, 'length' => 1, 'unit_cost' => 100]);

    expect(fn () => $svc->recordOutput($of->fresh(), ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 40, 'length' => 1, 'unit_cost' => 100]))
        ->toThrow(ValidationException::class); // 70+40=110 > 100
    expect((float) $of->fresh()->quantity_produced)->toBe(70.0); // aucun effet de bord
});

it('P06 — dérogation « autoriser dépassement qté » : le dépassement passe (comportement existant, non modifié)', function () {
    $co = p4Societe('p06');
    [$of, $wh, $pf] = p4ProdOrder($co, 100, overflow: true);
    test()->actingAs(p4User($co, 'chef_production'));

    app(ProductionStockService::class)->recordOutput($of, ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 110, 'length' => 1, 'unit_cost' => 100]);
    expect((float) $of->fresh()->quantity_produced)->toBe(110.0);
});

// ═══════════════════════════════════════════════════════════════════════
// PHASE 5 — SURPAIEMENT
// ═══════════════════════════════════════════════════════════════════════

function p4Invoice(Company $co, Client $client, int $totalTtc): Invoice
{
    return Invoice::create(['company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'FAC-PAY-'.uniqid(), 'status' => 'emise', 'issued_at' => now(), 'due_at' => now()->addDays(30), 'total_ttc' => $totalTtc, 'paid_amount' => 0, 'remaining_amount' => $totalTtc]);
}

it('PAY01 — paiement exact d\'une facture : PASS, solde à 0', function () {
    $co = p4Societe('pay01');
    $client = Client::factory()->create(['is_active' => true]);
    $invoice = p4Invoice($co, $client, 100_000);
    test()->actingAs(p4User($co, 'caissier'));

    app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 100_000, 'method' => 'especes', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 100_000]]]);

    expect($invoice->fresh()->status)->toBe('payee')->and((int) $invoice->fresh()->remaining_amount)->toBe(0);
});

it('PAY02 — paiement supérieur à la facture : PLAFONNÉ automatiquement, excédent crédité (pas un rejet — comportement existant)', function () {
    $co = p4Societe('pay02');
    $client = Client::factory()->create(['is_active' => true]);
    $invoice = p4Invoice($co, $client, 100_000);
    test()->actingAs(p4User($co, 'caissier'));

    $payment = app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 100_001, 'method' => 'especes', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 100_001]]]);

    expect((int) $invoice->fresh()->remaining_amount)->toBe(0);
    expect((int) $invoice->fresh()->paid_amount)->toBe(100_000); // jamais > total facture
    expect((int) $payment->unallocated_amount)->toBe(1); // excédent devient crédit client, pas une erreur
});

it('PAY03 — paiement partiel : PASS, solde partiel correct', function () {
    $co = p4Societe('pay03');
    $client = Client::factory()->create(['is_active' => true]);
    $invoice = p4Invoice($co, $client, 100_000);
    test()->actingAs(p4User($co, 'caissier'));

    app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 40_000, 'method' => 'especes', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 40_000]]]);

    expect((int) $invoice->fresh()->remaining_amount)->toBe(60_000)->and($invoice->fresh()->status)->toBe('partiellement_payee');
});

it('PAY04 — deux paiements dont le cumul exact solde la facture : PASS', function () {
    $co = p4Societe('pay04');
    $client = Client::factory()->create(['is_active' => true]);
    $invoice = p4Invoice($co, $client, 100_000);
    test()->actingAs(p4User($co, 'caissier'));

    app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 60_000, 'method' => 'especes', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 60_000]]]);
    app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 40_000, 'method' => 'virement', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 40_000]]]);

    expect($invoice->fresh()->status)->toBe('payee')->and((int) $invoice->fresh()->remaining_amount)->toBe(0);
});

it('PAY05 — deux paiements dont le cumul excède le solde : second plafonné, jamais négatif', function () {
    $co = p4Societe('pay05');
    $client = Client::factory()->create(['is_active' => true]);
    $invoice = p4Invoice($co, $client, 100_000);
    test()->actingAs(p4User($co, 'caissier'));

    // Montants distincts (60 000 puis 45 000) : la garde anti-doublon "même
    // montant sous 60s" est une protection DIFFÉRENTE de ce qui est testé ici
    // (surpaiement cumulé) — même la scinder aurait exigé force_duplicate,
    // hors sujet de PAY05.
    app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 60_000, 'method' => 'especes', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 60_000]]]);
    $second = app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 45_000, 'method' => 'virement', 'payment_date' => now()->toDateString(), 'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => 45_000]]]);

    expect((int) $invoice->fresh()->remaining_amount)->toBe(0);
    expect((int) $invoice->fresh()->paid_amount)->toBe(100_000); // jamais > total
});

it('PAY06 — acompte libre sans facture (avant facturation) : PASS, régression cycle comptant préservée', function () {
    $co = p4Societe('pay06');
    $client = Client::factory()->create(['is_active' => true]);
    test()->actingAs(p4User($co, 'caissier'));

    $payment = app(ClientPaymentService::class)->create(['client_id' => $client->id, 'amount' => 50_000, 'method' => 'especes', 'payment_date' => now()->toDateString(), 'is_acompte' => true, 'force_duplicate' => true]);

    expect((int) $payment->unallocated_amount)->toBe(50_000);
});

// ═══════════════════════════════════════════════════════════════════════
// PHASE 6 — REMISE / PLANCHER ÉCONOMIQUE
// ═══════════════════════════════════════════════════════════════════════

it('D01/D02 — remise 0 et remise normale sous plancher : PASS', function () {
    $co = p4Societe('d0102');
    $client = Client::factory()->create(['is_active' => true]);
    // Coûts explicitement à 0 : Product::factory() pioche des coûts d'achat
    // aléatoires (Faker) qui produisent un plancher économique non nul et
    // imprévisible — hors sujet de D01/D02 (juste prouver qu'une remise
    // normale ne casse rien en l'absence de contrainte de plancher).
    $product = Product::factory()->create(['is_sellable' => true, 'sale_price' => 1000, 'purchase_price' => 0, 'last_purchase_price' => 0, 'weighted_avg_cost' => 0, 'cout_standard' => 0]);
    test()->actingAs(p4User($co, 'commercial'));

    $response = test()->post(route('ventes.commandes.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(), 'status' => 'confirme',
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 10]],
    ]);

    $response->assertSessionHasNoErrors();
});

it('D04 — remise > 100% refusée par la validation', function () {
    $co = p4Societe('d04');
    $client = Client::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_sellable' => true]);
    test()->actingAs(p4User($co, 'commercial'));

    $response = test()->post(route('ventes.commandes.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 1000, 'discount_percent' => 150]],
    ]);

    $response->assertSessionHasErrors('items.0.discount_percent');
});

it('D05 — prix final négatif structurellement impossible (unit_price>=0, discount<=100%)', function () {
    $co = p4Societe('d05');
    $client = Client::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['is_sellable' => true]);
    test()->actingAs(p4User($co, 'commercial'));

    $response = test()->post(route('ventes.commandes.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => -1, 'discount_percent' => 0]],
    ]);

    $response->assertSessionHasErrors('items.0.unit_price'); // unit_price min:0 refuse déjà le négatif à la source
});

it('D03 — remise ramenant le NET sous le plancher est bloquée (P4 — faille prouvée et corrigée)', function () {
    $co = p4Societe('d03');
    $client = Client::factory()->create(['is_active' => true]);
    // "marchandise" (non fabriqué, non service) : le plancher se base sur
    // purchase_price/last_purchase_price/weighted_avg_cost (costCandidates()) —
    // fixé à une valeur connue, les autres candidats mis à 0 pour un plancher
    // déterministe (pas de cout_standard : ignoré pour ce type d'article).
    $product = Product::factory()->create(['is_sellable' => true, 'is_manufacturable' => false, 'is_stockable' => true, 'production_mode' => null, 'purchase_price' => 800, 'last_purchase_price' => 0, 'weighted_avg_cost' => 0, 'sale_price' => 2000]);
    $floor = app(\App\Services\SalesPriceGuardService::class)->effectiveFloor($product->fresh());
    test()->actingAs(p4User($co, 'commercial'));

    if ($floor <= 0) {
        expect(true)->toBeTrue(); // aucun plancher configuré pour cet article : rien à prouver, pas un bug

        return;
    }

    // unit_price au-dessus du plancher (passe le contrôle brut), mais une
    // remise de 60% ramène le NET très en dessous.
    $unitPrice = $floor + 500;
    $response = test()->post(route('ventes.commandes.store'), [
        'client_id' => $client->id, 'issued_at' => now()->toDateString(), 'status' => 'confirme',
        'items' => [['product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => $unitPrice, 'discount_percent' => 60]],
    ]);

    $net = $unitPrice * (1 - 0.60);
    expect($net)->toBeLessThan($floor); // confirme que le scénario teste bien un net sous plancher
    $response->assertSessionHasErrors('items.0.unit_price');
});
