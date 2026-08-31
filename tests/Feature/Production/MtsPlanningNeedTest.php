<?php

/**
 * [MTS §1-§2] Écran de planification « production pour stock ».
 *
 * L'écran affichait quatre articles en « Rupture », un besoin net de 0 partout,
 * et aucun bouton de création d'OF. Trois causes distinctes :
 *
 *   1. `cible = $p->stock_max ?: $p->stock_min ?: 0` — une colonne decimal
 *      remonte la chaîne « 0.00 », VRAIE en PHP. La cible valait donc « 0.00 »,
 *      soit 0 après conversion : besoin nul quoi qu'il arrive.
 *   2. Le même piège rendait « 0 » dans la colonne Min et « — » dans Cible, pour
 *      le même zéro sous-jacent.
 *   3. La formule ignorait les réceptions attendues et la demande client, et ne
 *      lisait jamais `reorder_point` — pourtant en base et exigé par le cahier.
 *
 * Un article sans aucun seuil n'est plus déclaré « en rupture » : il n'est pas
 * piloté. Le distinguer donne la bonne action — compléter la fiche article — au
 * lieu d'accuser un stock qui n'a jamais eu de cible.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

function mtsUser(): User
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTS-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $co = Company::firstOrCreate(['name' => 'MTS Co'], [
        'email' => 'mts@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    Warehouse::firstOrCreate(['code' => 'WMTS'], [
        'name' => 'WMTS', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true,
    ]);
    app()->instance('current_company', $co);

    $role = Role::firstOrCreate(['name' => 'mts_planificateur', 'guard_name' => 'web']);
    foreach (['production.view', 'production.create'] as $p) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
    }
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

/** Article MTS, avec le stock physique voulu. */
function mtsProduct(array $seuils = [], float $stock = 0): Product
{
    $p = Product::factory()->create(array_merge([
        'production_mode' => 'mts', 'is_stockable' => true, 'is_active' => true,
    ], $seuils));

    ProductStock::create([
        'product_id' => $p->id,
        'warehouse_id' => Warehouse::where('code', 'WMTS')->value('id'),
        'quantity' => $stock, 'reserved_quantity' => 0, 'avg_cost' => 500,
    ]);

    return $p;
}

