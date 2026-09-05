<?php

/**
 * [R3 — BUG-A3-SALES-DEPOSIT-004 fermé] Mode de règlement ACOMPTE réel.
 *
 * OA METAL exige trois modes distincts : comptant (100 % du TTC avant
 * production), acompte (une PART du TTC, taux canonique
 * `sales_settings.deposit_required_rate`) et crédit (plafond/exposition).
 *
 * Ce fichier prouve le comportement du mode acompte de bout en bout sur le
 * moteur réel — `ProductionFinancialEligibilityService` — jamais sur une
 * simulation : montant requis, seuil strict, solde restant dû après
 * franchissement du seuil, arrondi, taux limites, et refus fail-closed quand le
 * paramétrage est inexploitable.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\SalesSetting;
use App\Models\User;
use App\Services\Production\ProductionFinancialEligibilityService;
use App\Services\Production\ProductionFinancialRequirement;

uses(\Tests\Concerns\RefreshDatabase::class);

function r3Company(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'R3-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'R3 Co'], ['email' => 'r3@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function r3Rate(?float $rate): void
{
    $setting = SalesSetting::current();
    $setting->deposit_required_rate = $rate;
    $setting->save();
}

/** Commande TTC donnée pour un client d'un mode donné. */
function r3Order(string $mode, int $ttc = 1000000, array $clientAttrs = []): Order
{
    $co = r3Company();
    $client = Client::factory()->create(array_merge(['payment_mode' => $mode], $clientAttrs));

    return Order::create([
        'company_id' => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'R3-CMD-'.uniqid(),
        'status' => 'confirme',
        'issued_at' => now(),
        'total_ttc' => $ttc,
        'subtotal_ht' => (int) round($ttc / 1.18),
    ]);
}

/** Encaissement confirmé et alloué à la facture de la commande. */
function r3Pay(Order $order, int $amount): void
{
    if ($amount <= 0) {
        return;
    }

    $invoice = \App\Models\Invoice::firstOrCreate(
        ['order_id' => $order->id],
        [
            'company_id' => $order->company_id,
            'fiscal_year_id' => $order->fiscal_year_id,
            'client_id' => $order->client_id,
            'number' => 'R3-FA-'.uniqid(),
            'status' => 'emise',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'currency_code' => 'XOF',
            'subtotal_ht' => $order->subtotal_ht,
            'total_ttc' => $order->total_ttc,
            'remaining_amount' => $order->total_ttc,
        ]
    );

    $payment = \App\Models\ClientPayment::create([
        'company_id' => $order->company_id,
        'client_id' => $order->client_id,
        'number' => 'R3-ENC-'.uniqid(),
        'amount' => $amount,
        'net_amount' => $amount,
        'payment_date' => now(),
        'status' => 'confirme',
        'allocated_amount' => $amount,
        'unallocated_amount' => 0,
    ]);

    \App\Models\ClientPaymentAllocation::create([
        'client_payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => $amount,
        'allocated_at' => now(),
    ]);
}

function r3Evaluate(Order $order): ProductionFinancialRequirement
{
    return app(ProductionFinancialEligibilityService::class)->evaluate($order->fresh());
}

// ─────────────────────────── Modes disponibles ───────────────────────────

it('R3 — expose les trois modes canoniques et leurs libellés', function () {
    expect(Client::PAYMENT_MODES)->toBe(['cash', 'deposit', 'credit'])
        ->and(Client::PAYMENT_DEPOSIT)->toBe('deposit')
        ->and(Client::PAYMENT_MODE_LABELS[Client::PAYMENT_CASH])->toBe('Comptant')
        ->and(Client::PAYMENT_MODE_LABELS[Client::PAYMENT_DEPOSIT])->toBe('Acompte')
        ->and(Client::PAYMENT_MODE_LABELS[Client::PAYMENT_CREDIT])->toBe('Crédit');
});

it('R3 — un client acompte est persistable et se reconnaît', function () {
    r3Company();
    $client = Client::factory()->create(['payment_mode' => Client::PAYMENT_DEPOSIT]);

    expect($client->fresh()->payment_mode)->toBe('deposit')
        ->and($client->isDeposit())->toBeTrue()
        ->and($client->isCash())->toBeFalse()
        ->and($client->isCredit())->toBeFalse()
        ->and($client->paymentModeLabel())->toBe('Acompte');
});

// ─────────────────────────── Comptant inchangé ───────────────────────────

it('R3 — comptant : 100 % du TTC reste exigé', function (int $paid, bool $eligible) {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_CASH);
    r3Pay($order, $paid);

    $req = r3Evaluate($order);

    expect($req->type)->toBe(ProductionFinancialRequirement::TYPE_FULL_PAYMENT)
        ->and($req->requiredAmount)->toBe(1000000)
        ->and($req->satisfied)->toBe($eligible);
})->with([
    'rien payé'      => [0, false],
    'un franc court' => [999999, false],
    'intégral'       => [1000000, true],
]);

