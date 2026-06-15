<?php

use App\Models\CashAccount;
use App\Models\CashClosure;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\CashOperation;
use App\Services\CashAccountService;
use App\Services\CashClosureService;
use App\Services\CashOperationService;
use App\Models\TreasuryApprovalThreshold;
use App\Services\CashTransferService;
use App\Services\TreasuryApprovalService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function tresAdmin(int $companyId): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u    = User::factory()->create(['company_id' => $companyId]);
    $u->assignRole($role);
    return $u;
}

function tresCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );

    return Company::firstOrCreate(
        ['name' => 'Treso Test Company'],
        ['email' => 'treso@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

function tresAccount(int $companyId, string $type, int $balance): CashAccount
{
    return CashAccount::factory()->create([
        'company_id'      => $companyId,
        'type'            => $type,
        'current_balance' => $balance,
        'is_active'       => true,
    ]);
}

function tresUserWithRole(int $companyId, string $role): User
{
    $perm = Permission::firstOrCreate(['name' => 'treasury.validate', 'guard_name' => 'web']);
    $r    = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $r->givePermissionTo($perm);
    $u = User::factory()->create(['company_id' => $companyId]);
    $u->assignRole($r);
    return $u;
}

// ─── Approbation par seuil ───────────────────────────────────────────────────────

describe('TreasuryApprovalService hierarchy', function () {

    it('requires a directeur for a 2M payment and blocks a comptable', function () {
        $company = tresCompany();
        TreasuryApprovalThreshold::insert([
            ['company_id' => $company->id, 'name' => 'Comptable', 'min_amount' => 0,        'max_amount' => 500_000,   'required_role' => 'comptable',   'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Directeur', 'min_amount' => 500_001,  'max_amount' => 5_000_000, 'required_role' => 'directeur',   'is_active' => true],
            ['company_id' => $company->id, 'name' => 'DG',        'min_amount' => 5_000_001, 'max_amount' => null,      'required_role' => 'super_admin', 'is_active' => true],
        ]);

        $svc       = app(TreasuryApprovalService::class);
        $rule      = $svc->findRequiredRule($company->id, 2_000_000);
        $comptable = tresUserWithRole($company->id, 'comptable');
        $directeur = tresUserWithRole($company->id, 'directeur');

        expect($rule)->not->toBeNull()
            ->and($rule->required_role)->toBe('directeur')
            ->and($svc->userCanApprove($comptable, $rule))->toBeFalse()
            ->and($svc->userCanApprove($directeur, $rule))->toBeTrue();
    });

    it('lets a super_admin approve any amount', function () {
        $company = tresCompany();
        TreasuryApprovalThreshold::insert([
            ['company_id' => $company->id, 'name' => 'DG', 'min_amount' => 5_000_001, 'max_amount' => null, 'required_role' => 'super_admin', 'is_active' => true],
        ]);

        $svc = app(TreasuryApprovalService::class);
        $rule = $svc->findRequiredRule($company->id, 10_000_000);
        $admin = tresAdmin($company->id);

        expect($svc->userCanApprove($admin, $rule))->toBeTrue();
    });

});

// ─── Virements internes ────────────────────────────────────────────────────────

describe('CashTransferService', function () {

    it('moves balance from source to destination and posts GL', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));

        $from = tresAccount($company->id, 'banque', 1_000_000);
        $to   = tresAccount($company->id, 'caisse', 0);

        $transfer = app(CashTransferService::class)->create([
            'from_cash_account_id' => $from->id,
            'to_cash_account_id'   => $to->id,
            'amount'               => 300_000,
            'transfer_date'        => now()->toDateString(),
        ]);

        expect((int) $from->fresh()->current_balance)->toBe(700_000)
            ->and((int) $to->fresh()->current_balance)->toBe(300_000)
            ->and($transfer->status)->toBe('valide')
            ->and($transfer->journal_entry_id)->not->toBeNull();
    });

    it('refuses a transfer that would overdraw the source', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));

        $from = tresAccount($company->id, 'banque', 100_000);
        $to   = tresAccount($company->id, 'caisse', 0);

        expect(fn () => app(CashTransferService::class)->create([
            'from_cash_account_id' => $from->id,
            'to_cash_account_id'   => $to->id,
            'amount'               => 500_000,
            'transfer_date'        => now()->toDateString(),
        ]))->toThrow(RuntimeException::class);
    });

    it('refuses identical source and destination', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));
        $acc = tresAccount($company->id, 'banque', 500_000);

        expect(fn () => app(CashTransferService::class)->create([
            'from_cash_account_id' => $acc->id,
            'to_cash_account_id'   => $acc->id,
            'amount'               => 100_000,
            'transfer_date'        => now()->toDateString(),
        ]))->toThrow(RuntimeException::class);
    });

});

