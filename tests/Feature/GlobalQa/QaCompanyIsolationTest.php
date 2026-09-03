<?php

/**
 * [GLOBAL-E2E-PROD-QA §75] Isolation société — technique valeurs distinctives,
 * même méthode que SalesKpiCompanyIsolationTest.php (déjà 11 axes vente/
 * facturation/crédit, cité comme preuve dans le rapport final, non reproduit
 * ici). Ce fichier étend la preuve à STOCK, PRODUCTION et COMPTABILITÉ —
 * trois familles non couvertes par le test KPI existant.
 *
 * FINDING #1 (mémoire projet, audit sécurité antérieur) : la décision produit
 * pour le pilote OA Metal est ERP MONO-SOCIÉTÉ (une seule Company réelle en
 * production). Le modèle de données supporte plusieurs Company (utilisé ici
 * comme fixture de test), mais l'isolation multi-société est une propriété
 * de défense en profondeur pour ce pilote, PAS une exigence d'exploitation
 * multi-tenant réelle — à ne pas présenter comme telle dans le rapport final.
 *
 * FINDING #2 (découvert en écrivant ce test, preuve par lecture de code) :
 * Warehouse, ProductionOrder et JournalEntry portent tous trois le trait
 * HasCompanyScope → App\Models\Scopes\CompanyScope, un GLOBAL SCOPE Eloquent.
 * Sans contexte `current_company` explicitement posé (`app()->instance(...)`,
 * normalement fait par le middleware SetCurrentCompany), CompanyScope
 * restreint TOUTE requête sur ces modèles à `company_id = (SELECT id FROM
 * companies ORDER BY id LIMIT 1)` — la toute première société créée en base,
 * quel que soit le ->where('company_id', ...) explicitement ajouté par
 * ailleurs. Une première version de ce test ne posait aucun contexte : ses
 * assertions "A ne voit pas B" passaient alors TRIVIALEMENT (B était déjà
 * invisible pour toute requête Eloquent, contexte ou pas), sans exercer la
 * vraie mécanique d'isolation. Ce fichier bascule maintenant explicitement
 * le contexte société entre A et B pour chaque moitié de la preuve — seule
 * façon de vérifier que le SCOPE LUI-MÊME isole correctement, plutôt que de
 * profiter d'un repli qui aurait masqué une vraie fuite tout autant qu'une
 * absence de fuite.
 */

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\JournalType;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Modules\Production\Models\ProductionOrder;

uses(\Tests\Concerns\RefreshDatabase::class);

