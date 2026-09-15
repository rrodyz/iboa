<?php

/**
 * Encaissement client (trésorerie).
 *
 * Point métier vérifié :
 *  - encaissement total → facture payée, reste à payer = 0 ;
 *  - encaissement partiel → facture partiellement payée, reste à payer mis à jour ;
 *  - mouvement de trésorerie généré sur le compte de caisse/banque ;
 *  - pas de double encaissement (allocation refusée sur une facture déjà soldée).
 */

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClientPaymentService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function cpayAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => '2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'CPAY'], ['email' => 'cpay@cpay.io', 'current_fiscal_year_id' => $fy->id]);
    $r  = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u  = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($r);

    return $u;
}

/** Facture validée (émise) de 11 800 TTC pour un client donné. */
function cpayValidatedInvoice(Client $client): App\Models\Invoice
{
    $product = Product::factory()->create(['is_sellable' => true]);
    $unit    = Unit::firstOrCreate(['name' => 'PC'], ['abbreviation' => 'pc']);
    $tva     = TaxRate::firstOrCreate(['name' => 'TVA 18 CPAY'], ['short_name' => 'TVA18', 'rate' => 18, 'type' => 'tva', 'is_active' => true]);

    $order = app(OrderService::class)->create([
        'client_id' => $client->id,
        'issued_at' => now()->toDateString(),
        'items'     => [[
            'product_id' => $product->id, 'description' => 'Article',
            'quantity' => 10, 'unit_price' => 1000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tva->id, 'tax_rate_value' => 18,
        ]],
    ]);

    $invoice = app(InvoiceService::class)->createFromOrder($order);

    return app(InvoiceService::class)->validate($invoice);
}

function cpayCashAccount(): CashAccount
{
    return CashAccount::factory()->create([
        'company_id' => Company::first()->id, 'type' => 'banque', 'current_balance' => 0, 'is_active' => true,
    ]);
}

it('encaissement total → facture payée, reste à payer = 0, mouvement de trésorerie', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    expect((int) $invoice->remaining_amount)->toBe(11800);

    $payment = app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'order_id'        => $invoice->order_id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'method'          => 'virement',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('payee');
    expect((int) $invoice->remaining_amount)->toBe(0);
    expect($payment->order_id)->toBe($invoice->order_id);

    // Mouvement de trésorerie (crédit) rattaché à l'encaissement.
    $tx = CashTransaction::where('reference_type', 'ClientPayment')->where('reference_id', $payment->id)->first();
    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe('credit');
    expect((int) $tx->amount)->toBe(11800);
});

it('liste uniquement les commandes du client choisi pour un encaissement', function () {
    $user = cpayAdmin();
    $this->actingAs($user);

    $client = Client::factory()->create();
    $other  = Client::factory()->create();
    $base   = [
        'company_id'    => $user->company_id,
        'fiscal_year_id'=> $user->company->current_fiscal_year_id,
        'issued_at'     => now()->toDateString(),
        'total_ttc'     => 25000,
    ];

    Order::create($base + ['client_id' => $client->id, 'number' => 'CMD-CLIENT-001', 'status' => 'confirme']);
    Order::create($base + ['client_id' => $client->id, 'number' => 'CMD-ANNULEE-001', 'status' => 'annule']);
    Order::create($base + ['client_id' => $other->id,  'number' => 'CMD-AUTRE-001', 'status' => 'confirme']);

    $this->getJson(route('tresorerie.encaissements.orders', ['client_id' => $client->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.number', 'CMD-CLIENT-001');
});

it('refuse de rattacher un encaissement à la commande d’un autre client', function () {
    $user = cpayAdmin();
    $this->actingAs($user);

    $client = Client::factory()->create();
    $other  = Client::factory()->create();
    $order  = Order::create([
        'company_id'     => $user->company_id,
        'fiscal_year_id' => $user->company->current_fiscal_year_id,
        'client_id'      => $other->id,
        'number'         => 'CMD-AUTRE-002',
        'status'         => 'confirme',
        'issued_at'      => now()->toDateString(),
    ]);

    $this->post(route('tresorerie.encaissements.store'), [
        'client_id'       => $client->id,
        'order_id'        => $order->id,
        'cash_account_id' => cpayCashAccount()->id,
        'amount'          => 1000,
        'payment_date'    => now()->toDateString(),
    ])->assertSessionHasErrors('order_id');
});

it('refuse une facture différente de la commande rattachée et un dépassement du solde commande', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoiceA = cpayValidatedInvoice($client);
    $invoiceB = cpayValidatedInvoice($client);
    $cash = cpayCashAccount();

    expect(fn () => app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'order_id'        => $invoiceA->order_id,
        'cash_account_id' => $cash->id,
        'amount'          => 1000,
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoiceB->id, 'allocated_amount' => 1000]],
    ]))->toThrow(\RuntimeException::class, 'ne provient pas de la commande');

    expect(fn () => app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'order_id'        => $invoiceA->order_id,
        'cash_account_id' => $cash->id,
        'amount'          => 12000,
        'payment_date'    => now()->toDateString(),
    ]))->toThrow(\RuntimeException::class, 'dépasse le reste à encaisser');
});

