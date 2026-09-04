<?php

/**
 * [FINAL-CLOSURE-GATE §16] Contexte société en environnement asynchrone
 * (queue worker) — AUCUN code produit touché, classe de job factice
 * locale à ce test utilisant le même trait que les 5 jobs réels
 * (Illuminate\Queue\SerializesModels).
 *
 * Preuve visée : un worker de queue n'a JAMAIS de contexte HTTP (donc
 * jamais de `current_company` posé par SetCurrentCompany) — CompanyScope
 * retombe alors sur "la première société créée" (App\Models\Scopes\
 * CompanyScope::apply(), lecture de code). La question posée : un modèle
 * company-scoped appartenant à une société B (PAS la première) survit-il
 * intact au cycle serialize()/unserialize() d'un job réel, ou se
 * retrouve-t-il silencieusement remplacé/introuvable ?
 *
 * Réponse PROUVÉE par lecture de code AVANT ce test :
 * Illuminate\Database\Eloquent\Model::newQueryForRestoration() (vendor,
 * ligne ~1669) appelle explicitement newQueryWithoutScopes() — Laravel
 * CONTOURNE délibérément tous les global scopes (dont CompanyScope) lors
 * de la restauration d'un modèle depuis un payload de job. C'est un
 * comportement du FRAMEWORK, pas une protection ajoutée par ce projet.
 * Ce test vérifie empiriquement que ce mécanisme fonctionne bien tel que
 * documenté, sur un vrai modèle company-scoped du projet (ProductionOrder).
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;

uses(\Tests\Concerns\RefreshDatabase::class);

class QaAsyncJobFixture
{
    use \Illuminate\Queue\SerializesModels;

    public function __construct(public ProductionOrder $order) {}
}

it('QA-ASYNC — un ProductionOrder de la société B (pas la première) survit intact au cycle serialize/unserialize hors contexte HTTP', function () {
    // Aucun app()->instance('current_company', ...) n'est posé nulle part
    // dans ce test — état volontairement identique à un worker de queue.
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-ASYNC-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $companyFirst = Company::create(['name' => 'QA-ASYNC Société PREMIÈRE (fallback)', 'email' => 'qa-async-first@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $companyB = Company::create(['name' => 'QA-ASYNC Société B (cible réelle)', 'email' => 'qa-async-b@iboa.test', 'current_fiscal_year_id' => $fy->id]);

    expect($companyFirst->id)->toBeLessThan($companyB->id); // confirme B n'est PAS la première société.

    $product = Product::factory()->create(['reference' => 'QA-ASYNC-PROD']);
    $orderB = ProductionOrder::create([
        'company_id' => $companyB->id, 'product_id' => $product->id,
        'quantity_requested' => 7_654_321, 'status' => 'brouillon', 'number' => 'OF-QA-ASYNC-B-001',
    ]);

    expect(app()->has('current_company'))->toBeFalse(); // pas de contexte, comme un worker réel.

    // ── Cycle réel serialize()/unserialize() d'un job utilisant SerializesModels ──
    $job = new QaAsyncJobFixture($orderB);
    $payload = serialize($job);
    /** @var QaAsyncJobFixture $restored */
    $restored = unserialize($payload);

    expect(app()->has('current_company'))->toBeFalse(); // toujours aucun contexte au moment de la restauration.
    expect($restored->order)->not->toBeNull()
        ->and($restored->order->id)->toBe($orderB->id)
        ->and((int) $restored->order->company_id)->toBe($companyB->id) // PAS company_id de la première société.
        ->and((float) $restored->order->quantity_requested)->toBe(7654321.0);
});

it('QA-ASYNC — a contrario, une requête FRAÎCHE (pas le modèle sérialisé) sur un modèle scoped retombe bien sur la société "première" sans contexte', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-ASYNC-2026b'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $companyFirst = Company::create(['name' => 'QA-ASYNC-2 Première', 'email' => 'qa-async2-first@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $companyB = Company::create(['name' => 'QA-ASYNC-2 Société B', 'email' => 'qa-async2-b@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $product = Product::factory()->create(['reference' => 'QA-ASYNC2-PROD']);

    ProductionOrder::create(['company_id' => $companyFirst->id, 'product_id' => $product->id, 'quantity_requested' => 1_111_111, 'status' => 'brouillon', 'number' => 'OF-QA-ASYNC2-FIRST']);
    ProductionOrder::create(['company_id' => $companyB->id, 'product_id' => $product->id, 'quantity_requested' => 2_222_222, 'status' => 'brouillon', 'number' => 'OF-QA-ASYNC2-B']);

    expect(app()->has('current_company'))->toBeFalse();

    // Requête FRAÎCHE (pas restauration d'un modèle déjà connu) — c'est ICI
    // que CompanyScope s'applique réellement, avec son repli documenté.
    $visible = ProductionOrder::pluck('quantity_requested')->map(fn ($q) => (float) $q);
    expect($visible)->toContain(1_111_111.0)->not->toContain(2_222_222.0); // repli confirmé = première société, PAS B.
});
