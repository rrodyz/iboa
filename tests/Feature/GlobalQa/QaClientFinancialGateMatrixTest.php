<?php

/**
 * [GLOBAL-E2E-PROD-QA §6-9,30-34,58-60] Matrice financière des trois clients
 * QA obligatoires, testée directement sur ProductionFinancialEligibilityService
 * — la SOURCE UNIQUE de la règle (voir sa doc de tête) — plutôt que rejouée
 * indirectement via ProductionService::launch(), déjà couvert par
 * OrderToCashFullChainTest / E2eMtoApprovedAndMtsTest pour la mécanique OF.
 *
 * FINDING (à reporter tel quel dans le rapport final, pas silencieusement
 * corrigé) : Client::PAYMENT_MODES = ['cash', 'credit'] uniquement. Il n'existe
 * PAS de troisième MODE client "acompte"/deposit — toute valeur hors cash/credit
 * tombe dans le `default` de ProductionFinancialEligibilityService::evaluate()
 * → TYPE_UNSUPPORTED, fail-closed (refus), prouvé ci-dessous par un test dédié.
 * "Acompte" existe en revanche comme TYPE DE PAIEMENT (ClientPayment.is_acompte
 * = true, montant non imputé compté par Order::confirmedReceipts() via
 * unallocated_amount) — applicable à un client cash comme couverture partielle
 * avant production (0 → BLOCK, insuffisant → BLOCK, intégral → PASS). QA-CLI-
 * DEPOSIT est donc construit comme un client CASH réglant par acomptes
 * (is_acompte=true), pas comme un mode métier distinct.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\ClientPaymentService;
use App\Services\Production\ProductionFinancialEligibilityService;
use App\Models\CashAccount;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qaFinCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-E2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    return Company::firstOrCreate(['name' => 'QA-E2E-PROD Co'], ['email' => 'qa-e2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function qaFinAdmin(Company $co): User
{
    $r = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);
    return $u;
}

function qaFinOrder(Company $co, Client $client, int $unitPrice = 200_000): Order
{
    $unit = Unit::firstOrCreate(['name' => 'Pièce QA-FIN'], ['abbreviation' => 'pqf']);
    $tax  = TaxRate::firstOrCreate(['name' => 'TVA 18% QA-FIN'], ['short_name' => 'TVAQF', 'rate' => 18, 'is_active' => true]);
    $product = Product::factory()->create(['is_stockable' => true, 'production_mode' => 'mto']);

    return app(\App\Services\OrderService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $product->id, 'description' => $product->name,
            'quantity' => 1, 'unit_price' => $unitPrice, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tax->id, 'tax_rate_value' => 18,
        ]],
    ]);
}

// ═══════════════ QA-CLI-CASH — comptant ═══════════════

it('QA-CLI-CASH — commande non réglée : gate financier BLOCK', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));

    $client = Client::factory()->create(['code' => 'QA-CLI-CASH', 'name' => 'QA Client Comptant', 'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0, 'is_active' => true]);
    $order = qaFinOrder($co, $client, 200_000); // 236 000 TTC
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect($req->satisfied)->toBeFalse()
        ->and($req->type)->toBe(\App\Services\Production\ProductionFinancialRequirement::TYPE_FULL_PAYMENT)
        ->and((int) $req->requiredAmount)->toBe((int) $order->fresh()->total_ttc);
});

it('QA-CLI-CASH — paiement intégral encaissé : gate financier PASS', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));

    $client = Client::factory()->create(['code' => 'QA-CLI-CASH-2', 'name' => 'QA Client Comptant', 'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0, 'is_active' => true]);
    $order = qaFinOrder($co, $client, 200_000);
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());
    $order->refresh();

    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);
    // [FIX QA-01, preuve BonPreparationService.php:82] Le premier règlement
    // comptoir d'un client sans dette (aucune facture, aucun acompte
    // antérieur) doit passer par BonPreparationService::createForCashOrder()
    // — c'est LE chemin métier réel : il appelle ClientPaymentService::create()
    // avec force_duplicate=true, documenté explicitement "règlement comptoir
    // AVANT facturation : la garde anti-doublon (client sans dette) ne
    // s'applique pas". Mon premier essai appelait ClientPaymentService
    // directement sans ce flag → RuntimeException "Doublon structurel" (0
    // impayé ≥ 0 non imputé, vrai pour TOUT premier paiement d'un client
    // neuf) — défaut de TEST (mauvais point d'entrée), pas défaut produit :
    // ComptantAcompteChainTest.php (déjà dans les 1502 tests baseline)
    // prouve ce même chemin fonctionnel.
    app(\App\Services\BonPreparationService::class)->createForCashOrder($order->fresh(), (int) $order->total_ttc, 'QA-CASH-PAID-001', $cash->id);

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect($req->satisfied)->toBeTrue();
});

// ═══════════════ QA-CLI-DEPOSIT — mode ACOMPTE réel (R4.3) ═══════════════
//
// Ce bloc portait autrefois sur un client au COMPTANT à qui l'on versait la
// moitié : il nommait « acompte » ce qui n'était qu'un règlement partiel, et le
// mode acompte n'existait pas encore. Depuis R3 il existe pour de bon, avec un
// seuil canonique. Le scénario est donc rétabli dans son intention d'origine —
// prouver qu'un acompte suffisant ouvre la production — mais sur le vrai mode.

/** Client à acompte + seuil global, sur la société QA. */
function qaFinDepositClient(Company $co, string $code, float $taux = 50): Client
{
    app()->instance('current_company', $co);
    $setting = \App\Models\SalesSetting::current();
    $setting->deposit_required_rate = $taux;
    $setting->save();

    return Client::factory()->create([
        'code' => $code, 'name' => 'QA Client Acompte',
        'payment_mode' => Client::PAYMENT_DEPOSIT, 'credit_limit' => 0, 'is_active' => true,
    ]);
}

