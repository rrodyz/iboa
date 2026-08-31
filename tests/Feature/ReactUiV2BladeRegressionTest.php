<?php

/**
 * [A3-UI-V2 — REACT-01A Phase 21] Aucune page métier ne doit avoir changé
 * après l'ajout de l'infra Inertia/React. Chaque page legacy doit : répondre
 * normalement, ne jamais porter d'en-tête Inertia, et charger app.js (Turbo)
 * — jamais react.jsx.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function bladeRegAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'BLADEREG-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'BladeReg Co'], ['email' => 'bladereg@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

// [REACT-01B] 'dashboard' est retiré de cette liste — c'est désormais la
// première page migrée vers Inertia/React (voir DashboardReactMigrationTest).
// [REACT-01C] 'production.dashboard' et 'production.orders.mto' retirés à leur
// tour — couverture déplacée vers ProductionDashboardReactMigrationTest et
// MtoReactMigrationTest.
// [REACT-01D] 'production.orders.mts' et 'production.mrp' retirés à leur tour
// — couverture déplacée vers MtsReactMigrationTest et MrpReactMigrationTest.
// [REACT-01E] 'production.planning' retiré à son tour — couverture déplacée
// vers PlanningReactMigrationTest. OF/BOM/Routings restent Blade.
$pages = [
    'production.orders.index' => 'production.orders.index',
    'production.bom.index' => 'production.bom.index',
    'production.routings.index' => 'production.routings.index',
];

foreach ($pages as $label => $routeName) {
    it("la page legacy Blade « {$label} » répond normalement, sans Inertia, avec Turbo toujours chargé", function () use ($routeName) {
        bladeRegAdmin();

        $response = $this->get(route($routeName));

        $response->assertOk();
        expect($response->headers->has('X-Inertia'))->toBeFalse();

        $html = $response->getContent();
        expect($html)->toContain('/build/assets/app-')
            ->and($html)->not->toContain('/build/assets/react-');
    });
}

it('la liste des clients (module Ventes/Gestion) répond normalement', function () {
    bladeRegAdmin();

    $response = $this->get(route('clients.index'));

    $response->assertOk();
    expect($response->headers->has('X-Inertia'))->toBeFalse();
});
