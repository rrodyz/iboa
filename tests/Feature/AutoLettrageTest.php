<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\AccountingService;
use App\Services\ClientPaymentService;
use App\Services\SupplierPaymentService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function letCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'Let Co'], ['email' => 'let@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function letAdmin(int $companyId): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $companyId]);
    $u->assignRole($role);
    return $u;
}

describe('Auto-lettrage encaissement ↔ facture', function () {

    it('lettre automatiquement une facture soldée 1:1 par un paiement', function () {
        $company = letCompany();
        $this->actingAs(letAdmin($company->id));
        $client  = Client::factory()->create(['is_active' => true]);
        $cash    = CashAccount::factory()->create(['company_id' => $company->id, 'current_balance' => 0, 'is_active' => true]);
        $method  = PaymentMethod::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id, 'client_id' => $client->id, 'status' => 'emise',
            'subtotal_ht' => 300_000, 'total_tax' => 0, 'total_ttc' => 300_000, 'paid_amount' => 0, 'remaining_amount' => 300_000,
        ]);

        // Comptabilise la facture (crée la ligne 411 débit)
        $invEntry = app(AccountingService::class)->postClientInvoice($invoice->fresh());
        $invoice->update(['journal_entry_id' => $invEntry->id]);

        // Paiement total
        app(ClientPaymentService::class)->create([
            'client_id'         => $client->id,
            'payment_method_id' => $method->id,
            'cash_account_id'   => $cash->id,
            'amount'            => 300_000,
            'payment_date'      => now()->toDateString(),
            'allocations'       => [['invoice_id' => $invoice->id, 'allocated_amount' => 300_000]],
        ]);

        // Les deux lignes 411 doivent porter la même lettre (reconciliation_ref)
        $invLine = $invEntry->fresh()->lines()->where('debit', '>', 0)->whereNotNull('reconciliation_ref')->first();

        expect($invLine)->not->toBeNull()
            ->and($invLine->reconciliation_ref)->not->toBeNull();

        $payLineSameLetter = \App\Models\JournalEntryLine::where('reconciliation_ref', $invLine->reconciliation_ref)
            ->where('credit', '>', 0)->exists();
        expect($payLineSameLetter)->toBeTrue();
    });

    it('ne lettre pas un paiement partiel', function () {
        $company = letCompany();
        $this->actingAs(letAdmin($company->id));
        $client  = Client::factory()->create(['is_active' => true]);
        $cash    = CashAccount::factory()->create(['company_id' => $company->id, 'current_balance' => 0, 'is_active' => true]);
        $method  = PaymentMethod::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id, 'client_id' => $client->id, 'status' => 'emise',
            'subtotal_ht' => 500_000, 'total_tax' => 0, 'total_ttc' => 500_000, 'paid_amount' => 0, 'remaining_amount' => 500_000,
        ]);
        $invEntry = app(AccountingService::class)->postClientInvoice($invoice->fresh());
        $invoice->update(['journal_entry_id' => $invEntry->id]);

        app(ClientPaymentService::class)->create([
            'client_id'         => $client->id,
            'payment_method_id' => $method->id,
            'cash_account_id'   => $cash->id,
            'amount'            => 200_000,
            'payment_date'      => now()->toDateString(),
            'allocations'       => [['invoice_id' => $invoice->id, 'allocated_amount' => 200_000]],
        ]);

        // Facture pas soldée → aucune lettre
        $lettered = $invEntry->fresh()->lines()->whereNotNull('reconciliation_ref')->exists();
        expect($lettered)->toBeFalse();
    });

    it('lettre automatiquement une facture fournisseur soldée 1:1 (401 crédit ↔ débit)', function () {
        $company = letCompany();
        $this->actingAs(letAdmin($company->id));

        $supplier = Supplier::create([
            'code' => 'FRN-LET-1', 'name' => 'Fournisseur Lettrage', 'type' => 'entreprise', 'is_active' => true,
        ]);
        $cash   = CashAccount::factory()->create(['company_id' => $company->id, 'type' => 'banque', 'current_balance' => 400_000, 'is_active' => true]);
        $method = PaymentMethod::factory()->create(['is_active' => true]);

        $invoice = SupplierInvoice::factory()->create([
            'company_id'  => $company->id, 'supplier_id' => $supplier->id, 'status' => 'validee',
            'subtotal_ht' => 400_000, 'total_tax' => 0, 'total_ttc' => 400_000,
            'paid_amount' => 0, 'remaining_amount' => 400_000,
        ]);

        // Comptabilise la facture fournisseur (crée la ligne 401 crédit)
        $invEntry = app(AccountingService::class)->postSupplierInvoice($invoice->fresh());
        $invoice->update(['journal_entry_id' => $invEntry->id]);

        // Décaissement total 1:1
        app(SupplierPaymentService::class)->create([
            'supplier_id'       => $supplier->id,
            'payment_method_id' => $method->id,
            'cash_account_id'   => $cash->id,
            'amount'            => 400_000,
            'payment_date'      => now()->toDateString(),
            'allocations'       => [['supplier_invoice_id' => $invoice->id, 'allocated_amount' => 400_000]],
        ]);

        // Ligne 401 crédit (facture) lettrée + ligne 401 débit (paiement) même lettre
        $invLine = $invEntry->fresh()->lines()->where('credit', '>', 0)->whereNotNull('reconciliation_ref')->first();

        expect($invLine)->not->toBeNull()
            ->and($invLine->reconciliation_ref)->not->toBeNull();

        $payLineSameLetter = \App\Models\JournalEntryLine::where('reconciliation_ref', $invLine->reconciliation_ref)
            ->where('debit', '>', 0)->exists();
        expect($payLineSameLetter)->toBeTrue();
    });

});
