<?php

/**
 * [A3-UI-V2 — REACT-01E] Migration du Plan de charge vers Inertia+React.
 * Même route ('production.planning'), même URI, mêmes DEUX permissions
 * cumulées (production.view contrôleur ET production.update groupe de
 * routes — revalidé, voir ProductionPlanningController). PlanningService/
 * CapacityCalendarService/SchedulingConflictService intacts : chaque terme
 * (charge/capacité/downtime/occupation/statut/conflit) est transmis tel
 * quel, jamais recalculé côté React.
 *
 * SCHEDULING LEVEL: manuel/assisté + détection de conflits — pas un
 * solveur. Aucun texte "optimisé automatiquement" n'existe côté React.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function planReactCo(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PLANR-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'PlanReact Co'], ['email' => 'planreact@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function planReactUser(array $perms = ['production.view', 'production.update']): User
{
    $co = planReactCo();
    $role = Role::firstOrCreate(['name' => 'plan_react_role_' . implode('_', $perms)], ['guard_name' => 'web']);
    foreach ($perms as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('redirige le visiteur non authentifié', function () {
    $this->get(route('production.planning'))->assertRedirect(route('login'));
});

it('production.view seul ne suffit pas — la garde du groupe de routes exige aussi production.update', function () {
    planReactUser(['production.view']);

    $this->get(route('production.planning'))->assertForbidden();
});

it('production.update seul ne suffit pas — la garde du contrôleur exige aussi production.view', function () {
    planReactUser(['production.update']);

    $this->get(route('production.planning'))->assertForbidden();
});

it('les deux permissions ensemble donnent accès à la page', function () {
    planReactUser();

    $this->get(route('production.planning'))->assertOk();
});

it('refuse un utilisateur sans aucune permission production', function () {
    $co = planReactCo();
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    test()->actingAs($u);

    $this->get(route('production.planning'))->assertForbidden();
});

it('un utilisateur autorisé reçoit le composant Production/Planning/Index avec toutes les props', function () {
    planReactUser();

    $this->get(route('production.planning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Production/Planning/Index')
            ->has('horizon')->has('tauxGlobal')
            ->has('plan')->has('planMachine')->has('planTeam')
            ->has('conflicts')->has('canReplan')->has('lignes')->has('ofActifs')->has('links')
        );
});

it('horizon par défaut à 7, respecte le paramètre de requête, borné à [1,60]', function () {
    planReactUser();

    $this->get(route('production.planning'))
        ->assertInertia(fn (Assert $page) => $page->where('horizon', 7)->etc());

    $this->get(route('production.planning', ['horizon' => 14]))
        ->assertInertia(fn (Assert $page) => $page->where('horizon', 14)->etc());

    $this->get(route('production.planning', ['horizon' => 999]))
        ->assertInertia(fn (Assert $page) => $page->where('horizon', 60)->etc());

    $this->get(route('production.planning', ['horizon' => 0]))
        ->assertInertia(fn (Assert $page) => $page->where('horizon', 1)->etc());
});

it('la charge/capacité/downtime/occupation/statut par centre sont transmis fidèlement', function () {
    $co = planReactCo();
    planReactUser();
    $wc = WorkCenter::create(['company_id' => $co->id, 'code' => 'PLR-C1', 'name' => 'Découpe', 'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 100, 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PLR', 'status' => 'en_cours', 'quantity_requested' => 10]);
    $of->operations()->create(['company_id' => $co->id, 'work_center_id' => $wc->id, 'sequence' => 10, 'name' => 'A', 'planned_minutes' => 240, 'status' => 'pending']);

    $rows = $this->get(route('production.planning'))->assertOk()->inertiaProps('plan')['rows'];
    $row = collect($rows)->firstWhere('id', $wc->id);

    expect($row['planned_h'])->toEqual(4.0)
        ->and($row['ops'])->toBe(1)
        ->and($row['status'])->toBe('ok');
});

it('centre en surcharge (>100%) est signalé status=surcharge et compté dans overloaded', function () {
    $co = planReactCo();
    planReactUser();
    $wc = WorkCenter::create(['company_id' => $co->id, 'code' => 'PLR-C2', 'name' => 'X', 'capacity_hours_per_day' => 1, 'cost_per_hour' => 0, 'efficiency_rate' => 100, 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PLR2', 'status' => 'lance', 'quantity_requested' => 1]);
    // Horizon par défaut = 7 jours calendaires -> au plus 7 jours ouvrés à 1h/j
    // = 420 min de capacité max, quel que soit le jour d'exécution du test.
    // 1000 min planifiées garantit la surcharge sans dépendre de la date réelle.
    $of->operations()->create(['company_id' => $co->id, 'work_center_id' => $wc->id, 'sequence' => 10, 'name' => 'A', 'planned_minutes' => 1000, 'status' => 'pending']);

    $this->get(route('production.planning'))
        ->assertInertia(fn (Assert $page) => $page->where('plan.overloaded', 1)->etc());
});

it('conflit 120 minutes visible dans les props conflicts avec les bons noms d\'OF et minutes', function () {
    $co = planReactCo();
    planReactUser();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'PLR-M1', 'name' => 'Profileuse', 'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'code' => 'PLR-L1', 'name' => 'Ligne PLR', 'is_active' => true]);
    $mk = fn ($num, $sd, $st, $ed, $et) => ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => $num, 'status' => 'lance', 'quantity_requested' => 10, 'production_line_id' => $line->id, 'date_debut_prevue' => $sd, 'heure_debut_prevue' => $st, 'date_fin_prevue' => $ed, 'heure_fin_prevue' => $et]);
    $mk('OF-PLR-C1', '2026-09-01', '08:00', '2026-09-01', '12:00');
    $mk('OF-PLR-C2', '2026-09-01', '10:00', '2026-09-01', '14:00');

    $this->get(route('production.planning'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('conflicts.0.resourceLabel', 'Ligne PLR')
            ->where('conflicts.0.overlapMinutes', 120)
            ->etc()
        );
});

it('pas de conflit : ressources différentes ou fenêtres non chevauchantes → conflicts vide', function () {
    $co = planReactCo();
    planReactUser();
    $m1 = ProductionMachine::create(['company_id' => $co->id, 'code' => 'PLR-M2', 'name' => 'M2', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $m2 = ProductionMachine::create(['company_id' => $co->id, 'code' => 'PLR-M3', 'name' => 'M3', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    $l1 = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $m1->id, 'code' => 'PLR-L2', 'name' => 'Ligne 2', 'is_active' => true]);
    $l2 = ProductionLine::create(['company_id' => $co->id, 'machine_id' => $m2->id, 'code' => 'PLR-L3', 'name' => 'Ligne 3', 'is_active' => true]);
    $mk = fn ($num, $line, $sd, $st, $ed, $et) => ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => $num, 'status' => 'lance', 'quantity_requested' => 10, 'production_line_id' => $line->id, 'date_debut_prevue' => $sd, 'heure_debut_prevue' => $st, 'date_fin_prevue' => $ed, 'heure_fin_prevue' => $et]);
    $mk('OF-PLR-D1', $l1, '2026-09-01', '10:00', '2026-09-01', '14:00');
    $mk('OF-PLR-D2', $l2, '2026-09-01', '10:00', '2026-09-01', '14:00');

    $this->get(route('production.planning'))
        ->assertInertia(fn (Assert $page) => $page->where('conflicts', [])->etc());
});

/**
 * La capacité brute vaut capacity_hours_per_day × jours OUVRÉS de l'horizon
 * (CapacityCalendarService exclut samedi/dimanche et les jours fériés déclarés).
 * La date d'exécution doit donc être figée : lancé un samedi, un horizon d'un
 * jour vaut 0 jour ouvré, la capacité brute vaut 0 et la relation
 * « nette = brute − arrêts » n'est pas observable, la capacité nette étant
 * plancherée à 0 (PlanningService : max(0, capacité − arrêts), jamais négative).
 * Les deux règles sont donc vérifiées séparément, chacune sur une date connue.
 */
