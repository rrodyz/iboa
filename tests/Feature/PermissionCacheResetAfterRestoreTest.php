<?php

/**
 * [R1 — SÉCURITÉ] Le cache Spatie (spatie.permission.cache, store par défaut,
 * TTL 24 h) mémorise le mapping rôle ↔ permission par identifiant. Après une
 * restauration / un reseed de la base qui remplace ce mapping sans passer par
 * les modèles Spatie (mysql < dump.sql, INSERT bruts), le cache encore présent
 * fait résoudre les permissions d'un AUTRE rôle : observé en recette
 * (employe → accès stock/production, caissier → écrans RH, lecture_seule → 403).
 *
 * Ce test reproduit le mécanisme sur la base de test dédiée et fige le contrat
 * opérationnel : après un remplacement des données d'autorisation, exécuter
 * `cache:clear` (suffisant pour le store par défaut, prouvé ici) ET
 * `permission:cache-reset` (vide aussi le registre en mémoire — indispensable
 * pour les processus longs : queue workers) avant de remettre en service.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(\Tests\Concerns\RefreshDatabase::class);

function r1User(string $role): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'R1-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'R1 Co'], ['email' => 'r1@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);

    return $u;
}

/** Remplace le mapping rôle→permission en SQL brut, comme le ferait un restore/reseed. */
function r1RawSwap(string $role, string $from, string $to): void
{
    $roleId = Role::where('name', $role)->value('id');
    DB::table('role_has_permissions')->where('role_id', $roleId)->where('permission_id', Permission::where('name', $from)->value('id'))->delete();
    DB::table('role_has_permissions')->insert(['role_id' => $roleId, 'permission_id' => Permission::where('name', $to)->value('id')]);
    // Un restore ne passe pas par les modèles : le registre en mémoire du process reste tel quel.
}

beforeEach(function () {
    Permission::findOrCreate('r1.stocks.view', 'web');
    Permission::findOrCreate('r1.rh.view', 'web');
    Role::findOrCreate('r1_employe', 'web')->syncPermissions(['r1.rh.view']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('R1 — un cache Spatie obsolète fait survivre une autorisation supprimée en base (reproduction du risque)', function () {
    $u = r1User('r1_employe');

    expect($u->can('r1.rh.view'))->toBeTrue()
        ->and($u->can('r1.stocks.view'))->toBeFalse()
        ->and(Cache::has('spatie.permission.cache'))->toBeTrue();

    r1RawSwap('r1_employe', 'r1.rh.view', 'r1.stocks.view');

    // Cache + registre non invalidés : l'application continue d'accorder l'ANCIENNE permission
    // et refuse la NOUVELLE — c'est exactement la confusion de privilèges observée.
    $stale = User::find($u->id);
    expect($stale->can('r1.rh.view'))->toBeTrue()
        ->and($stale->can('r1.stocks.view'))->toBeFalse();
});

it('R1 — permission:cache-reset rétablit immédiatement la matrice réelle', function () {
    $u = r1User('r1_employe');
    $u->can('r1.rh.view');
    r1RawSwap('r1_employe', 'r1.rh.view', 'r1.stocks.view');

    Artisan::call('permission:cache-reset');

    $fresh = User::find($u->id);
    expect(Cache::has('spatie.permission.cache'))->toBeFalse()
        ->and($fresh->can('r1.rh.view'))->toBeFalse()
        ->and($fresh->can('r1.stocks.view'))->toBeTrue();
});

it('R1 — cache:clear évince bien la clé Spatie du store par défaut (preuve deploy.sh), mais pas le registre en mémoire', function () {
    $u = r1User('r1_employe');
    $u->can('r1.rh.view');
    expect(Cache::has('spatie.permission.cache'))->toBeTrue();

    r1RawSwap('r1_employe', 'r1.rh.view', 'r1.stocks.view');
    Artisan::call('cache:clear');

    expect(Cache::has('spatie.permission.cache'))->toBeFalse();

    // Même process (cas queue worker / octane) : le registre mémoire sert encore l'ancien mapping.
    $same = User::find($u->id);
    expect($same->can('r1.rh.view'))->toBeTrue();

    // Le reset Spatie vide aussi le registre : la matrice réelle est servie.
    Artisan::call('permission:cache-reset');
    $fresh = User::find($u->id);
    expect($fresh->can('r1.rh.view'))->toBeFalse()
        ->and($fresh->can('r1.stocks.view'))->toBeTrue();
});