// ─────────────────────────── Acompte : matrice ───────────────────────────

it('R3 — acompte 30 % sur 1 000 000 : seuil strict, solde restant dû', function (int $paid, bool $eligible) {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, $paid);

    $req = r3Evaluate($order);

    expect($req->type)->toBe(ProductionFinancialRequirement::TYPE_DEPOSIT)
        ->and($req->requiredAmount)->toBe(300000)
        ->and($req->coveredAmount)->toBe($paid)
        ->and($req->satisfied)->toBe($eligible);
})->with([
    'rien payé'          => [0, false],
    'un franc sous seuil'=> [299999, false],
    'seuil exact'        => [300000, true],
    'au-dessus du seuil' => [450000, true],
    'intégralement payé' => [1000000, true],
]);

it('R3 — sous le seuil, le manquant avant lancement est exact', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 250000);

    $req = r3Evaluate($order);

    expect($req->satisfied)->toBeFalse()
        ->and($req->uncoveredAmount())->toBe(50000)
        ->and($req->reason)->toContain('Manque');
});

it('R3 — seuil franchi : production éligible mais la facture reste due', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 300000);

    $req = r3Evaluate($order);
    $invoice = \App\Models\Invoice::where('order_id', $order->id)->first();
    $alloue = (int) \App\Models\ClientPaymentAllocation::where('invoice_id', $invoice->id)->sum('amount');

    expect($req->satisfied)->toBeTrue()
        ->and($req->uncoveredAmount())->toBe(0)
        // La production est lancée, mais la créance client reste entière au-delà
        // de l'acompte : 1 000 000 − 300 000 = 700 000 toujours dus.
        ->and((int) $invoice->total_ttc - $alloue)->toBe(700000)
        ->and($invoice->status)->not->toBe('payee');
});

it('R3 — le taux paramétré change réellement le montant exigé (paramètre non décoratif)', function (float $rate, int $required) {
    r3Company();
    r3Rate($rate);
    $order = r3Order(Client::PAYMENT_DEPOSIT);

    expect(r3Evaluate($order)->requiredAmount)->toBe($required)
        ->and($order->fresh()->requiredBeforeProduction())->toBe($required);
})->with([
    '20 %'  => [20.0, 200000],
    '30 %'  => [30.0, 300000],
    '40 %'  => [40.0, 400000],
    '70 %'  => [70.0, 700000],
    '100 %' => [100.0, 1000000],
]);

it('R3 — arrondi sur un TTC non rond : 236 000 × 30 % = 70 800', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT, 236000);

    expect(r3Evaluate($order)->requiredAmount)->toBe(70800);

    r3Pay($order, 70799);
    expect(r3Evaluate($order)->satisfied)->toBeFalse();
});

it('R3 — acompte 100 % équivaut au comptant pour la garde, sans changer le mode', function () {
    r3Company();
    r3Rate(100);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 999999);

    $req = r3Evaluate($order);

    expect($req->type)->toBe(ProductionFinancialRequirement::TYPE_DEPOSIT)
        ->and($req->requiredAmount)->toBe(1000000)
        ->and($req->satisfied)->toBeFalse();
});

// ─────────────────────────── Fail-closed ───────────────────────────

it('R3 — taux inexploitable : la garde refuse, sans repli sur comptant ni crédit', function (?float $rate) {
    r3Company();
    r3Rate($rate);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 1000000); // même intégralement payé, un paramétrage cassé ne doit pas "passer par hasard"

    $req = r3Evaluate($order);

    expect($req->type)->toBe(ProductionFinancialRequirement::TYPE_UNSUPPORTED)
        ->and($req->satisfied)->toBeFalse()
        ->and($req->source)->toBe('sales_settings.deposit_required_rate')
        ->and($order->fresh()->requiredBeforeProduction())->toBeNull();
})->with([
    'taux nul'     => [0.0],
    'taux négatif' => [-10.0],
    'taux > 100'   => [150.0],
]);

it('R3 — le taux ne peut pas être absent en base : colonne NOT NULL avec défaut', function () {
    r3Company();

    $col = collect(\Illuminate\Support\Facades\DB::select(
        'SELECT is_nullable AS nullable, column_default AS defaut FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        ['sales_settings', 'deposit_required_rate']
    ))->map(fn ($r) => (array) $r)->first();

    // « Taux absent » n'est donc pas un état atteignable par la base : le
    // service garde tout de même la branche null (défense en profondeur, cas
    // d'un paramétrage lu depuis une autre source), mais le scénario réel
    // couvert par la garde est le taux hors bornes, testé ci-dessus.
    expect($col['nullable'])->toBe('NO')
        ->and($col['defaut'])->not->toBeNull()
        ->and(SalesSetting::current()->deposit_required_rate)->not->toBeNull();
});

it('R3 — mode de règlement inconnu : refus explicite (fail-closed)', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order('leasing');

    $req = r3Evaluate($order);

    expect($req->type)->toBe(ProductionFinancialRequirement::TYPE_UNSUPPORTED)
        ->and($req->satisfied)->toBeFalse()
        ->and($req->reason)->toContain('leasing');
});