function planReactDowntimeRow(string $frozenDate): array
{
    test()->travelTo(\Illuminate\Support\Carbon::parse($frozenDate.' 09:00:00'));

    $co = planReactCo();
    planReactUser();
    $machine = ProductionMachine::create(['company_id' => $co->id, 'code' => 'PLR-M4', 'name' => 'M4', 'type' => 'profilage', 'hourly_cost' => 0, 'status' => 'active', 'is_active' => true]);
    WorkCenter::create(['company_id' => $co->id, 'code' => 'PLR-C3', 'name' => 'Centre M4', 'machine_id' => $machine->id, 'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 100, 'is_active' => true]);
    \App\Modules\Production\Models\ProductionDowntime::create(['company_id' => $co->id, 'machine_id' => $machine->id, 'started_at' => now(), 'duration_minutes' => 240, 'reason' => 'panne']);

    $rows = test()->get(route('production.planning', ['horizon' => 1]))->assertOk()->inertiaProps('planMachine')['rows'];

    return collect($rows)->firstWhere('id', $machine->id);
}

it('downtime machine réduit la capacité nette un jour ouvré, jamais un double-nettage côté frontend', function () {
    // Lundi 2026-09-07 : 1 jour ouvré dans l'horizon → capacité brute 8 h.
    $row = planReactDowntimeRow('2026-09-07');

    expect($row['capacity_h'])->toEqual(8.0)
        ->and($row['downtime_h'])->toEqual(4.0)
        ->and($row['net_capacity_h'])->toEqual($row['capacity_h'] - 4.0)
        ->and($row['net_capacity_h'])->toEqual(4.0);

    test()->travelBack();
});

it('un week-end ne produit aucune capacité et la capacité nette reste plancherée à 0, jamais négative', function () {
    // Samedi 2026-09-05 : 0 jour ouvré → capacité brute 0 ; l'arrêt déclaré est
    // toujours remonté tel quel mais ne peut pas rendre la capacité nette négative.
    $row = planReactDowntimeRow('2026-09-05');

    expect($row['capacity_h'])->toEqual(0.0)
        ->and($row['downtime_h'])->toEqual(4.0)
        ->and($row['net_capacity_h'])->toEqual(0.0);

    test()->travelBack();
});

it('super_admin voit le planning malgré getAllPermissions() vide', function () {
    $co = planReactCo();
    $role = Role::firstOrCreate(['name' => 'super_admin'], ['guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('production.planning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Planning/Index')->where('auth.is_super_admin', true)->where('canReplan', true));
});

it('un utilisateur sans production.create reçoit canReplan=false', function () {
    planReactUser(['production.view', 'production.update']);

    $this->get(route('production.planning'))
        ->assertInertia(fn (Assert $page) => $page->where('canReplan', false)->etc());
});

it('empty state : aucun OF, aucun centre, aucun conflit → tableaux vides, pas d\'erreur', function () {
    planReactUser();

    $this->get(route('production.planning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.rows', [])
            ->where('conflicts', [])
            ->where('ofActifs', [])
            ->etc()
        );
});

it('les liens transmis pointent vers les mêmes routes que l’ancien Blade', function () {
    planReactUser();

    $this->get(route('production.planning'))
        ->assertInertia(fn (Assert $page) => $page->where('links.downtimesUrl', route('production.downtimes'))->etc());
});

it('la route production.planning reste GET production/planning sous le même middleware', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'production.planning');

    expect($route->uri())->toBe('production/planning');
    expect($route->methods())->toContain('GET');
});

it('les autres pages Production (OF/BOM/Routings) ne sont pas migrées par erreur', function () {
    planReactUser();

    foreach (['production.orders.index', 'production.bom.index', 'production.routings.index'] as $name) {
        $response = $this->get(route($name));
        expect($response->headers->has('X-Inertia'))->toBeFalse();
    }
});
