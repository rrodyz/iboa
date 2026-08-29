<?php

/**
 * [PROD-01 — Phase 8] Détection de conflits d'ordonnancement — pas un
 * solveur, un signal. Exemple de la mission : M1, OF1 08:00-12:00, OF2
 * 10:00-14:00 → CONFLICT = YES, chevauchement 08:00→12:00 ∩ 10:00→14:00
 * = 10:00→12:00 (120 min).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\SchedulingConflictService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function scfCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'SCF-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'SCF Co'], ['email' => 'scf@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

function scfOf(Company $co, ProductionLine $line, string $number, string $start, string $end): ProductionOrder
{
    [$startDate, $startTime] = explode(' ', $start);
    [$endDate, $endTime] = explode(' ', $end);

    return ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => $number,
        'status' => 'lance', 'quantity_requested' => 10, 'production_line_id' => $line->id,
        'date_debut_prevue' => $startDate, 'heure_debut_prevue' => $startTime,
        'date_fin_prevue' => $endDate, 'heure_fin_prevue' => $endTime,
    ]);
}

it('détecte un chevauchement M1 : OF1 08h-12h vs OF2 10h-14h', function () {
    $co = scfCompany();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M1', 'name' => 'Profileuse M1', 'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L-M1', 'name' => 'Ligne M1', 'is_active' => true]);

    $of1 = scfOf($co, $line, 'OF-CONF-1', '2026-09-01 08:00', '2026-09-01 12:00');
    $of2 = scfOf($co, $line, 'OF-CONF-2', '2026-09-01 10:00', '2026-09-01 14:00');

    $conflicts = app(SchedulingConflictService::class)->detect();

    expect($conflicts)->toHaveCount(1);
    $c = $conflicts->first();
    expect($c['production_line_id'])->toBe($line->id)
        ->and($c['overlap_minutes'])->toBe(120.0)
        ->and(collect([$c['of_a']->id, $c['of_b']->id])->sort()->values()->all())->toBe(collect([$of1->id, $of2->id])->sort()->values()->all());
});

it('ne détecte rien quand les fenêtres ne se chevauchent pas', function () {
    $co = scfCompany();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M2', 'name' => 'M2', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L-M2', 'name' => 'Ligne M2', 'is_active' => true]);

    scfOf($co, $line, 'OF-NOCONF-1', '2026-09-01 08:00', '2026-09-01 10:00');
    scfOf($co, $line, 'OF-NOCONF-2', '2026-09-01 10:00', '2026-09-01 12:00'); // contigu, pas de chevauchement

    expect(app(SchedulingConflictService::class)->detect())->toBeEmpty();
});

it('ne confond jamais deux lignes différentes : même horaire, ressources distinctes = pas de conflit', function () {
    $co = scfCompany();
    $m1 = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M3', 'name' => 'M3', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $m2 = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M4', 'name' => 'M4', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $l1 = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $m1->id, 'code' => 'L3', 'name' => 'Ligne 3', 'is_active' => true]);
    $l2 = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $m2->id, 'code' => 'L4', 'name' => 'Ligne 4', 'is_active' => true]);

    scfOf($co, $l1, 'OF-L3', '2026-09-01 08:00', '2026-09-01 12:00');
    scfOf($co, $l2, 'OF-L4', '2026-09-01 08:00', '2026-09-01 12:00');

    expect(app(SchedulingConflictService::class)->detect())->toBeEmpty();
});

it('ignore un OF terminé ou annulé même en cas de chevauchement horaire', function () {
    $co = scfCompany();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M5', 'name' => 'M5', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L5', 'name' => 'Ligne 5', 'is_active' => true]);

    $of1 = scfOf($co, $line, 'OF-DONE', '2026-09-01 08:00', '2026-09-01 12:00');
    $of1->update(['status' => 'termine']);
    scfOf($co, $line, 'OF-ACTIVE', '2026-09-01 10:00', '2026-09-01 14:00');

    expect(app(SchedulingConflictService::class)->detect())->toBeEmpty();
});

// [Clôture PROD-01 — section 7] Scénario exact de la directive à 3 OF sur M1 :
// OF1 08-12, OF2 10-14 (chevauchent), OF3 14-16 (contigu à OF2, PAS de
// chevauchement) -> exactement 1 conflit (OF1×OF2), OF3 non impliqué.
it('3 OF sur M1 : OF1×OF2 en conflit (120min), OF3 contigu à OF2 sans conflit', function () {
    $co = scfCompany();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M1-S7', 'name' => 'M1', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L-M1-S7', 'name' => 'Ligne M1', 'is_active' => true]);

    $of1 = scfOf($co, $line, 'OF1', '2026-09-01 08:00', '2026-09-01 12:00');
    $of2 = scfOf($co, $line, 'OF2', '2026-09-01 10:00', '2026-09-01 14:00');
    $of3 = scfOf($co, $line, 'OF3', '2026-09-01 14:00', '2026-09-01 16:00');

    $conflicts = app(SchedulingConflictService::class)->detect();

    expect($conflicts)->toHaveCount(1);
    $c = $conflicts->first();
    expect($c['overlap_minutes'])->toBe(120.0);
    $involved = collect([$c['of_a']->id, $c['of_b']->id]);
    expect($involved->contains($of1->id))->toBeTrue()
        ->and($involved->contains($of2->id))->toBeTrue()
        ->and($involved->contains($of3->id))->toBeFalse(); // OF3 jamais impliqué
});

// Même horaire (10-14), mais sur une ligne/machine DIFFÉRENTE et compatible :
// confirme la règle exacte -> la ressource partagée est ce qui déclenche le
// conflit, jamais le seul chevauchement horaire.
it('même créneau horaire sur une machine différente : jamais de conflit — seule la ressource partagée compte', function () {
    $co = scfCompany();
    $m1 = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M1-S7B', 'name' => 'M1', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $m2 = ProductionMachine::create(['company_id' => $co->id, 'code' => 'M2-S7B', 'name' => 'M2', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $l1 = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $m1->id, 'code' => 'L-M1-S7B', 'name' => 'Ligne M1', 'is_active' => true]);
    $l2 = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $m2->id, 'code' => 'L-M2-S7B', 'name' => 'Ligne M2', 'is_active' => true]);

    scfOf($co, $l1, 'OF-M1', '2026-09-01 10:00', '2026-09-01 14:00');
    scfOf($co, $l2, 'OF-M2', '2026-09-01 10:00', '2026-09-01 14:00');

    expect(app(SchedulingConflictService::class)->detect())->toBeEmpty();
});
