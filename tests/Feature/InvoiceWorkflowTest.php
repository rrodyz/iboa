<?php

use App\Events\InvoiceValidated;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    // Associate the admin with the test-fixture company so SetCurrentCompany
    // injects a company that has a current_fiscal_year_id set.
    $company = createCompanyFixture();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    return $user;
}

function createCompanyFixture(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(
        ['name' => 'Test Company'],
        ['email' => 'test@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function createClientFixture(): Client
{
    return Client::factory()->create([
        'name'          => 'Client Test SARL',
        'email'         => 'client@test.bf',
        'credit_limit'  => 5_000_000,
        'is_active'     => true,
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Invoice creation', function () {

    it('creates an invoice with the correct number sequence', function () {
        $user    = createAdmin();
        $company = createCompanyFixture();
        $client  = createClientFixture();
        $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18%'], ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]);
        $unit    = Unit::firstOrCreate(['name' => 'Pièce'], ['abbreviation' => 'pcs']);

        $this->actingAs($user);

        /** @var InvoiceService $svc */
        $svc = app(InvoiceService::class);

        $invoice = $svc->create([
            'client_id'   => $client->id,
            'issued_at'   => now()->toDateString(),
            'due_at'      => now()->addDays(30)->toDateString(),
            'items'       => [
                [
                    'product_id'     => null,
                    'description'    => 'Prestation de conseil',
                    'unit_id'        => $unit->id,
                    'quantity'       => 2,
                    'unit_price'     => 100_000,
                    'discount_percent' => 0,
                    'tax_rate_id'    => $taxRate->id,
                    'tax_rate_value' => 18,
                ],
            ],
        ]);

        expect($invoice)->toBeInstanceOf(Invoice::class)
            ->and($invoice->status)->toBe('brouillon')
            ->and($invoice->subtotal_ht)->toBe(200_000)
            ->and($invoice->total_tax)->toBe(36_000)
            ->and($invoice->total_ttc)->toBe(236_000)
            ->and($invoice->remaining_amount)->toBe(236_000)
            ->and($invoice->number)->toStartWith('F');
    });

    it('validates an invoice and fires InvoiceValidated event', function () {
        Event::fake([InvoiceValidated::class]);

        $user    = createAdmin();
        $company = createCompanyFixture();
        $client  = createClientFixture();

        $this->actingAs($user);

        $invoice = Invoice::factory()->create([
            'company_id'       => $company->id,
            'client_id'        => $client->id,
            'status'           => 'brouillon',
            'subtotal_ht'      => 423_729,   // 500_000 / 1.18 ≈ 423_729
            'total_tax'        => 76_271,    // 423_729 × 18% ≈ 76_271  (423_729+76_271=500_000)
            'total_ttc'        => 500_000,
            'remaining_amount' => 500_000,
        ]);

        $svc = app(InvoiceService::class);
        $svc->validate($invoice);

        $invoice->refresh();

        expect($invoice->status)->toBe('emise')
            ->and($invoice->validated_at)->not->toBeNull();

        Event::assertDispatched(InvoiceValidated::class, fn($e) => $e->invoice->id === $invoice->id);
    });

    it('cannot validate an already-emise invoice', function () {
        $user   = createAdmin();
        $client = createClientFixture();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create([
            'company_id'  => createCompanyFixture()->id,
            'client_id'   => $client->id,
            'status'      => 'emise',
            'subtotal_ht' => 423_729,
            'total_tax'   => 76_271,
            'total_ttc'   => 500_000,
        ]);

        $svc = app(InvoiceService::class);

        expect(fn() => $svc->validate($invoice))->toThrow(\RuntimeException::class);
    });

});

describe('Invoice HTTP endpoints', function () {

    it('returns 200 on invoice index for authorized user', function () {
        $user = createAdmin();
        createCompanyFixture();

        $this->actingAs($user)
             ->get(route('ventes.factures.index'))
             ->assertOk();
    });

    it('redirects to login for unauthenticated requests', function () {
        $this->get(route('ventes.factures.index'))
             ->assertRedirect(route('login'));
    });

    it('generates invoice PDF', function () {
        $user    = createAdmin();
        $company = createCompanyFixture();
        $client  = createClientFixture();

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id'  => $client->id,
            'status'     => 'emise',
            'number'     => 'F2025-TEST-001',
        ]);

        $this->actingAs($user)
             ->get(route('ventes.factures.pdf', ['facture' => $invoice->id, 'preview' => true]))
             ->assertOk()
             ->assertHeader('Content-Type', 'application/pdf');
    });

});