// ─────────────────────────── Crédit inchangé ───────────────────────────

it('R3 — crédit : plafond nul refuse toujours, plafond accordé autorise', function () {
    r3Company();
    r3Rate(30);

    $sansPlafond = r3Order(Client::PAYMENT_CREDIT, 1000000, ['credit_limit' => 0]);
    expect(r3Evaluate($sansPlafond)->satisfied)->toBeFalse();

    $avecPlafond = r3Order(Client::PAYMENT_CREDIT, 1000000, ['credit_limit' => 5000000]);
    $req = r3Evaluate($avecPlafond);
    expect($req->type)->toBe(ProductionFinancialRequirement::TYPE_CREDIT)
        ->and($req->satisfied)->toBeTrue();
});

// ─────────────────────────── Garde de lancement MTO ───────────────────────────

/** OF rattaché à une commande acompte, prêt à être lancé. */
function r3ProductionOrder(Order $order): \App\Modules\Production\Models\ProductionOrder
{
    $product = \App\Models\Product::factory()->create([
        'is_active' => true, 'is_sellable' => true,
        'is_manufacturable' => false, // écarte la garde nomenclature : on éprouve la garde FINANCIÈRE
        'production_mode' => 'mto',
    ]);

    $order->items()->create([
        'product_id' => $product->id, 'description' => $product->name,
        'quantity' => 10, 'unit_price' => (int) ($order->total_ttc / 10),
        'line_total_ht' => $order->subtotal_ht, 'line_tax' => 0, 'line_total_ttc' => $order->total_ttc,
    ]);

    return \App\Modules\Production\Models\ProductionOrder::create([
        'company_id' => $order->company_id, 'fiscal_year_id' => $order->fiscal_year_id,
        'order_id' => $order->id, 'client_id' => $order->client_id, 'product_id' => $product->id,
        'number' => 'R3-OF-'.uniqid(), 'status' => 'brouillon',
        'quantity_requested' => 10, 'quantity_produced' => 0, 'origin' => 'commande_client',
    ]);
}

it('R3 — MTO : acompte sous le seuil, le lancement est refusé par le service', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 299999);
    $of = r3ProductionOrder($order);

    expect(fn () => app(\App\Modules\Production\Services\ProductionService::class)->launch($of))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    expect($of->fresh()->status)->toBe('brouillon')
        ->and($of->fresh()->financial_authorization)->not->toBe('approved');
});

it('R3 — MTO : seuil exact atteint, le lancement passe la garde financière', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 300000);
    $of = r3ProductionOrder($order);

    $exigence = app(\App\Modules\Production\Services\ProductionService::class)->checkFinancialGate($of);

    expect($exigence)->not->toBeNull()
        ->and($exigence->type)->toBe(ProductionFinancialRequirement::TYPE_DEPOSIT)
        ->and($exigence->satisfied)->toBeTrue();
});

it('R3 — MTO : au-dessus du seuil, la garde reste satisfaite', function () {
    r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 450000);
    $of = r3ProductionOrder($order);

    expect(app(\App\Modules\Production\Services\ProductionService::class)->checkFinancialGate($of)->satisfied)->toBeTrue();
});

it('R3 — MTO : la route HTTP de lancement refuse aussi un acompte insuffisant', function () {
    $co = r3Company();
    r3Rate(30);
    $order = r3Order(Client::PAYMENT_DEPOSIT);
    r3Pay($order, 100000);
    $of = r3ProductionOrder($order);

    $user = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($user);

    $this->post(route('production.orders.launch', $of))
        ->assertSessionHasErrors('financial');

    expect($of->fresh()->status)->toBe('brouillon');
});

// ─────────────────────────── Validation / UI ───────────────────────────

it('R3 — les Form Requests acceptent les trois modes et refusent le reste', function () {
    r3Company();
    $rules = (new \App\Http\Requests\Client\StoreClientRequest)->rules()['payment_mode'];
    $validator = fn (string $mode) => validator(['payment_mode' => $mode], ['payment_mode' => $rules])->passes();

    expect($validator('cash'))->toBeTrue()
        ->and($validator('deposit'))->toBeTrue()
        ->and($validator('credit'))->toBeTrue()
        ->and($validator('acompte'))->toBeFalse()
        ->and($validator('leasing'))->toBeFalse();
});

it('R3 — la fiche client enregistre et affiche le mode acompte via HTTP', function () {
    $co = r3Company();
    r3Rate(30);
    $user = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($user);

    $this->post(route('clients.store'), [
        'name' => 'Client Acompte R3',
        'type' => Client::TYPE_ENTREPRISE,
        'payment_mode' => Client::PAYMENT_DEPOSIT,
    ])->assertRedirect();

    $client = Client::where('name', 'Client Acompte R3')->firstOrFail();
    expect($client->payment_mode)->toBe('deposit');

    $this->get(route('clients.show', $client))->assertOk()->assertSee('Acompte');
});