/** Commande fournisseur engagée : $recu déjà réceptionné sur $commande. */
function mtsPurchaseOrder(Product $p, float $commande, float $recu = 0, string $status = 'confirme'): void
{
    $co = Company::first();
    $poId = DB::table('purchase_orders')->insertGetId([
        'company_id' => $co->id,
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'CF-MTS-'.uniqid(), 'status' => $status,
        'ordered_at' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('purchase_order_items')->insert([
        'purchase_order_id' => $poId, 'product_id' => $p->id,
        'description' => $p->name, 'quantity' => $commande, 'received_quantity' => $recu,
        'unit_price' => 1000, 'line_total_ht' => 1000 * $commande, 'line_tax' => 0,
        'line_total_ttc' => 1000 * $commande,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Commande client ferme : $livre déjà livré sur $commande. */
function mtsSalesOrder(Product $p, float $commande, float $livre = 0, string $status = 'confirme'): void
{
    $co = Company::first();
    $order = Order::create([
        'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => Client::factory()->create()->id,
        'number' => 'CMD-MTS-'.uniqid(), 'status' => $status, 'issued_at' => now(),
    ]);
    $order->items()->create([
        'product_id' => $p->id, 'description' => $p->name,
        'quantity' => $commande, 'delivered_quantity' => $livre,
        'unit_price' => 2000, 'line_total_ht' => 2000 * $commande, 'line_tax' => 0,
        'line_total_ttc' => 2000 * $commande,
    ]);
}

/**
 * [REACT-01D] La page est désormais Inertia — plus de vue Blade dont on peut
 * lire ->original->getData(). On demande le payload JSON brut (en-tête
 * X-Inertia, comme le fait un vrai client Inertia) et on y cherche la ligne.
 * Les clés (seuil/cible/besoin/etat/dispo/client/recu/physique/reserve/plan)
 * sont transmises telles quelles par ProductionOrderController::mts() —
 * seule la clé d'identification change (productId au lieu de p->id).
 *
 * @return array<string,mixed> la ligne calculée pour cet article
 */
function mtsRow(Product $p): array
{
    $rows = test()->get(route('production.orders.mts'))
        ->assertOk()
        ->inertiaProps('rows');

    return collect($rows)->firstWhere(fn ($r) => $r['productId'] === $p->id);
}

// ── 1. Article non paramétré ─────────────────────────────────────────────────

it('ne déclare pas « en rupture » un article sans aucun seuil', function () {
    mtsUser();
    $p = mtsProduct(['stock_min' => 0, 'stock_max' => null, 'stock_securite' => 0, 'reorder_point' => 0]);

    expect(mtsRow($p)['etat'])->toBe('non_parametre');
});

it('affiche « Seuil non défini » et renvoie vers la fiche article', function () {
    // [REACT-01D] Le composant React affiche "Seuil non défini" uniquement
    // quand etat === 'non_parametre' (voir Mts/Index.jsx) ; on vérifie la
    // donnée transmise (etat + editUrl), pas un texte rendu côté client (pas
    // de SSR).
    mtsUser();
    $p = mtsProduct(['stock_min' => 0, 'stock_max' => null, 'stock_securite' => 0, 'reorder_point' => 0]);
    $row = mtsRow($p);

    expect($row['etat'])->toBe('non_parametre')
        ->and($row['editUrl'])->toBe(route('products.edit', $p));
});

it('affiche « — » et non « 0 » pour un seuil et une cible absents', function () {
    // Le piège d'origine : « 0.00 » est une chaîne VRAIE en PHP. La colonne Min
    // rendait donc « 0 » quand la colonne Cible rendait « — », pour le même zéro.
    // [REACT-01D] 'parametre' n'est pas transmis au frontend (jamais rendu par
    // le Blade non plus) — 'etat' === 'non_parametre' porte la même preuve.
    mtsUser();
    $p = mtsProduct(['stock_min' => 0, 'stock_max' => null, 'stock_securite' => 0, 'reorder_point' => 0]);
    $row = mtsRow($p);

    expect($row['seuil'])->toEqual(0.0)
        ->and($row['cible'])->toEqual(0.0)
        ->and($row['etat'])->toBe('non_parametre');
});

// ── 2. Résolution des seuils ─────────────────────────────────────────────────

it('retient le stock maximum comme cible quand il est défini', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'reorder_point' => 200, 'stock_min' => 100]);

    expect(mtsRow($p)['cible'])->toEqual(500.0);
});

it('fait du point de commande le seuil de déclenchement, devant le minimum', function () {
    // `reorder_point` existait en base et n'était jamais lu : l'état se calculait
    // sur `stock_min`, ignorant le seuil que le cahier désigne comme déclencheur.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'reorder_point' => 200, 'stock_min' => 100], stock: 150);
    $row = mtsRow($p);

    expect($row['seuil'])->toEqual(200.0)
        ->and($row['etat'])->toBe('sous_min'); // 150 < 200
});

it('se rabat sur le point de commande puis le minimum à défaut de maximum', function () {
    mtsUser();
    $surReorder = mtsProduct(['stock_max' => null, 'reorder_point' => 300, 'stock_min' => 100]);
    $surMin     = mtsProduct(['stock_max' => null, 'reorder_point' => 0, 'stock_min' => 120]);

    expect(mtsRow($surReorder)['cible'])->toEqual(300.0)
        ->and(mtsRow($surMin)['cible'])->toEqual(120.0);
});

// ── 3. Formule du besoin net ─────────────────────────────────────────────────

it('déduit les réceptions fournisseurs attendues du besoin', function () {
    // Cible 500, stock 0 → besoin brut 500. Une commande fournisseur de 200 en
    // cours le ramène à 300 : on ne fabrique pas ce qu'on a déjà acheté.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200);

    $row = mtsRow($p);
    expect($row['recu'])->toEqual(200.0)
        ->and($row['besoin'])->toEqual(300.0);
});

it('ne déduit pas une commande fournisseur déjà entièrement reçue', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200, recu: 200);

    expect(mtsRow($p)['recu'])->toEqual(0.0);
});

it('ne déduit pas une commande fournisseur annulée ou encore en brouillon', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200, status: 'annule');
    mtsPurchaseOrder($p, commande: 150, status: 'brouillon');

    expect(mtsRow($p)['recu'])->toEqual(0.0);
});

it('compte une commande fournisseur « partiellement_recu » au masculin', function () {
    // L'énumération réelle est au MASCULIN (`envoye`, `confirme`,
    // `partiellement_recu`). Écrits au féminin, ces statuts ne correspondent à
    // aucune ligne et le terme vaudrait silencieusement zéro — c'est le défaut
    // constaté dans PurchaseInsightsService.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsPurchaseOrder($p, commande: 200, recu: 50, status: 'partiellement_recu');

    expect(mtsRow($p)['recu'])->toEqual(150.0);
});

