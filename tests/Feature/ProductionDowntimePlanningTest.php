<?php

/**
 * [PRO Temps d'arrêt + Plan de charge machine/équipe]
 *
 *  - déclaration d'un arrêt (durée calculée, machine déduite de l'OF) ;
 *  - clôture d'un arrêt en cours ;
 *  - stats par machine / cause ;
 *  - plan de charge agrégé par machine (capacité nette = brute − arrêts) et par équipe.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionDowntime;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderOperation;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\PlanningService;
use App\Modules\Production\Services\ProductionDowntimeService;
use Carbon\Carbon;

uses(\Tests\Concerns\RefreshDatabase::class);

function pdtSetup(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PDT'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PDT Co'], ['email' => 'pdt@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id]);
    test()->actingAs($u);

    $machine = ProductionMachine::create([
        'company_id' => $co->id, 'code' => 'MX-PDT', 'name' => 'Profileuse PDT',
        'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create([
        'company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'L-PDT', 'name' => 'Ligne PDT', 'is_active' => true,
    ]);

    return compact('co', 'u', 'machine', 'line');
}

it('déclare un arrêt et calcule sa durée, machine déduite de l’OF', function () {
    $ctx = pdtSetup();
    $product = Product::factory()->create();
    $of = ProductionOrder::factory()->create([
        'company_id' => $ctx['co']->id, 'product_id' => $product->id,
        'production_line_id' => $ctx['line']->id, 'status' => 'en_cours',
    ]);

    $dt = app(ProductionDowntimeService::class)->declare([
        'production_order_id' => $of->id,
        'category' => 'non_planifie', 'reason' => 'panne',
        'started_at' => '2026-07-13 08:00:00', 'ended_at' => '2026-07-13 09:30:00',
        'description' => 'Panne moteur',
    ]);

    expect($dt->duration_minutes)->toBe(90)
        ->and($dt->machine_id)->toBe($ctx['machine']->id)   // déduite de la ligne de l'OF
        ->and($dt->isOngoing())->toBeFalse();
});

it('clôture un arrêt en cours', function () {
    $ctx = pdtSetup();
    $dt = app(ProductionDowntimeService::class)->declare([
        'machine_id' => $ctx['machine']->id, 'category' => 'non_planifie', 'reason' => 'reglage',
        'started_at' => now()->subMinutes(45)->toDateTimeString(),
    ]);
    expect($dt->isOngoing())->toBeTrue();

    $closed = app(ProductionDowntimeService::class)->close($dt);
    expect($closed->isOngoing())->toBeFalse()
        ->and($closed->duration_minutes)->toBeGreaterThanOrEqual(44);
});

it('agrège les stats par machine et par cause', function () {
    $ctx = pdtSetup();
    $svc = app(ProductionDowntimeService::class);
    $svc->declare(['machine_id' => $ctx['machine']->id, 'category' => 'non_planifie', 'reason' => 'panne', 'started_at' => '2026-07-13 08:00:00', 'ended_at' => '2026-07-13 09:00:00']); // 60
    $svc->declare(['machine_id' => $ctx['machine']->id, 'category' => 'non_planifie', 'reason' => 'panne', 'started_at' => '2026-07-13 10:00:00', 'ended_at' => '2026-07-13 10:30:00']); // 30

    $byMachine = $svc->statsByMachine(365);
    expect((int) $byMachine[$ctx['machine']->id]->minutes)->toBe(90);

    $byReason = $svc->statsByReason(365);
    expect($byReason[0]['reason'])->toBe('panne')
        ->and($byReason[0]['minutes'])->toBe(90);
})->skip(fn () => now()->diffInDays('2026-07-13') > 300, 'Fenêtre 365j dépend de la date courante.');

it('calcule le plan de charge par machine avec capacité nette (brute − arrêts)', function () {
    $ctx = pdtSetup();
    $wc = WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-PDT', 'name' => 'Poste PDT',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true, 'default_team' => 'Équipe A',
    ]);
    $product = Product::factory()->create();
    $of = ProductionOrder::factory()->create(['company_id' => $ctx['co']->id, 'product_id' => $product->id, 'status' => 'lance']);
    ProductionOrderOperation::create([
        'company_id' => $ctx['co']->id, 'production_order_id' => $of->id, 'work_center_id' => $wc->id,
        'name' => 'Profilage', 'sequence' => 1, 'planned_minutes' => 120, 'status' => 'pending',
    ]);
    // Arrêt de 60 min sur la machine → réduit la capacité nette
    app(ProductionDowntimeService::class)->declare([
        'machine_id' => $ctx['machine']->id, 'category' => 'non_planifie', 'reason' => 'panne',
        'started_at' => now()->subHours(2)->toDateTimeString(), 'ended_at' => now()->subHour()->toDateTimeString(),
    ]);

    // [PROD-01 Phase 6] horizon ancré un lundi : 1 jour ouvré garanti quel que soit le jour d'exécution du test.
    $plan = app(PlanningService::class)->loadByMachine(1, Carbon::parse('2026-08-31'));
    $row  = collect($plan['rows'])->firstWhere('id', $ctx['machine']->id);

    expect($row)->not->toBeNull()
        ->and($row['planned_h'])->toBe(2.0)          // 120 min
        ->and($row['capacity_h'])->toBe(8.0)          // 8h/j × 100% × 1j
        ->and($row['downtime_h'])->toBe(1.0)          // 60 min d'arrêt
        ->and($row['net_capacity_h'])->toBe(7.0);     // 8 − 1
});

it('calcule le plan de charge par équipe', function () {
    $ctx = pdtSetup();
    WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-A', 'name' => 'Poste A',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true, 'default_team' => 'Équipe A',
    ]);
    $plan = app(PlanningService::class)->loadByTeam(1, Carbon::parse('2026-08-31'));

    $row = collect($plan['rows'])->firstWhere('name', 'Équipe A');
    expect($row)->not->toBeNull()
        ->and($row['centers'])->toBe(1)
        ->and($row['capacity_h'])->toBe(8.0);
});

// [PROD-01 — Phase 7] Une machine en arrêt ne doit pas être comptée à 100%
// de disponibilité — ni au niveau CENTRE, ni au niveau ÉQUIPE (jusqu'ici
// seul loadByMachine() nettait les arrêts ; le centre et l'équipe affichaient
// une occupation optimiste, capacité brute, en pleine panne machine).
it('nette la capacité d’un CENTRE de la même machine en arrêt (pas seulement loadByMachine)', function () {
    $ctx = pdtSetup();
    $wc = WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-PDT2', 'name' => 'Poste PDT2',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true,
    ]);
    app(ProductionDowntimeService::class)->declare([
        'machine_id' => $ctx['machine']->id, 'category' => 'non_planifie', 'reason' => 'panne',
        'started_at' => now()->subHours(2)->toDateTimeString(), 'ended_at' => now()->subHour()->toDateTimeString(),
    ]);

    $plan = app(PlanningService::class)->loadByWorkCenter(1, Carbon::parse('2026-08-31'));
    $row = collect($plan['rows'])->firstWhere('id', $wc->id);

    expect($row['capacity_h'])->toBe(8.0)      // brute, affichée telle quelle
        ->and($row['downtime_h'])->toBe(1.0)
        ->and($row['net_capacity_h'])->toBe(7.0);
});

it('nette la capacité d’une ÉQUIPE quand une machine de ses centres est en arrêt', function () {
    $ctx = pdtSetup();
    WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-EQ', 'name' => 'Poste EQ',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true, 'default_team' => 'Équipe B',
    ]);
    app(ProductionDowntimeService::class)->declare([
        'machine_id' => $ctx['machine']->id, 'category' => 'non_planifie', 'reason' => 'panne',
        'started_at' => now()->subHours(2)->toDateTimeString(), 'ended_at' => now()->subHour()->toDateTimeString(),
    ]);

    $plan = app(PlanningService::class)->loadByTeam(1, Carbon::parse('2026-08-31'));
    $row = collect($plan['rows'])->firstWhere('name', 'Équipe B');

    expect($row['capacity_h'])->toBe(8.0)
        ->and($row['downtime_h'])->toBe(1.0)
        ->and($row['net_capacity_h'])->toBe(7.0);
});

// [Clôture PROD-01 — section 6] Exemple EXACT de la directive : M1 capacité
// 8h, maintenance 4h -> capacité nette = 4h.
it('M1 : capacité 8h, maintenance 4h -> capacité nette exacte = 4h', function () {
    $ctx = pdtSetup();
    WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-M1', 'name' => 'Poste M1',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true,
    ]);
    app(ProductionDowntimeService::class)->declare([
        'machine_id' => $ctx['machine']->id, 'category' => 'planifie', 'reason' => 'maintenance',
        'started_at' => now()->subHours(5)->toDateTimeString(), 'ended_at' => now()->subHour()->toDateTimeString(), // 4h
    ]);

    $plan = app(PlanningService::class)->loadByMachine(1, Carbon::parse('2026-08-31'));
    $row = collect($plan['rows'])->firstWhere('id', $ctx['machine']->id);

    expect($row['capacity_h'])->toBe(8.0)
        ->and($row['downtime_h'])->toBe(4.0)
        ->and($row['net_capacity_h'])->toBe(4.0);
});

// [Clôture PROD-01 — section 6] Propagation MACHINE -> CENTRE -> ÉQUIPE sans
// double comptage : une équipe avec DEUX centres rattachés à LA MÊME machine
// ne doit soustraire l'arrêt qu'UNE seule fois, pas une fois par centre.
it('la même indisponibilité machine n’est jamais soustraite deux fois au niveau équipe', function () {
    $ctx = pdtSetup();
    $wcA = WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-DBL-A', 'name' => 'Poste DBL A',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true, 'default_team' => 'Équipe Unique',
    ]);
    $wcB = WorkCenter::create([
        'company_id' => $ctx['co']->id, 'machine_id' => $ctx['machine']->id, 'code' => 'WC-DBL-B', 'name' => 'Poste DBL B',
        'capacity_hours_per_day' => 8, 'efficiency_rate' => 100, 'is_active' => true, 'default_team' => 'Équipe Unique',
    ]);
    app(ProductionDowntimeService::class)->declare([
        'machine_id' => $ctx['machine']->id, 'category' => 'planifie', 'reason' => 'maintenance',
        'started_at' => now()->subHours(5)->toDateTimeString(), 'ended_at' => now()->subHour()->toDateTimeString(), // 4h
    ]);

    // Chaque CENTRE affiche bien 4h d'arrêt individuellement (même machine).
    $planWc = app(PlanningService::class)->loadByWorkCenter(1, Carbon::parse('2026-08-31'));
    expect(collect($planWc['rows'])->firstWhere('id', $wcA->id)['downtime_h'])->toBe(4.0);
    expect(collect($planWc['rows'])->firstWhere('id', $wcB->id)['downtime_h'])->toBe(4.0);

    // Mais l'ÉQUIPE (2 centres, MÊME machine) ne compte l'arrêt qu'UNE fois :
    // capacité brute 16h (8+8), arrêt 4h (pas 8h), nette 12h (pas 8h).
    $planTeam = app(PlanningService::class)->loadByTeam(1, Carbon::parse('2026-08-31'));
    $team = collect($planTeam['rows'])->firstWhere('name', 'Équipe Unique');

    expect($team['capacity_h'])->toBe(16.0)
        ->and($team['downtime_h'])->toBe(4.0)      // jamais 8.0
        ->and($team['net_capacity_h'])->toBe(12.0); // jamais 8.0
});
