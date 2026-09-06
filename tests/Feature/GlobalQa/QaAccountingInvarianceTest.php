<?php

/**
 * [GLOBAL-E2E-PROD-QA §66-69] Invariant comptable central : pour chaque pièce
 * comptabilisée, SUM(DEBIT) = SUM(CREDIT). InvoiceService::validate() et
 * ClientPaymentService::create() déclenchent automatiquement
 * AccountingService::postClientInvoice()/postSaleStockMovement()/
 * postClientPayment() (lecture de code confirmée, pas supposée) — ce test
 * les laisse s'exécuter normalement puis balaie TOUTES les écritures
 * générées pour la société QA, sans supposer un numéro de compte SYSCOHADA
 * particulier (paramétrable, non hardcodé ici — phase 67).
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ClientPaymentService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

it('QA — chaque pièce comptable générée (vente + encaissement) est équilibrée : SUM(debit) = SUM(credit)', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-ACC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'QA-ACC Co'], ['email' => 'qa-acc@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($u);

    $client = Client::factory()->create(['code' => 'QA-CLI-ACC', 'is_active' => true, 'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0]);
    $unit = Unit::firstOrCreate(['name' => 'Pièce QA-ACC'], ['abbreviation' => 'pqa']);
    $tax = TaxRate::firstOrCreate(['name' => 'TVA 18% QA-ACC'], ['short_name' => 'TVAQA', 'rate' => 18, 'is_active' => true]);
    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-ACC'], ['name' => 'Dépôt QA Compta', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $product = Product::factory()->create(['reference' => 'QA-PF-ACC', 'is_stockable' => true, 'production_mode' => 'mts']);
    \App\Models\ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $wh->id, 'quantity' => 50, 'reserved_quantity' => 0]);

    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $product->id, 'description' => $product->name,
            'quantity' => 5, 'unit_price' => 15_000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tax->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());

    $dn = app(OrderService::class)->createDeliveryNote($order->fresh());
    app(DeliveryNoteService::class)->validate($dn);
    // [R4.11] La validation du bon de livraison a déjà émis la facture :
    // on la récupère au lieu de la créer. Les assertions qui suivent —
    // montants, COGS, écritures, règlements — restent inchangées.
    $invoice = \App\Models\Invoice::where('delivery_note_id', $dn->id)->firstOrFail();
    app(InvoiceService::class)->validate($invoice); // → postClientInvoice + postSaleStockMovement automatiques.

    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);
    app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $cash->id,
        'amount' => $invoice->fresh()->total_ttc, 'method' => 'especes', 'payment_date' => now()->toDateString(),
        'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => $invoice->fresh()->total_ttc]],
    ]); // → postClientPayment automatique.

    $entries = JournalEntry::where('company_id', $co->id)->with('lines')->get();
    expect($entries->count())->toBeGreaterThan(0); // au moins une pièce a bien été générée — pas un test vide.

    foreach ($entries as $entry) {
        $debit = (int) $entry->lines->sum('debit');
        $credit = (int) $entry->lines->sum('credit');
        expect($debit)->toBe($credit); // P0 si faux — ACCOUNTING INTEGRITY FAILURE.
    }
});
