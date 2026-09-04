<?php

/**
 * [A3-QA-013] Régression ciblée : navigation Blade → React via Turbo.
 *
 * Root cause (voir rapport A3-QA-013) : Turbo Drive morph/head-merge une
 * navigation entre deux pages dont l'entrypoint Vite diffère (app.js/Turbo
 * vs react.jsx/Inertia) sans garantir que le nouveau <script type="module">
 * s'exécute avant le rendu du body — l'app React peut ne jamais monter
 * (#app vide, aucune erreur console), de façon non déterministe.
 *
 * Fix : App\Providers\ViteTurboTrackServiceProvider marque tous les tags
 * <script>/<link> générés par @vite avec `data-turbo-track="reload"` — le
 * mécanisme Turbo natif qui force un rechargement complet dès qu'un élément
 * tracké diffère entre la page courante et la cible. Ce test vérifie la
 * propriété structurelle exacte dont dépend le fix : l'attribut est présent
 * sur les deux entrypoints (donc toute transition Blade↔React redevient un
 * hard reload déterministe), et une page Blade→Blade (même entrypoint,
 * même tag) n'est pas affectée dans son contenu.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function turboTrackAdmin(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'TURBOTRACK-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'TurboTrack Co'], ['email' => 'turbotrack@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin'], ['guard_name' => 'web']));
    test()->actingAs($u);

    return $u;
}

it('QA-013 — le layout Blade (app.js/Turbo) porte data-turbo-track="reload" sur son entrypoint Vite', function () {
    turboTrackAdmin();

    $html = $this->get(route('production.orders.index'))->assertOk()->getContent();

    expect($html)->toMatch('/<script[^>]+app-[^>]+\.js[^>]+data-turbo-track="reload"/')
        ->and($html)->toMatch('/<script[^>]+app-[^>]+\.js/');
});

it('QA-013 — le root Inertia (react.jsx) porte data-turbo-track="reload" sur son entrypoint Vite', function () {
    turboTrackAdmin();

    $html = $this->get(route('production.dashboard'))->assertOk()->getContent();

    expect($html)->toMatch('/<script[^>]+react-[^>]+\.js[^>]+data-turbo-track="reload"/');
});

it('QA-013 — deux pages Blade successives partagent le même tag app.js tracké (pas de reload forcé Blade→Blade)', function () {
    turboTrackAdmin();

    $htmlA = $this->get(route('production.orders.index'))->assertOk()->getContent();
    $htmlB = $this->get(route('production.bom.index'))->assertOk()->getContent();

    preg_match('/<script[^>]+(app-[^"]+\.js)[^>]*>/', $htmlA, $matchA);
    preg_match('/<script[^>]+(app-[^"]+\.js)[^>]*>/', $htmlB, $matchB);

    expect($matchA[1] ?? null)->not->toBeNull()
        ->and($matchB[1] ?? null)->not->toBeNull()
        ->and($matchA[1])->toBe($matchB[1]);
});