// ─── Clôture de caisse ──────────────────────────────────────────────────────────

describe('CashClosureService', function () {

    it('adjusts the cash balance to the counted amount and posts the écart', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));

        $caisse = tresAccount($company->id, 'caisse', 200_000);

        $closure = app(CashClosureService::class)->create([
            'cash_account_id'   => $caisse->id,
            'counted_balance'   => 185_000, // manque de 15 000
            'closure_date'      => now()->toDateString(),
            'difference_reason' => 'Manque constaté au comptage',
        ]);

        expect((int) $closure->difference)->toBe(-15_000);

        $validated = app(CashClosureService::class)->validateClosure($closure);

        expect($validated->status)->toBe('valide')
            ->and((int) $caisse->fresh()->current_balance)->toBe(185_000);
    });

});

// ─── Opérations diverses de caisse ──────────────────────────────────────────────

describe('CashOperationService', function () {

    it('records a cash-in operation, increases balance and posts GL', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));
        $caisse = tresAccount($company->id, 'caisse', 100_000);

        $op = app(CashOperationService::class)->create([
            'cash_account_id' => $caisse->id,
            'direction'       => 'entree',
            'amount'          => 50_000,
            'operation_date'  => now()->toDateString(),
            'category'        => 'Apport en caisse',
        ]);

        expect((int) $caisse->fresh()->current_balance)->toBe(150_000)
            ->and($op->direction)->toBe('entree')
            ->and($op->journal_entry_id)->not->toBeNull();
    });

    it('records a cash-out operation and decreases balance', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));
        $caisse = tresAccount($company->id, 'caisse', 100_000);

        app(CashOperationService::class)->create([
            'cash_account_id' => $caisse->id,
            'direction'       => 'sortie',
            'amount'          => 30_000,
            'operation_date'  => now()->toDateString(),
            'category'        => 'Fournitures',
        ]);

        expect((int) $caisse->fresh()->current_balance)->toBe(70_000);
    });

    it('refuses a cash-out that would overdraw the caisse', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));
        $caisse = tresAccount($company->id, 'caisse', 20_000);

        expect(fn () => app(CashOperationService::class)->create([
            'cash_account_id' => $caisse->id,
            'direction'       => 'sortie',
            'amount'          => 50_000,
            'operation_date'  => now()->toDateString(),
        ]))->toThrow(RuntimeException::class);
    });

    it('cancels an operation and restores the balance', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));
        $caisse = tresAccount($company->id, 'caisse', 100_000);

        $op = app(CashOperationService::class)->create([
            'cash_account_id' => $caisse->id,
            'direction'       => 'entree',
            'amount'          => 40_000,
            'operation_date'  => now()->toDateString(),
        ]);
        expect((int) $caisse->fresh()->current_balance)->toBe(140_000);

        app(CashOperationService::class)->cancel($op, 'Erreur de saisie');

        expect((int) $caisse->fresh()->current_balance)->toBe(100_000)
            ->and($op->fresh()->status)->toBe('annule');
    });

});

// ─── Garde post-clôture ─────────────────────────────────────────────────────────

describe('post-closure guard', function () {

    it('blocks a movement dated on or before a validated closure', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));

        $caisse = tresAccount($company->id, 'caisse', 200_000);

        CashClosure::create([
            'company_id'          => $company->id,
            'cash_account_id'     => $caisse->id,
            'number'              => 'CLC-TEST-1',
            'closure_date'        => '2026-06-01',
            'theoretical_balance' => 200_000,
            'counted_balance'     => 200_000,
            'difference'          => 0,
            'status'              => 'valide',
        ]);

        expect(fn () => app(CashAccountService::class)->recordTransaction($caisse, [
            'type'             => 'debit',
            'amount'           => 10_000,
            'transaction_date' => '2026-06-01',
            'label'            => 'Mouvement antidaté',
        ]))->toThrow(RuntimeException::class);
    });

    it('allows a movement dated after the validated closure', function () {
        $company = tresCompany();
        $this->actingAs(tresAdmin($company->id));

        $caisse = tresAccount($company->id, 'caisse', 200_000);

        CashClosure::create([
            'company_id'          => $company->id,
            'cash_account_id'     => $caisse->id,
            'number'              => 'CLC-TEST-2',
            'closure_date'        => '2026-06-01',
            'theoretical_balance' => 200_000,
            'counted_balance'     => 200_000,
            'difference'          => 0,
            'status'              => 'valide',
        ]);

        $tx = app(CashAccountService::class)->recordTransaction($caisse, [
            'type'             => 'credit',
            'amount'           => 10_000,
            'transaction_date' => '2026-06-02',
            'label'            => 'Mouvement post-clôture',
        ]);

        expect((int) $caisse->fresh()->current_balance)->toBe(210_000)
            ->and((int) $tx->amount)->toBe(10_000);
    });

});
