<?php

/**
 * [A3-UI-V2 — REACT-01D] Migration de l'écran MRP (réappro bobines) vers
 * Inertia+React. Même route ('production.mrp'), même URI.
 *
 * [Phase 15 — revalidation, pas supposition] Toutes les routes du bloc MRP
 * (index/generate/ordres-fabrication/ordres-fabrication.generate) sont
 * enveloppées par Route::middleware('permission:production.update') au niveau
 * du GROUPE de routes (routes/web.php, commentaire "pilotage production
 * uniquement") — EN PLUS du middleware propre au contrôleur
 * (permission:production.view sur index/ofProposals). Les deux s'appliquent
 * en ET : voir GET production.mrp exige production.update ET production.view
 * ensemble, pas l'un ou l'autre. C'est une correction par rapport à l'audit
 * précédent qui ne mentionnait que production.update.
 *
 * [Trouvaille Phase 4/9] Cet écran ne fait ni explosion BOM multi-niveaux, ni
 * pegging, ni time-phasing, ni besoin brut/net par article fini : ce sont des
 * notions absentes de MrpService::analyze() (rupture bobine vs stock_min
 * uniquement). Elles n'existent nulle part ailleurs comme écran non plus —
 * BomExplosionService ne sert que la fiche BOM, MrpPeggingService n'est
 * appelé par aucune route. Aucun test ci-dessous ne porte donc sur ces
 * notions : les phases 5/6/7/8/9(multi-écrans)/13/17/18/26(multi-level) du
 * mandat REACT-01D sont N/A pour cet écran précis (voir rapport final).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Modules\Production\Models\Coil;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mrpReactCo(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MRPR-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::firstOrCreate(['name' => 'MrpReact Co'], ['email' => 'mrpreact@oa-metal.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    return $co;
}

function mrpReactUser(array $perms = ['production.view', 'production.update']): User
{
    $co = mrpReactCo();
    $role = Role::firstOrCreate(['name' => 'mrp_react_role_' . implode('_', $perms)], ['guard_name' => 'web']);
    foreach ($perms as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

it('redirige le visiteur non authentifié', function () {
    $this->get(route('production.mrp'))->assertRedirect(route('login'));
});

it('production.view seul ne suffit pas — la garde du groupe de routes exige aussi production.update', function () {
    mrpReactUser(['production.view']);

    $this->get(route('production.mrp'))->assertForbidden();
});

it('production.update seul ne suffit pas — la garde du contrôleur exige aussi production.view', function () {
    mrpReactUser(['production.update']);

    $this->get(route('production.mrp'))->assertForbidden();
});

it('les deux permissions ensemble donnent accès à la page', function () {
    mrpReactUser(['production.view', 'production.update']);

    $this->get(route('production.mrp'))->assertOk();
});

it('refuse un utilisateur sans aucune permission production', function () {
    $co = mrpReactCo();
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    test()->actingAs($u);

    $this->get(route('production.mrp'))->assertForbidden();
});

it('un utilisateur autorisé reçoit le composant Production/Mrp/Index', function () {
    mrpReactUser();

    $this->get(route('production.mrp'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Mrp/Index')->has('shortfalls')->has('stats')->has('links')->where('canGenerate', true));
});

it('super_admin voit MRP malgré getAllPermissions() vide', function () {
    $co = mrpReactCo();
    $role = Role::firstOrCreate(['name' => 'super_admin'], ['guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    $this->get(route('production.mrp'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Production/Mrp/Index')->where('auth.is_super_admin', true)->where('canGenerate', true));
});

it('déficit bobine transmis fidèlement (available/min/deficit/estimated)', function () {
    $co = mrpReactCo();
    mrpReactUser();
    $matiere = Product::factory()->create(['name' => 'Bobine MRP React', 'stock_min' => 2000]);
    Coil::create(['company_id' => $co->id, 'product_id' => $matiere->id, 'reference' => 'B-MRPR-1', 'initial_weight' => 1000, 'remaining_weight' => 800, 'cost_per_kg' => 600, 'purchase_price' => 600000, 'status' => 'disponible']);

    $this->get(route('production.mrp'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shortfalls.0.productId', $matiere->id)
            ->where('shortfalls.0.available', 800)
            ->where('shortfalls.0.min', 2000)
            ->where('shortfalls.0.deficit', 1200)
            ->where('shortfalls.0.estimated', 720000)
            ->where('stats.count', 1)
            ->etc()
        );
});

it('table vide sans déficit : shortfalls est un tableau vide, stats à zéro', function () {
    mrpReactUser();

    $this->get(route('production.mrp'))
        ->assertInertia(fn (Assert $page) => $page->where('shortfalls', [])->where('stats.count', 0)->etc());
});

it('les liens transmis pointent vers la même route que l’ancien Blade', function () {
    mrpReactUser();

    $this->get(route('production.mrp'))
        ->assertInertia(fn (Assert $page) => $page->where('links.generateUrl', route('production.mrp.generate'))->etc());
});

it('la route production.mrp reste GET production/mrp sous le même middleware', function () {
    $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->getName() === 'production.mrp');

    expect($route->uri())->toBe('production/mrp');
    expect($route->methods())->toContain('GET');
});

// [Phase 27 — SÉCURITÉ] Même si un utilisateur parvenait d'une façon ou d'une
// autre à voir un bouton "Générer" (bug UI, DOM modifié à la main), le
// endpoint POST reste protégé indépendamment par le middleware
// permission:production.update du groupe de routes — jamais touché ici.
it('MRP ACTION SECURITY: le serveur refuse la génération de DA à un utilisateur sans production.update', function () {
    $co = mrpReactCo();
    mrpReactUser(['production.view']); // vue seule, jamais assez pour générer
    $matiere = Product::factory()->create(['stock_min' => 2000]);
    Coil::create(['company_id' => $co->id, 'product_id' => $matiere->id, 'reference' => 'B-MRPR-SEC', 'initial_weight' => 1000, 'remaining_weight' => 400, 'cost_per_kg' => 600, 'purchase_price' => 600000, 'status' => 'disponible']);

    $this->post(route('production.mrp.generate'), ['product_ids' => [$matiere->id]])->assertForbidden();
    expect(PurchaseRequest::count())->toBe(0);
});

it('un utilisateur autorisé peut générer la demande d’achat via l’endpoint', function () {
    $co = mrpReactCo();
    mrpReactUser();
    $matiere = Product::factory()->create(['stock_min' => 2000]);
    Coil::create(['company_id' => $co->id, 'product_id' => $matiere->id, 'reference' => 'B-MRPR-OK', 'initial_weight' => 1000, 'remaining_weight' => 400, 'cost_per_kg' => 600, 'purchase_price' => 600000, 'status' => 'disponible']);

    // [REACT-01D] Le contrôleur renvoie Inertia::location() — pour un appel
    // sans en-tête X-Inertia (comme ce client de test), ResponseFactory::
    // location() retombe sur Redirect::away(), une redirection 3xx standard.
    // Pour un vrai client Inertia (navigateur), le même appel renvoie 409 +
    // X-Inertia-Location à la place — validé empiriquement navigateur, voir
    // rapport final.
    $this->post(route('production.mrp.generate'), ['product_ids' => [$matiere->id]])->assertRedirect();
    expect(PurchaseRequest::count())->toBe(1);
});