it('ajoute la demande client ferme non encore livrée', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsSalesOrder($p, commande: 80);

    $row = mtsRow($p);
    expect($row['client'])->toEqual(80.0)
        ->and($row['besoin'])->toEqual(580.0);
});

it('ne compte pas la part déjà livrée d’une commande client', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    mtsSalesOrder($p, commande: 80, livre: 30);

    expect(mtsRow($p)['client'])->toEqual(50.0);
});

it('ajoute le stock de sécurité à la cible', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_securite' => 50, 'stock_min' => 0, 'reorder_point' => 0]);

    expect(mtsRow($p)['besoin'])->toEqual(550.0);
});

it('combine tous les termes de la formule', function () {
    // 400 cible + 50 sécurité + 100 demande − 120 dispo − 30 attendu = 400
    mtsUser();
    $p = mtsProduct(['stock_max' => 400, 'stock_securite' => 50, 'stock_min' => 0, 'reorder_point' => 0], stock: 120);
    mtsSalesOrder($p, commande: 100);
    mtsPurchaseOrder($p, commande: 30);

    expect(mtsRow($p)['besoin'])->toEqual(400.0);
});

it('ne descend jamais sous zéro quand l’approvisionnement couvre la cible', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 100, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0], stock: 500);

    expect(mtsRow($p)['besoin'])->toEqual(0.0);
});

// ── 4. Action proposée ───────────────────────────────────────────────────────

it('propose la création d’un OF dès qu’un besoin est calculé', function () {
    // [REACT-01D] canCreateOf est calculé côté serveur (permission.create ET
    // besoin > 0, jamais recalculé côté React — voir ProductionOrderController
    // ::mts()) ; le bouton "Créer OF MTS" du composant ne fait que lire ce
    // booléen. On vérifie donc canCreateOf + createOfUrl, pas un texte rendu.
    mtsUser();
    $p = mtsProduct(['stock_max' => 500, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0]);
    $row = mtsRow($p);

    expect($row['besoin'])->toEqual(500.0)
        ->and($row['canCreateOf'])->toBeTrue()
        ->and($row['createOfUrl'])->toContain('product_id='.$p->id)
        ->and($row['createOfUrl'])->toContain('qty=500');
});

it('ne propose rien quand le stock couvre déjà la cible', function () {
    mtsUser();
    $p = mtsProduct(['stock_max' => 100, 'stock_min' => 0, 'stock_securite' => 0, 'reorder_point' => 0], stock: 500);

    expect(mtsRow($p)['canCreateOf'])->toBeFalse();
});

// [Clôture PROD-01 — section 9] Cohérence SERVICE vs ÉCRAN : valeur attendue
// calculée à la main à partir de données connues (jamais en rappelant la
// formule du service), puis vérifiée séparément sur NetRequirementService ET
// sur le rendu HTML de l'écran MTS — les deux doivent donner exactement le
// même nombre, preuve qu'aucune divergence n'existe entre le moteur et
// l'affichage.
it('cohérence service/écran : besoin net calculé à la main = NetRequirementService = écran MTS', function () {
    mtsUser();
    // cible=1000, sécurité=50, physique=200 (dispo=200), demande ferme=150,
    // OF planifiés=0, réception attendue=80.
    // Besoin net attendu (calcul manuel, indépendant du code) :
    // 1000 + 50 + 150 − 200 − 0 − 80 = 920.
    $p = mtsProduct(['stock_max' => 1000, 'stock_min' => 0, 'stock_securite' => 50, 'reorder_point' => 0], stock: 200);
    mtsSalesOrder($p, commande: 150, livre: 0);
    mtsPurchaseOrder($p, commande: 80, recu: 0);
    $besoinAttendu = 920.0;

    $rowService = app(\App\Modules\Production\Services\NetRequirementService::class)
        ->forMtsProducts()->firstWhere(fn ($r) => $r['p']->id === $p->id);
    expect($rowService['besoin'])->toBe($besoinAttendu)
        ->and($rowService['cible'])->toEqual(1000.0)
        ->and($rowService['secu'])->toEqual(50.0)
        ->and($rowService['dispo'])->toEqual(200.0)
        ->and($rowService['client'])->toEqual(150.0)
        ->and($rowService['recu'])->toEqual(80.0);

    // [REACT-01D] Le contrôleur transmet la valeur numérique brute (920.0),
    // le formatage "920" est fait côté React (fmt()) à partir de cette même
    // valeur — la preuve de non-divergence se fait sur le nombre transmis.
    expect(mtsRow($p)['besoin'])->toEqual($besoinAttendu); // écran (contrôleur) = service
});
