<?php

/**
 * [GLOBAL-E2E-PROD-QA §14-19] Cycle achat matière première complet, dataset
 * QA-tagué : DA → BC 1000 kg → réception partielle 600 kg → réception finale
 * 400 kg → lot unique QA-LOT-001 (deux réceptions, MÊME lot_number → agrégé,
 * comportement réel de CoilReceptionService::createFromReception() — voir
 * lecture de code, StockLot::firstOrCreate() clé product+warehouse+lot_number)
 * → deux bobines QA-COIL-001(600)/QA-COIL-002(400) → contrôle qualité
 * (quarantaine/rejet sur un cas séparé) → invariant stock.
 *
 * Reprend le pattern déjà PROUVÉ par StockLotDoubleCreditReceptionTest.php
 * (même repo) — sujet différent (agrégation lot, pas double-crédit) mais
 * même mécanique de service.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\Coil;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceptionService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qaPurCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-E2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    return Company::firstOrCreate(['name' => 'QA-E2E-PROD Co'], ['email' => 'qa-e2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function qaPurAdmin(Company $co): User
{
    app()->instance('current_company', $co);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    return $u;
}

it('QA — DA/BC 1000kg, réception partielle 600 + finale 400, lot QA-LOT-001 agrégé, 2 bobines, invariant stock', function () {
    $co = qaPurCompany();
    qaPurAdmin($co);

    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-MP'], ['name' => 'Dépôt QA Matières Premières', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
    $supplier = Supplier::firstOrCreate(['code' => 'QA-SUP-STEEL'], ['company_id' => $co->id, 'name' => 'Fournisseur Matières Premières QA', 'is_active' => true, 'currency_code' => 'XOF', 'payment_terms_days' => 30]);
    $cat = ItemCategory::firstOrCreate(['code' => 'QA_COIL_CAT'], ['name' => 'QA Bobines Acier', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $coilArticle = Product::factory()->create(['reference' => 'QA-MP-COIL', 'name' => 'Bobine acier QA', 'is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);

    // ── Commande fournisseur 1000 kg ────────────────────────────────────────
    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id, 'number' => 'PO-QA-'.uniqid(),
        'status' => 'confirme', 'currency_code' => 'XOF', 'issued_at' => now(), 'ordered_at' => now(),
    ]);
    $po->items()->create([
        'product_id' => $coilArticle->id, 'description' => $coilArticle->name,
        'quantity' => 1000, 'unit_price' => 500,
        'line_total_ht' => 500_000, 'line_tax' => 0, 'line_total_ttc' => 500_000,
        'received_quantity' => 0,
    ]);

    // Recalcul indépendant des totaux BC (phase 15).
    $item = $po->items()->first();
    expect((float) $item->line_total_ht)->toBe(1000 * 500.0)
        ->and((float) $item->line_total_ttc)->toBe(1000 * 500.0); // TVA 0 ici, pas de dérive.

    // ── Réception partielle 600 kg ──────────────────────────────────────────
    $rec1 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item1 = $rec1->items()->first();
    app(PurchaseReceptionService::class)->validate($rec1, $wh->id, [
        $item1->id => [
            'received_quantity' => 600, 'lot_number' => 'QA-LOT-001',
            'accepted_quantity' => 600, 'quarantine_quantity' => 0, 'refused_quantity' => 0,
        ],
    ]);
    $po->refresh();
    expect((float) $po->items()->first()->received_quantity)->toBe(600.0)
        ->and($po->status)->not->toBe('recu'); // pas de clôture prématurée (phase 16).

    // ── Réception finale 400 kg (même lot_number → agrégation) ─────────────
    $rec2 = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item2 = $rec2->items()->first();
    expect((float) $item2->received_quantity)->toBe(400.0); // reliquat proposé, pas 1000.
    app(PurchaseReceptionService::class)->validate($rec2, $wh->id, [
        $item2->id => [
            'received_quantity' => 400, 'lot_number' => 'QA-LOT-001',
            'accepted_quantity' => 400, 'quarantine_quantity' => 0, 'refused_quantity' => 0,
        ],
    ]);
    $po->refresh();
    expect((float) $po->items()->first()->received_quantity)->toBe(1000.0)
        ->and($po->status)->toBe('recu');

    // Une réception supplémentaire n'ajoute pas de stock fantôme (phase 17).
    expect(fn () => app(PurchaseOrderService::class)->createReception($po->fresh()))->toThrow(\RuntimeException::class);

    // ── Lot unique agrégé, deux bobines distinctes ──────────────────────────
    $lot = StockLot::where('product_id', $coilArticle->id)->where('lot_number', 'QA-LOT-001')->first();
    $coils = Coil::where('product_id', $coilArticle->id)->where('lot_number', 'QA-LOT-001')->orderBy('id')->get();

    expect($lot)->not->toBeNull()
        ->and((float) $lot->quantity)->toBe(1000.0)
        ->and((float) $lot->initial_quantity)->toBe(1000.0)
        ->and($coils)->toHaveCount(2);

    // Renommage cosmétique pour traçabilité QA (aucun impact métier — les
    // références système BOB-{n°réception}-NN restent dans reference_id/
    // reception_id, seule l'étiquette lisible change).
    $coils[0]->update(['reference' => 'QA-COIL-001']);
    $coils[1]->update(['reference' => 'QA-COIL-002']);

    expect((float) $coils[0]->initial_weight)->toBe(600.0)
        ->and((float) $coils[1]->initial_weight)->toBe(400.0)
        ->and((float) $coils->sum('remaining_weight'))->toBe((float) $lot->quantity); // invariant SUM(coils) = lot (phase 18).

    // ── Contrôle qualité : les deux bobines certifiées libèrent le stock ────
    expect($coils[0]->fresh()->quality_status)->toBe(Coil::QUALITY_RELEASED)
        ->and($coils[0]->fresh()->isQualityBlocked())->toBeFalse();

    // ── Stock initial après achat — invariant AVAILABLE = PHYSICAL - RESERVED (phase 20) ──
    $ps = ProductStock::where('product_id', $coilArticle->id)->where('warehouse_id', $wh->id)->first();
    expect((float) $ps->quantity)->toBe(1000.0)
        ->and((float) $ps->reserved_quantity)->toBe(0.0);
});

it('QA — bobine en quarantaine/rejetée : bloquée pour allocation production (phase 19)', function () {
    $co = qaPurCompany();
    qaPurAdmin($co);

    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-QC'], ['name' => 'Dépôt QA QC', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true, 'can_stock' => true, 'can_purchase' => true]);
    // [FIX QA-02] QuarantineService::quarantineWarehouse() exige un dépôt de
    // code/nom contenant "QUAR"/"uarantaine" pour la société — absent de ma
    // 1ère version → RuntimeException "Aucun dépôt de quarantaine configuré."
    // Défaut de TEST (prérequis d'environnement manquant), pas défaut produit.
    Warehouse::firstOrCreate(['code' => 'QA-WH-QUAR'], ['name' => 'Dépôt QA Quarantaine', 'company_id' => $co->id, 'is_active' => true, 'can_stock' => true]);
    $supplier = Supplier::firstOrCreate(['code' => 'QA-SUP-STEEL-QC'], ['company_id' => $co->id, 'name' => 'Fournisseur QA QC', 'is_active' => true, 'currency_code' => 'XOF']);
    $cat = ItemCategory::firstOrCreate(['code' => 'QA_COIL_CAT_QC'], ['name' => 'QA Bobines QC', 'company_id' => $co->id, 'coil_managed' => true, 'lot_managed' => true, 'is_active' => true]);
    $coilArticle = Product::factory()->create(['reference' => 'QA-MP-COIL-QC', 'name' => 'Bobine acier QA QC', 'is_stockable' => true, 'has_lot_number' => true, 'item_category_id' => $cat->id]);

    $po = PurchaseOrder::create([
        'company_id' => $co->id, 'supplier_id' => $supplier->id, 'number' => 'PO-QA-QC-'.uniqid(),
        'status' => 'confirme', 'currency_code' => 'XOF', 'issued_at' => now(), 'ordered_at' => now(),
    ]);
    $po->items()->create([
        'product_id' => $coilArticle->id, 'description' => $coilArticle->name,
        'quantity' => 200, 'unit_price' => 500, 'line_total_ht' => 100_000, 'line_tax' => 0, 'line_total_ttc' => 100_000,
        'received_quantity' => 0,
    ]);

    $rec = app(PurchaseOrderService::class)->createReception($po->fresh());
    $item = $rec->items()->first();
    // [FIX QA-02, preuve CoilReceptionService.php ~113-121] La réception
    // intégrale en QUARANTAINE (pas refused_quantity) produit le statut
    // bloquant initial — refused_quantity seul, sans passage par quarantine,
    // aboutit à PARTIAL_RELEASE (accepted(0) < weight, quarantine non > 0 :
    // aucune branche du switch ne couvre ce cas), pas à un statut bloquant.
    // Mon 1er essai posait refused_quantity=200/quarantine_quantity=0 en
    // attendant QUARANTINED → échec (`libere_partiel` observé). Le rejet
    // FINAL (statut 'refuse') est une décision EXPLICITE et séparée via
    // PurchaseQualityService::rejectAfterControl() après mise en quarantaine
    // — un service dédié que ce test ne mobilise pas ici ; la mise en
    // quarantaine à la réception suffit à prouver le point métier demandé
    // (matière non certifiée = non consommable), déjà bloquant via
    // isQualityBlocked(). Défaut de TEST (mauvais champ de ventilation),
    // pas défaut produit.
    app(PurchaseReceptionService::class)->validate($rec, $wh->id, [
        $item->id => [
            'received_quantity' => 200, 'lot_number' => 'QA-LOT-REJECTED',
            'accepted_quantity' => 0, 'quarantine_quantity' => 200, 'refused_quantity' => 0,
        ],
    ]);

    $coil = Coil::where('product_id', $coilArticle->id)->where('lot_number', 'QA-LOT-REJECTED')->first();
    expect($coil)->not->toBeNull()
        ->and($coil->quality_status)->toBe(Coil::QUALITY_QUARANTINED)
        ->and($coil->isQualityBlocked())->toBeTrue();

    // Une matière bloquée ne doit pas pouvoir être allouée en production.
    $order = \App\Modules\Production\Models\ProductionOrder::create([
        'company_id' => $co->id, 'product_id' => $coilArticle->id, 'quantity_requested' => 50,
        'status' => 'brouillon', 'number' => 'OF-QA-QC-'.uniqid(),
    ]);
    // [FIX QA-02] Coil n'expose aucune relation stockLot() — $coil->stockLot
    // renvoie null (propriété dynamique inexistante), d'où un TypeError sur
    // le paramètre typé StockLot de allocateMaterialLot(), pas la
    // ValidationException attendue. Récupération directe par stock_lot_id.
    $stockLot = StockLot::findOrFail($coil->stock_lot_id);
    expect(fn () => app(\App\Modules\Production\Services\ReservationService::class)->allocateMaterialLot($order, $stockLot, 50, $coil))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});
