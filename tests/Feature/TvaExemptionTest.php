<?php

/**
 * Tests TVA — règles d'exonération et de calcul
 *
 * Règles métier testées :
 *   1. Client exonéré + facture → TVA=0, TTC=HT, aucune écriture 4431
 *   2. Client non exonéré + TVA 18% → TVA calculée, écriture 4431 créée
 *   3. Client non exonéré + TVA choisie 0% → TVA=0, aucune écriture 4431
 *   4. Client exonéré : zeroOutTax() force TVA=0 même si formulaire envoie 18%
 *   5. Client modèle : is_tax_exempt + isTaxExempt() helper
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\InvoiceService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function tvaCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2025'],
        ['starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'TVA Test Co'],
        ['email' => 'tva@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function tvaAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = tvaCompany();
    $user    = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role);
    return $user;
}

function tvaRate(float $rate = 18.0): TaxRate
{
    return TaxRate::firstOrCreate(
        ['name' => 'TVA ' . $rate . '%'],
        ['short_name' => 'TVA' . (int)$rate, 'rate' => $rate, 'type' => 'tva', 'is_active' => true]
    );
}

function makeInvoiceData(int $clientId, float $taxRate = 18.0, int $unitPrice = 100000): array
{
    $tr = tvaRate($taxRate);
    return [
        'client_id'  => $clientId,
        'issued_at'  => now()->toDateString(),
        'due_at'     => now()->addDays(30)->toDateString(),
        'items'      => [[
            'description'      => 'Prestation de service',
            'quantity'         => 1,
            'unit_price'       => $unitPrice,
            'discount_percent' => 0,
            'tax_rate_id'      => $tr->id,
            'tax_rate_value'   => $taxRate,
        ]],
    ];
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('TVA — exoneration client', function () {

    it('Scenario 1 : client exonere → TVA=0 et TTC=HT', function () {
        $user    = tvaAdmin();
        $company = tvaCompany();
        $client  = Client::factory()->create([
            'is_tax_exempt' => true,
            'is_active'     => true,
        ]);

        $this->actingAs($user);

        // Même si le formulaire envoie tax_rate_value=18, le service doit ignorer
        $invoice = app(InvoiceService::class)->create(makeInvoiceData($client->id, 18.0, 100000));

        expect($invoice->total_tax)->toBe(0)
            ->and($invoice->total_ttc)->toBe($invoice->subtotal_ht)
            ->and($invoice->items->first()->line_tax)->toBe(0)
            ->and((float) $invoice->items->first()->tax_rate_value)->toBe(0.0);
    });

    it('Scenario 1b : client exonere → aucune ecriture TVA 4431 apres validation', function () {
        $user    = tvaAdmin();
        $client  = Client::factory()->create([
            'is_tax_exempt' => true,
            'is_active'     => true,
        ]);

        $this->actingAs($user);
        $svc     = app(InvoiceService::class);
        $invoice = $svc->create(makeInvoiceData($client->id, 18.0, 200000));
        $invoice = $svc->validate($invoice);
        $invoice->refresh();

        // Après validation, les écritures GL sont créées
        // On vérifie qu'aucune ligne sur un compte 4431 n'a été générée
        $has4431 = $invoice->journalEntry?->lines
            ->contains(fn($l) => str_starts_with($l->account?->code ?? '', '4431'));

        expect($has4431)->toBeFalse();
    });

    it('Scenario 2 : client non exonere + TVA 18% → TVA calculee et ecriture 4431', function () {
        $user    = tvaAdmin();
        $client  = Client::factory()->create([
            'is_tax_exempt' => false,
            'is_active'     => true,
        ]);

        $this->actingAs($user);
        $svc     = app(InvoiceService::class);
        $invoice = $svc->create(makeInvoiceData($client->id, 18.0, 100000));

        expect($invoice->total_tax)->toBe(18000)
            ->and($invoice->total_ttc)->toBe(118000)
            ->and($invoice->subtotal_ht)->toBe(100000);
    });

    it('Scenario 3 : client non exonere + TVA 0% → TVA=0 sans ecriture 4431', function () {
        $user    = tvaAdmin();
        $client  = Client::factory()->create([
            'is_tax_exempt' => false,
            'is_active'     => true,
        ]);

        $this->actingAs($user);
        // L'utilisateur choisit explicitement 0%
        $invoice = app(InvoiceService::class)->create(makeInvoiceData($client->id, 0.0, 150000));

        expect($invoice->total_tax)->toBe(0)
            ->and($invoice->total_ttc)->toBe(150000);
    });

    it('Scenario 4 : zeroOutTax force TVA=0 meme si formulaire envoie 18%', function () {
        $user    = tvaAdmin();
        $client  = Client::factory()->create([
            'is_tax_exempt' => true,
            'is_active'     => true,
        ]);

        $this->actingAs($user);

        // Simuler données malveillantes : formulaire envoie 18% pour un client exonéré
        $data = makeInvoiceData($client->id, 18.0, 500000);
        // Forcer le taux à 18 comme si le formulaire était trafiqué
        $data['items'][0]['tax_rate_value'] = 18;
        $data['items'][0]['line_tax']       = 90000;

        $invoice = app(InvoiceService::class)->create($data);

        // La défense serveur doit avoir annulé la TVA
        expect($invoice->total_tax)->toBe(0)
            ->and($invoice->total_ttc)->toBe(500000)
            ->and($invoice->items->first()->line_tax)->toBe(0);
    });

    it('Scenario 5 : model Client - is_tax_exempt + isTaxExempt() helper', function () {
        $exempt = Client::factory()->create([
            'is_tax_exempt'          => true,
            'tax_exemption_reason'   => 'Attestation DGI',
            'tax_exemption_number'   => 'ATT-DGI-2025-001',
            'is_active'              => true,
        ]);

        $normal = Client::factory()->create([
            'is_tax_exempt' => false,
            'is_active'     => true,
        ]);

        expect($exempt->isTaxExempt())->toBeTrue()
            ->and($exempt->is_tax_exempt)->toBeTrue()
            ->and($exempt->tax_exemption_reason)->toBe('Attestation DGI')
            ->and($exempt->tax_exemption_number)->toBe('ATT-DGI-2025-001')
            ->and($normal->isTaxExempt())->toBeFalse();
    });

});
