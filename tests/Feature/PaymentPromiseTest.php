<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\PaymentPromise;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function promiseCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Promise Test Co'],
        ['email' => 'promise@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function promiseAdmin(int $companyId): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $companyId]);
    $u->assignRole($role);
    return $u;
}

describe('PaymentPromise', function () {

    it('flags an en_attente promise with a past date as overdue', function () {
        $company = promiseCompany();
        $this->actingAs(promiseAdmin($company->id));
        $client = Client::factory()->create(['is_active' => true]);

        $promise = PaymentPromise::create([
            'company_id'    => $company->id,
            'client_id'     => $client->id,
            'amount'        => 250_000,
            'promised_date' => now()->subDays(3)->toDateString(),
            'status'        => 'en_attente',
        ]);

        expect($promise->isOverdue())->toBeTrue()
            ->and($promise->statusLabel())->toBe('En attente');
    });

    it('does not flag a future or kept promise as overdue', function () {
        $company = promiseCompany();
        $this->actingAs(promiseAdmin($company->id));
        $client = Client::factory()->create(['is_active' => true]);

        $future = PaymentPromise::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'amount' => 100_000,
            'promised_date' => now()->addDays(5)->toDateString(), 'status' => 'en_attente',
        ]);
        $kept = PaymentPromise::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'amount' => 100_000,
            'promised_date' => now()->subDays(5)->toDateString(), 'status' => 'tenue',
        ]);

        expect($future->isOverdue())->toBeFalse()
            ->and($kept->isOverdue())->toBeFalse();
    });

    it('returns 200 on the promesses index', function () {
        $company = promiseCompany();
        $this->actingAs(promiseAdmin($company->id));

        $this->get(route('promesses.index'))->assertOk();
    });

});
