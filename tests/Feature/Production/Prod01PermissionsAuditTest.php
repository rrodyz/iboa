<?php

/**
 * [Clôture PROD-01 — section 13] Audit permissions des écrans/actions ajoutés
 * cette mission : MTO dashboard, planning/conflits. Aucune architecture de
 * permission modifiée — vérifie seulement que les gardes déjà en place
 * (permission:production.view / production.create sur les contrôleurs
 * existants, inchangées) couvrent bien les nouvelles actions.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function permAuditCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'PERMAUDIT-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);

    return Company::firstOrCreate(['name' => 'PermAudit Co'], ['email' => 'permaudit@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
}

function permAuditUser(Company $co, array $permissions): User
{
    app()->instance('current_company', $co);
    $role = Role::firstOrCreate(['name' => 'permaudit_'.md5(implode('|', $permissions)), 'guard_name' => 'web']);
    foreach ($permissions as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

// ── Commercial : aucun droit production ─────────────────────────────────────

it('un commercial sans droit production ne peut pas consulter le tableau de bord MTO', function () {
    $co = permAuditCompany();
    permAuditUser($co, ['ventes.view']); // profil commercial : vente uniquement

    $this->get(route('production.orders.mto'))->assertForbidden();
});

it('un commercial sans droit production ne peut pas créer un OF', function () {
    $co = permAuditCompany();
    permAuditUser($co, ['production.view']); // consultation seule, pas de création
    $p = Product::factory()->create(['is_manufacturable' => true]);

    $this->post(route('production.orders.store'), [
        'product_id' => $p->id, 'quantity_requested' => 10,
    ])->assertForbidden();

    expect(ProductionOrder::where('product_id', $p->id)->exists())->toBeFalse();
});

it('un commercial sans droit production voit le tableau de bord MTO sans jamais le lien « Créer OF »', function () {
    $co = permAuditCompany();
    permAuditUser($co, ['production.view']);

    $this->get(route('production.orders.mto'))->assertOk()->assertDontSee('Créer OF');
});

// ── Magasinier : consultation production, pas de pilotage ──────────────────

it('un magasinier (production.view seul) ne peut pas replanifier un OF', function () {
    $co = permAuditCompany();
    permAuditUser($co, ['production.view']);
    $line = ProductionLine::create(['company_id' => $co->id, 'code' => 'L-PERM', 'name' => 'Ligne', 'is_active' => true]);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PERM',
        'status' => 'lance', 'quantity_requested' => 10, 'production_line_id' => $line->id,
    ]);

    $this->post(route('production.planning.replan', $of), ['date_fabrication_prevue' => '2026-09-15'])
        ->assertForbidden();
});

// [Constat] Le plan de charge exige déjà production.update en lecture ET en
// écriture (route pré-existante, non modifiée par PROD-01) — c'est du
// pilotage production, pas une simple consultation. Un magasinier avec
// seulement production.view n'y a PAS accès, pas même en lecture. Plus
// strict que supposé initialement, mais c'est le comportement RÉEL et
// intentionnel : le corriger ici serait modifier une architecture de
// permission hors périmètre PROD-01, explicitement interdit.
it('un magasinier (production.view seul) n’a même pas accès en lecture au plan de charge (production.update requis)', function () {
    $co = permAuditCompany();
    permAuditUser($co, ['production.view']);

    $this->get(route('production.planning'))->assertForbidden();
});

// ── Chef d'atelier / production.update : pilote sans être super_admin ──────

it('un chef d’atelier (production.update, sans super_admin) peut replanifier avec production.create additionnel', function () {
    $co = permAuditCompany();
    permAuditUser($co, ['production.view', 'production.update', 'production.create']);
    $line = ProductionLine::create(['company_id' => $co->id, 'code' => 'L-PERM2', 'name' => 'Ligne2', 'is_active' => true]);
    $of = ProductionOrder::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'number' => 'OF-PERM2',
        'status' => 'lance', 'quantity_requested' => 10, 'production_line_id' => $line->id,
    ]);

    $this->post(route('production.planning.replan', $of), ['date_fabrication_prevue' => '2026-09-15'])
        ->assertRedirect();

    expect($of->fresh()->date_fabrication_prevue->format('Y-m-d'))->toBe('2026-09-15');
});
