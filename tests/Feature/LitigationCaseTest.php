<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\LitigationCase;
use App\Models\User;
use App\Services\LitigationService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function litigCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Litig Test Co'],
        ['email' => 'litig@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function litigAdmin(int $companyId): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $companyId]);
    $u->assignRole($role);
    return $u;
}

describe('LitigationService', function () {

    it('opens a litigation case', function () {
        $company = litigCompany();
        $this->actingAs(litigAdmin($company->id));
        $client = Client::factory()->create(['is_active' => true]);

        $case = app(LitigationService::class)->create([
            'client_id' => $client->id,
            'amount'    => 750_000,
            'stage'     => 'mise_en_demeure',
            'opened_at' => now()->toDateString(),
        ]);

        expect($case->status)->toBe('ouvert')
            ->and($case->number)->toStartWith('CTX-')
            ->and($case->journal_entry_id)->toBeNull();
    });

    it('posts a bad-debt entry when marked irrecouvrable, once only', function () {
        $company = litigCompany();
        $this->actingAs(litigAdmin($company->id));
        $client = Client::factory()->create(['is_active' => true]);

        $case = app(LitigationService::class)->create([
            'client_id' => $client->id,
            'amount'    => 500_000,
            'opened_at' => now()->toDateString(),
        ]);

        $case = app(LitigationService::class)->update($case, ['status' => 'irrecouvrable']);

        expect($case->status)->toBe('irrecouvrable')
            ->and($case->journal_entry_id)->not->toBeNull()
            ->and($case->closed_at)->not->toBeNull();

        $firstEntry = $case->journal_entry_id;

        // Idempotent : ré-update ne recrée pas d'écriture.
        $case = app(LitigationService::class)->update($case, ['status' => 'irrecouvrable', 'notes' => 'relance']);
        expect($case->journal_entry_id)->toBe($firstEntry);
    });

    it('returns 200 on contentieux index', function () {
        $company = litigCompany();
        $this->actingAs(litigAdmin($company->id));
        $this->get(route('contentieux.index'))->assertOk();
    });

});
