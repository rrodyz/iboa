<?php

/**
 * [PROD-01 — Phase 6] Calendrier de capacité : jours ouvrés réels sur un
 * horizon, week-ends et jours fériés déclarés exclus.
 *
 * Exemple de la mission : horizon vendredi → lundi (3 jours calendaires,
 * 28→30 août 2026) ne doit compter que le vendredi comme jour ouvré — pas
 * samedi ni dimanche.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ProductionCalendarException;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\CapacityCalendarService;
use App\Modules\Production\Services\PlanningService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function calCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CAL-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'Cal Co'], ['email' => 'cal@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $co;
}

it('exclut le week-end : horizon vendredi 3 jours = 1 seul jour ouvré', function () {
    calCompany();
    // 2026-08-28 = vendredi, +2 = samedi 29, dimanche 30.
    $count = app(CapacityCalendarService::class)->workingDaysInHorizon(Carbon::parse('2026-08-28'), 3, currentCompany()->id);

    expect($count)->toBe(1);
});

it('un horizon lundi→vendredi (5 jours) compte 5 jours ouvrés sans jour férié déclaré', function () {
    calCompany();
    $count = app(CapacityCalendarService::class)->workingDaysInHorizon(Carbon::parse('2026-08-31'), 5, currentCompany()->id);

    expect($count)->toBe(5);
});

it('exclut un jour férié déclaré en semaine, en plus des week-ends', function () {
    $co = calCompany();
    ProductionCalendarException::create(['company_id' => $co->id, 'date' => '2026-09-01', 'label' => 'Fête nationale']);

    // Lundi 31 août -> +5 jours : lun31, mar1(férié), mer2, jeu3, ven4 = 4 ouvrés.
    $count = app(CapacityCalendarService::class)->workingDaysInHorizon(Carbon::parse('2026-08-31'), 5, currentCompany()->id);

    expect($count)->toBe(4);
});

it('un jour férié déclaré pour une AUTRE société ne réduit pas le calendrier de celle-ci', function () {
    $co = calCompany();
    $other = Company::factory()->create();
    ProductionCalendarException::create(['company_id' => $other->id, 'date' => '2026-09-01', 'label' => 'Férié autre société']);

    $count = app(CapacityCalendarService::class)->workingDaysInHorizon(Carbon::parse('2026-08-31'), 5, $co->id);

    expect($count)->toBe(5);
});

it('le plan de charge par centre reflète les jours ouvrés réels, pas l’horizon calendaire brut', function () {
    $co = calCompany();
    $wc = WorkCenter::create(['company_id' => $co->id, 'code' => 'CAL-C1', 'name' => 'Découpe', 'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 100, 'is_active' => true]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-CAL', 'status' => 'en_cours', 'quantity_requested' => 10]);
    $of->operations()->create(['company_id' => $co->id, 'work_center_id' => $wc->id, 'sequence' => 10, 'name' => 'A', 'planned_minutes' => 240, 'status' => 'pending']);

    // Horizon vendredi + 3 jours calendaires = 1 seul jour ouvré -> capacité = 8h, pas 24h.
    $plan = app(PlanningService::class)->loadByWorkCenter(3, Carbon::parse('2026-08-28'));
    $row = collect($plan['rows'])->firstWhere('id', $wc->id);

    expect($row['capacity_h'])->toBe(8.0);
});

// [Clôture PROD-01 — section 5] Exemple EXACT de la directive : capacité
// 8h/j, horizon vendredi→lundi inclus (4 jours calendaires : ven28, sam29,
// dim30, lun31) = 2 jours ouvrés (ven + lun) -> capacité théorique 16h, PAS 32h.
it('horizon vendredi->lundi inclus : capacité théorique = 16h (2 jours ouvrés), pas 32h', function () {
    $co = calCompany();
    $wc = WorkCenter::create(['company_id' => $co->id, 'code' => 'CAL-C2', 'name' => 'Profilage', 'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 100, 'is_active' => true]);

    $count = app(CapacityCalendarService::class)->workingDaysInHorizon(Carbon::parse('2026-08-28'), 4, $co->id);
    expect($count)->toBe(2); // vendredi + lundi

    $plan = app(PlanningService::class)->loadByWorkCenter(4, Carbon::parse('2026-08-28'));
    $row = collect($plan['rows'])->firstWhere('id', $wc->id);
    expect($row['capacity_h'])->toBe(16.0); // 8h × 2 jours ouvrés, pas 8h × 4 = 32h
});

// Puis lundi déclaré férié/non ouvré -> ne reste que le vendredi -> capacité = 8h.
it('lundi déclaré férié sur le même horizon : capacité théorique tombe à 8h', function () {
    $co = calCompany();
    $wc = WorkCenter::create(['company_id' => $co->id, 'code' => 'CAL-C3', 'name' => 'Profilage2', 'capacity_hours_per_day' => 8, 'cost_per_hour' => 5000, 'efficiency_rate' => 100, 'is_active' => true]);
    ProductionCalendarException::create(['company_id' => $co->id, 'date' => '2026-08-31', 'label' => 'Fermeture exceptionnelle']);

    $count = app(CapacityCalendarService::class)->workingDaysInHorizon(Carbon::parse('2026-08-28'), 4, $co->id);
    expect($count)->toBe(1); // seul le vendredi reste

    $plan = app(PlanningService::class)->loadByWorkCenter(4, Carbon::parse('2026-08-28'));
    $row = collect($plan['rows'])->firstWhere('id', $wc->id);
    expect($row['capacity_h'])->toBe(8.0);
});
