<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\CashAccount;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Services\TreasurySimulationService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function simCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'Sim Test Co'], ['email' => 'simt@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function simAdmin(int $companyId): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $companyId]);
    $u->assignRole($role);
    return $u;
}

describe('TreasurySimulationService', function () {

    it('builds horizon+1 weekly buckets from the current balance', function () {
        $company = simCompany();
        $this->actingAs(simAdmin($company->id));
        CashAccount::factory()->create(['company_id' => $company->id, 'type' => 'banque', 'current_balance' => 1_000_000, 'is_active' => true]);

        $res = app(TreasurySimulationService::class)->simulate(['horizon_weeks' => 10]);

        expect($res['buckets'])->toHaveCount(11)
            ->and($res['start'])->toBe(1_000_000)
            ->and($res['buckets'][0]['balance'])->toBeGreaterThanOrEqual(0);
    });

    it('applies the recovery rate to projected client inflows', function () {
        $company = simCompany();
        $this->actingAs(simAdmin($company->id));
        CashAccount::factory()->create(['company_id' => $company->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);
        $client = Client::factory()->create(['is_active' => true]);
        Invoice::factory()->create([
            'company_id' => $company->id, 'client_id' => $client->id, 'status' => 'emise',
            'total_ttc' => 1_000_000, 'paid_amount' => 0, 'remaining_amount' => 1_000_000,
            'due_at' => now()->addWeeks(2)->toDateString(),
        ]);

        $full = app(TreasurySimulationService::class)->simulate(['recovery_rate' => 100, 'horizon_weeks' => 8]);
        $half = app(TreasurySimulationService::class)->simulate(['recovery_rate' => 50, 'horizon_weeks' => 8]);

        expect($full['total_in'])->toBe(1_000_000)
            ->and($half['total_in'])->toBe(500_000);
    });

    it('returns 200 on the simulations page', function () {
        $company = simCompany();
        $this->actingAs(simAdmin($company->id));
        $this->get(route('tresorerie.simulations.index'))->assertOk();
    });

});