it('QA-CLI-DEPOSIT — 0 versé : BLOCK', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    $client = qaFinDepositClient($co, 'QA-CLI-DEPOSIT');
    $order = qaFinOrder($co, $client, 500_000); // 590 000 TTC
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect($req->satisfied)->toBeFalse()
        ->and((int) $req->coveredAmount)->toBe(0)
        ->and((int) $req->requiredAmount)->toBe(295_000); // 50 % de 590 000
});

it('QA-CLI-DEPOSIT — versement sous le seuil : BLOCK', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    $client = qaFinDepositClient($co, 'QA-CLI-DEPOSIT-2');
    $order = qaFinOrder($co, $client, 500_000);
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());
    $order->refresh();

    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);

    // Un franc sous le seuil : le bon de préparation reste fermé. Le seuil est
    // une frontière, pas une approximation.
    expect(fn () => app(\App\Services\BonPreparationService::class)
        ->createForCashOrder($order->fresh(), 294_999, 'QA-DEPOSIT-UNDER-001', $cash->id))
        ->toThrow(RuntimeException::class);
    expect($order->fresh()->hasBonPreparation())->toBeFalse();
});

it('QA-CLI-DEPOSIT — acompte au seuil : bon de préparation émis', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    $client = qaFinDepositClient($co, 'QA-CLI-DEPOSIT-3');
    $order = qaFinOrder($co, $client, 500_000);
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());
    $order->refresh();

    expect((int) $order->total_ttc)->toBe(590_000);

    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);
    $bp = app(\App\Services\BonPreparationService::class)
        ->createForCashOrder($order->fresh(), 295_000, 'QA-DEPOSIT-OK-001', $cash->id);

    expect($bp->exists)->toBeTrue()
        ->and($order->fresh()->hasBonPreparation())->toBeTrue();

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect((int) $req->requiredAmount)->toBe(295_000)
        ->and((int) $req->coveredAmount)->toBe(295_000)
        ->and($req->satisfied)->toBeTrue();
});

// ═══════════════ QA-CLI-CREDIT — crédit ═══════════════

it('QA-CLI-CREDIT — encours < plafond : PASS', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    $client = Client::factory()->create(['code' => 'QA-CLI-CREDIT', 'name' => 'QA Client Crédit', 'payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 10_000_000, 'is_active' => true]);
    $order = qaFinOrder($co, $client, 500_000); // 590 000 TTC << 10M
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect($req->satisfied)->toBeTrue()
        ->and($req->type)->toBe(\App\Services\Production\ProductionFinancialRequirement::TYPE_CREDIT);
});

it('QA-CLI-CREDIT — encours prévisionnel > plafond : BLOCK (aucun fail-open), dès la validation commerciale', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    $client = Client::factory()->create(['code' => 'QA-CLI-CREDIT-2', 'name' => 'QA Client Crédit', 'payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 300_000, 'is_active' => true]);
    $order = qaFinOrder($co, $client, 500_000); // 590 000 TTC > 300 000 plafond

    // [FIX QA-01 v2, preuve CustomerCreditExposureService.php:186] Le
    // dépassement de plafond bloque DÈS submit() — CommercialWorkflowService
    // ::submit() effectue déjà le contrôle de crédit (verrouillage client
    // avant commande, cf. commentaire de tête de la méthode), pas seulement
    // validateOrder(). Mon 1er correctif enveloppait encore validateOrder()
    // seul et laissait submit() non protégé → l'exception partait AVANT le
    // bloc expect(), remontant comme échec non capturé. Défaut de TEST
    // (portée de capture insuffisante), pas défaut produit — le
    // comportement réel bloque encore plus tôt que prévu, jamais fail-open.
    expect(function () use ($order) {
        app(CommercialWorkflowService::class)->submit($order);
        app(CommercialWorkflowService::class)->validateOrder($order->fresh());
    })->toThrow(\RuntimeException::class);
    expect($order->fresh()->status)->not->toBe('confirme');
});

it('QA-CLI-CREDIT — plafond = 0 : refus explicite, pas un plafond illimité (garde contre le fail-open)', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    $client = Client::factory()->create(['code' => 'QA-CLI-CREDIT-ZERO', 'name' => 'QA Client Crédit Sans Plafond', 'payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 0, 'is_active' => true]);
    $order = qaFinOrder($co, $client, 10_000);
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect($req->satisfied)->toBeFalse();
});

// ═══════════════ Mode inconnu — fail-closed (preuve directe de la doc de tête) ═══════════════

it('mode de règlement inconnu → TYPE_UNSUPPORTED, fail-closed', function () {
    $co = qaFinCompany();
    $this->actingAs(qaFinAdmin($co));
    // Force une valeur hors énum applicative pour prouver le comportement réel
    // du `match` par défaut — c'est EXACTEMENT ce qui arriverait si un futur mode
    // était ajouté en base sans toucher ProductionFinancialEligibilityService.
    // 'deposit' ne peut plus servir d'exemple : c'est un mode réel depuis R3.
    $client = Client::factory()->create(['code' => 'QA-CLI-UNKNOWN-MODE', 'payment_mode' => 'leasing', 'credit_limit' => 10_000_000, 'is_active' => true]);
    $order = qaFinOrder($co, $client, 10_000);
    app(CommercialWorkflowService::class)->submit($order);
    app(CommercialWorkflowService::class)->validateOrder($order->fresh());

    $req = app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
    expect($req->satisfied)->toBeFalse()
        ->and($req->type)->toBe(\App\Services\Production\ProductionFinancialRequirement::TYPE_UNSUPPORTED);
});
