<?php

use App\Events\PaymentReceived;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\ClientPaymentService;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function payAdmin(?int $companyId = null): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u    = User::factory()->create(array_filter(['company_id' => $companyId]));
    $u->assignRole($role);
    return $u;
}

function payCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(
        ['name' => 'Pay Test Company'],
        ['email' => 'pay@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ClientPaymentService', function () {

    it('creates a payment and allocates to an invoice', function () {
        $company     = payCompany();
        $user        = payAdmin($company->id);
        $client      = Client::factory()->create(['is_active' => true]);
        $cashAccount = CashAccount::factory()->create(['current_balance' => 0, 'is_active' => true]);
        $method      = PaymentMethod::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'company_id'       => $company->id,
            'client_id'        => $client->id,
            'status'           => 'emise',
            'total_ttc'        => 200_000,
            'paid_amount'      => 0,
            'remaining_amount' => 200_000,
        ]);

        $this->actingAs($user);

        /** @var ClientPaymentService $svc */
        $svc = app(ClientPaymentService::class);

        $payment = $svc->create([
            'client_id'         => $client->id,
            'payment_method_id' => $method->id,
            'cash_account_id'   => $cashAccount->id,
            'amount'            => 200_000,
            'payment_date'      => now()->toDateString(),
            'allocations'       => [
                ['invoice_id' => $invoice->id, 'allocated_amount' => 200_000],
            ],
        ]);

        $invoice->refresh();

        expect($payment->amount)->toBe(200_000)
            ->and($payment->allocated_amount)->toBe(200_000)
            ->and($payment->unallocated_amount)->toBe(0)
            ->and($invoice->status)->toBe('payee')
            ->and((float) $invoice->paid_amount)->toBe(200_000.0)
            ->and((float) $invoice->remaining_amount)->toBe(0.0);
    });

    it('partially allocates a payment and sets invoice to partiellement_payee', function () {
        $company     = payCompany();
        $user        = payAdmin($company->id);
        $client      = Client::factory()->create(['is_active' => true]);
        $cashAccount = CashAccount::factory()->create(['current_balance' => 0, 'is_active' => true]);
        $method      = PaymentMethod::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'company_id'       => $company->id,
            'client_id'        => $client->id,
            'status'           => 'emise',
            'total_ttc'        => 500_000,
            'paid_amount'      => 0,
            'remaining_amount' => 500_000,
        ]);

        $this->actingAs($user);

        $svc = app(ClientPaymentService::class);

        $payment = $svc->create([
            'client_id'         => $client->id,
            'payment_method_id' => $method->id,
            'cash_account_id'   => $cashAccount->id,
            'amount'            => 200_000,
            'payment_date'      => now()->toDateString(),
            'allocations'       => [
                ['invoice_id' => $invoice->id, 'allocated_amount' => 200_000],
            ],
        ]);

        $invoice->refresh();

        expect($invoice->status)->toBe('partiellement_payee')
            ->and((float) $invoice->paid_amount)->toBe(200_000.0)
            ->and((float) $invoice->remaining_amount)->toBe(300_000.0)
            ->and($payment->unallocated_amount)->toBe(0);
    });

    it('fires PaymentReceived event after payment creation', function () {
        Event::fake([PaymentReceived::class]);

        $company     = payCompany();
        $user        = payAdmin($company->id);
        $client      = Client::factory()->create(['is_active' => true]);
        $cashAccount = CashAccount::factory()->create(['current_balance' => 0, 'is_active' => true]);
        $method      = PaymentMethod::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'company_id'       => $company->id,
            'client_id'        => $client->id,
            'status'           => 'emise',
            'total_ttc'        => 100_000,
            'paid_amount'      => 0,
            'remaining_amount' => 100_000,
        ]);

        $this->actingAs($user);

        $svc = app(ClientPaymentService::class);
        $svc->create([
            'client_id'         => $client->id,
            'payment_method_id' => $method->id,
            'cash_account_id'   => $cashAccount->id,
            'amount'            => 100_000,
            'payment_date'      => now()->toDateString(),
            'allocations'       => [
                ['invoice_id' => $invoice->id, 'allocated_amount' => 100_000],
            ],
        ]);

        Event::assertDispatched(PaymentReceived::class);
    });

    it('returns 200 on encaissements index', function () {
        $user = payAdmin();
        payCompany();
        $this->actingAs($user)
             ->get(route('tresorerie.encaissements.index'))
             ->assertOk();
    });

});