it('applique les obligations du mode de paiement', function () {
    $this->actingAs(cpayAdmin());
    $client = Client::factory()->create();
    $cash   = cpayCashAccount();
    $method = PaymentMethod::factory()->create([
        'is_active'          => true,
        'requires_reference' => true,
        'is_mobile_money'    => true,
        'attachment_required'=> true,
    ]);

    $this->post(route('tresorerie.encaissements.store'), [
        'client_id'          => $client->id,
        'payment_method_id'  => $method->id,
        'cash_account_id'    => $cash->id,
        'amount'             => 1000,
        'payment_date'       => now()->toDateString(),
        'site'               => '01',
        'treasury_journal'   => 'BAN1',
    ])->assertSessionHasErrors(['reference', 'phone_number', 'documents']);
});

it('annule un encaissement et restaure facture caisse et comptabilité', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    $payment = app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'order_id'        => $invoice->order_id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]);

    expect((int) $cash->fresh()->current_balance)->toBe(11800);

    app(ClientPaymentService::class)->cancel($payment, 'Erreur de saisie confirmée');

    $payment->refresh();
    $invoice->refresh();
    expect($payment->status)->toBe('annule')
        ->and($payment->allocations()->count())->toBe(0)
        ->and((int) $payment->allocated_amount)->toBe(0)
        ->and((int) $invoice->paid_amount)->toBe(0)
        ->and((int) $invoice->remaining_amount)->toBe(11800)
        ->and((int) $cash->fresh()->current_balance)->toBe(0)
        ->and($payment->journalEntry?->reversed_by_entry_id)->not->toBeNull();
});

it('encaissement partiel → facture partiellement payée, reste à payer mis à jour', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 5000,
        'method'          => 'especes',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 5000]],
    ]);

    $invoice->refresh();
    expect($invoice->status)->toBe('partiellement_payee');
    // 11 800 − 5 000 = 6 800 restant.
    expect((int) $invoice->remaining_amount)->toBe(6800);
    expect((int) $invoice->paid_amount)->toBe(5000);
});

it('pas de double encaissement : allocation refusée sur une facture déjà soldée', function () {
    $this->actingAs(cpayAdmin());
    $client  = Client::factory()->create();
    $invoice = cpayValidatedInvoice($client);
    $cash    = cpayCashAccount();

    // Premier encaissement solde la facture.
    app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'method'          => 'virement',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]);
    expect($invoice->fresh()->status)->toBe('payee');

    // Second encaissement sur la même facture (payée) → refusé.
    expect(fn () => app(ClientPaymentService::class)->create([
        'client_id'       => $client->id,
        'cash_account_id' => $cash->id,
        'amount'          => 11800,
        'method'          => 'cheque',
        'reference'       => 'CHQ-DUP-001',
        'payment_date'    => now()->toDateString(),
        'allocations'     => [['invoice_id' => $invoice->id, 'allocated_amount' => 11800]],
    ]))->toThrow(\RuntimeException::class);

    // La facture reste soldée à 0 (aucun sur-paiement).
    expect((int) $invoice->fresh()->remaining_amount)->toBe(0);
});