it('QA — stock, OF et écritures comptables de la société B ne fuient jamais vers la société A (contexte société explicite)', function () {
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-ISO-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $a = Company::create(['name' => 'QA-ISO Société A', 'email' => 'qa-iso-a@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $b = Company::create(['name' => 'QA-ISO Société B', 'email' => 'qa-iso-b@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $product = Product::factory()->create(['reference' => 'QA-ISO-PROD']);

    // ── Création des données SOCIÉTÉ A, sous contexte A ──────────────────────
    app()->instance('current_company', $a);
    $whA = Warehouse::create(['code' => 'QA-ISO-WH-A', 'name' => 'Dépôt A', 'company_id' => $a->id, 'is_active' => true, 'is_default' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $whA->id, 'quantity' => 111, 'reserved_quantity' => 0]);
    ProductionOrder::create(['company_id' => $a->id, 'product_id' => $product->id, 'quantity_requested' => 1, 'status' => 'brouillon', 'number' => 'OF-ISO-A-001']);
    $jtA = JournalType::firstOrCreate(['code' => 'QA-ISO-JT', 'company_id' => $a->id], ['name' => 'QA Isolation A']);
    $acA = AccountClass::firstOrCreate(['company_id' => $a->id, 'number' => 5], ['name' => 'QA Classe A']);
    $accA = Account::firstOrCreate(['company_id' => $a->id, 'code' => '571000'], ['account_class_id' => $acA->id, 'name' => 'QA Compte A', 'type' => 'bilan', 'is_detail' => true, 'is_active' => true]);
    $entryA = JournalEntry::create(['company_id' => $a->id, 'fiscal_year_id' => $fy->id, 'journal_type_id' => $jtA->id, 'number' => 'JE-ISO-A-001', 'entry_date' => now(), 'description' => 'QA isolation test entry A', 'status' => 'valide', 'created_by' => null]);
    JournalEntryLine::create(['journal_entry_id' => $entryA->id, 'account_id' => $accA->id, 'debit' => 222, 'credit' => 0, 'sort_order' => 1, 'label' => 'QA debit A']);
    JournalEntryLine::create(['journal_entry_id' => $entryA->id, 'account_id' => $accA->id, 'debit' => 0, 'credit' => 222, 'sort_order' => 2, 'label' => 'QA credit A']);

    // ── Création des données SOCIÉTÉ B, sous contexte B (valeurs distinctives 7 chiffres) ──
    app()->instance('current_company', $b);
    $whB = Warehouse::create(['code' => 'QA-ISO-WH-B', 'name' => 'Dépôt B', 'company_id' => $b->id, 'is_active' => true, 'is_default' => true]);
    ProductStock::create(['product_id' => $product->id, 'warehouse_id' => $whB->id, 'quantity' => 8_765_432, 'reserved_quantity' => 0]);
    ProductionOrder::create(['company_id' => $b->id, 'product_id' => $product->id, 'quantity_requested' => 9_876_543, 'status' => 'brouillon', 'number' => 'OF-ISO-B-001']);
    $jtB = JournalType::firstOrCreate(['code' => 'QA-ISO-JT', 'company_id' => $b->id], ['name' => 'QA Isolation B']);
    $acB = AccountClass::firstOrCreate(['company_id' => $b->id, 'number' => 5], ['name' => 'QA Classe B']);
    $accB = Account::firstOrCreate(['company_id' => $b->id, 'code' => '571000'], ['account_class_id' => $acB->id, 'name' => 'QA Compte B', 'type' => 'bilan', 'is_detail' => true, 'is_active' => true]);
    $entryB = JournalEntry::create(['company_id' => $b->id, 'fiscal_year_id' => $fy->id, 'journal_type_id' => $jtB->id, 'number' => 'JE-ISO-B-001', 'entry_date' => now(), 'description' => 'QA isolation test entry B', 'status' => 'valide', 'created_by' => null]);
    JournalEntryLine::create(['journal_entry_id' => $entryB->id, 'account_id' => $accB->id, 'debit' => 6_543_219, 'credit' => 0, 'sort_order' => 1, 'label' => 'QA debit B']);
    JournalEntryLine::create(['journal_entry_id' => $entryB->id, 'account_id' => $accB->id, 'debit' => 0, 'credit' => 6_543_219, 'sort_order' => 2, 'label' => 'QA credit B']);

    // ── Contexte A : le SCOPE (pas seulement mon where) doit exclure B ───────
    app()->instance('current_company', $a);

    $stockA = ProductStock::whereHas('warehouse', fn ($q) => $q->where('company_id', $a->id))->pluck('quantity')->map(fn ($q) => (float) $q);
    expect($stockA)->toContain(111.0)->not->toContain(8_765_432.0);

    // Sans filtre where explicite : le SCOPE seul doit déjà restreindre à A.
    $ofVisibleSousContexteA = ProductionOrder::pluck('quantity_requested')->map(fn ($q) => (float) $q);
    expect($ofVisibleSousContexteA)->toContain(1.0)->not->toContain(9876543.0);

    $entriesVisiblesSousContexteA = JournalEntry::with('lines')->get();
    expect($entriesVisiblesSousContexteA->pluck('number'))->toContain('JE-ISO-A-001')->not->toContain('JE-ISO-B-001');
    $amountsA = $entriesVisiblesSousContexteA->flatMap(fn ($e) => $e->lines->pluck('debit'))->merge($entriesVisiblesSousContexteA->flatMap(fn ($e) => $e->lines->pluck('credit')));
    expect($amountsA)->not->toContain(6543219);

    // ── Contexte B : réciproquement, A doit être invisible, B doit apparaître ──
    app()->instance('current_company', $b);

    $stockB = ProductStock::whereHas('warehouse', fn ($q) => $q->where('company_id', $b->id))->pluck('quantity')->map(fn ($q) => (float) $q);
    expect($stockB)->toContain(8_765_432.0)->not->toContain(111.0);

    $ofVisibleSousContexteB = ProductionOrder::pluck('quantity_requested')->map(fn ($q) => (float) $q);
    expect($ofVisibleSousContexteB)->toContain(9876543.0)->not->toContain(1.0);

    $entriesVisiblesSousContexteB = JournalEntry::with('lines')->get();
    expect($entriesVisiblesSousContexteB->pluck('number'))->toContain('JE-ISO-B-001')->not->toContain('JE-ISO-A-001');
    $amountsB = $entriesVisiblesSousContexteB->flatMap(fn ($e) => $e->lines->pluck('debit'))->merge($entriesVisiblesSousContexteB->flatMap(fn ($e) => $e->lines->pluck('credit')));
    expect($amountsB)->not->toContain(222);
});
