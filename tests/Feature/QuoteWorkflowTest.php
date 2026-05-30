<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Services\QuoteService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function quoteAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    // Associate the admin with the test-fixture company so SetCurrentCompany
    // injects a company that has a current_fiscal_year_id set.
    $company = quoteCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function quoteCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(
        ['name' => 'Quote Test Co'],
        ['email' => 'quote@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

describe('Quote → Order conversion workflow', function () {

    it('creates a quote with correct totals', function () {
        $user    = quoteAdmin();
        $company = quoteCompany();
        $client  = Client::factory()->create(['is_active' => true]);
        $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18%'], ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]);
        $unit    = Unit::firstOrCreate(['name' => 'Pièce'], ['abbreviation' => 'pcs']);

        $this->actingAs($user);

        /** @var QuoteService $svc */
        $svc   = app(QuoteService::class);
        $quote = $svc->create([
            'client_id'  => $client->id,
            'issued_at'  => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
            'items'      => [
                [
                    'description'    => 'Ordinateur portable',
                    'quantity'       => 5,
                    'unit_price'     => 300_000,
                    'discount_percent' => 10,
                    'tax_rate_id'    => $taxRate->id,
                    'tax_rate_value' => 18,
                    'unit_id'        => $unit->id,
                ],
            ],
        ]);

        // 5 * 300000 * 0.9 = 1_350_000 HT; TVA = 243_000; TTC = 1_593_000
        expect($quote)->toBeInstanceOf(Quote::class)
            ->and($quote->status)->toBe('brouillon')
            ->and($quote->subtotal_ht)->toBe(1_350_000)
            ->and($quote->total_tax)->toBe(243_000)
            ->and($quote->total_ttc)->toBe(1_593_000);
    });

    it('converts a quote to an order preserving all data', function () {
        $user    = quoteAdmin();
        $company = quoteCompany();
        $client  = Client::factory()->create(['is_active' => true]);
        $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18%'], ['short_name' => 'TVA18', 'rate' => 18, 'is_active' => true]);
        $unit    = Unit::firstOrCreate(['name' => 'Pièce'], ['abbreviation' => 'pcs']);

        $this->actingAs($user);

        $svc   = app(QuoteService::class);
        $quote = $svc->create([
            'client_id'  => $client->id,
            'issued_at'  => now()->toDateString(),
            'expires_at' => now()->addDays(30)->toDateString(),
            'items'      => [
                [
                    'description'    => 'Laptop Dell',
                    'quantity'       => 2,
                    'unit_price'     => 500_000,
                    'discount_percent' => 0,
                    'tax_rate_id'    => $taxRate->id,
                    'tax_rate_value' => 18,
                    'unit_id'        => $unit->id,
                ],
            ],
        ]);

        // Accept the quote first (required before conversion)
        $svc->accept($quote);
        $quote->refresh();

        $order = $svc->convertToOrder($quote);

        expect($order)->toBeInstanceOf(Order::class)
            ->and($order->client_id)->toBe($client->id)
            ->and($order->subtotal_ht)->toBe($quote->subtotal_ht)
            ->and($order->total_ttc)->toBe($quote->total_ttc)
            ->and($order->items)->toHaveCount(1);

        $quote->refresh();
        expect($quote->status)->toBe('converti');
    });

    it('cannot convert an already-converted quote', function () {
        $user   = quoteAdmin();
        $client = Client::factory()->create(['is_active' => true]);
        quoteCompany();

        $this->actingAs($user);

        $quote = Quote::factory()->create([
            'company_id' => quoteCompany()->id,
            'client_id'  => $client->id,
            'status'     => 'accepte',
        ]);

        $svc = app(QuoteService::class);

        expect(fn() => $svc->convertToOrder($quote))->toThrow(\RuntimeException::class);
    });

    it('returns 200 on quote index page', function () {
        $user = quoteAdmin();
        quoteCompany();
        $this->actingAs($user)
             ->get(route('ventes.devis.index'))
             ->assertOk();
    });

});
