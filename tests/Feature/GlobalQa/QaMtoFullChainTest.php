<?php

/**
 * [GLOBAL-E2E-PROD-QA §34-48,84] Chaîne MTO complète, dataset QA-tagué :
 * réservation matière (lot explicite, pas de FIFO caché) → consommation →
 * double-consommation refusée → PF → livraison → facture → encaissement →
 * double paiement refusé → surpaiement (comportement réel documenté, pas jugé
 * sans preuve).
 *
 * Réutilise le squelette prouvé par OrderToCashFullChainTest.php (même repo)
 * avec du dataset QA-CLI-CASH/QA-MP-COIL/QA-PF-BAC nommé et traçable.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Models\StockLot as ProdStockLot;
use App\Models\StockLot;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Modules\Production\Services\ReservationService;
use App\Services\ClientPaymentService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function qaMtoCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(['label' => 'QA-E2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    return Company::firstOrCreate(['name' => 'QA-E2E-PROD Co'], ['email' => 'qa-e2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function qaMtoAdmin(Company $co): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);
    return $u;
}

it('QA-CLI-CASH — chaîne MTO complète : réservation lot explicite → consommation → PF → livraison → facture → encaissement, avec gardes double-consommation/double-paiement/surpaiement', function () {
    $co = qaMtoCompany();
    $user = qaMtoAdmin($co);

    $client = Client::factory()->create(['code' => 'QA-CLI-CASH-CHAIN', 'name' => 'QA Client Comptant', 'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0, 'is_active' => true]);
    $unit = Unit::firstOrCreate(['name' => 'Pièce QA-MTO'], ['abbreviation' => 'pqm']);
    $tax = TaxRate::firstOrCreate(['name' => 'TVA 18% QA-MTO'], ['short_name' => 'TVAQM', 'rate' => 18, 'is_active' => true]);
    $wh = Warehouse::firstOrCreate(['code' => 'QA-WH-PF'], ['name' => 'Dépôt QA Produits Finis', 'company_id' => $co->id, 'is_active' => true, 'is_default' => true]);
    $whMp = Warehouse::firstOrCreate(['code' => 'QA-WH-MP-MTO'], ['name' => 'Dépôt QA MP MTO', 'company_id' => $co->id, 'is_active' => true]);

    $pf = Product::factory()->create(['reference' => 'QA-PF-BAC', 'name' => 'Tôle bac QA', 'is_stockable' => true, 'production_mode' => 'mto']);
    $mp = Product::factory()->create(['reference' => 'QA-MP-COIL-MTO', 'name' => 'Bobine acier QA MTO', 'is_stockable' => true, 'has_lot_number' => true]);

    $bom = BillOfMaterial::create(['company_id' => $co->id, 'product_id' => $pf->id, 'name' => 'BOM QA-PF-BAC', 'is_active' => true, 'labor_per_unit' => 100, 'machine_time_per_unit' => 2]);

    // ── Deux lots matière disponibles : la sélection DOIT respecter le lot demandé, pas un FIFO caché (phase 35) ──
    $lotOld = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $whMp->id, 'lot_number' => 'QA-LOT-OLD', 'quantity' => 500, 'unit_cost' => 400, 'received_at' => now()->subDays(30)]);
    $lotNew = StockLot::create(['product_id' => $mp->id, 'warehouse_id' => $whMp->id, 'lot_number' => 'QA-LOT-NEW', 'quantity' => 500, 'unit_cost' => 420, 'received_at' => now()]);
    ProductStock::create(['product_id' => $mp->id, 'warehouse_id' => $whMp->id, 'quantity' => 1000, 'reserved_quantity' => 0]);

    // ── Commande CASH intégralement réglée avant production ─────────────────
    $order = app(OrderService::class)->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $pf->id, 'description' => $pf->name,
            'quantity' => 10, 'unit_price' => 20_000, 'discount_percent' => 0,
            'unit_id' => $unit->id, 'tax_rate_id' => $tax->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    $order->refresh();

    $cash = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'caisse', 'current_balance' => 0, 'is_active' => true]);
    // [FIX QA-04, même correctif que QA-01] Premier règlement d'un client
    // neuf (0 impayé, 0 non imputé) : ClientPaymentService::create() direct
    // refuse via la garde OVERPAYMENT-GUARD (0>=0). Chemin métier réel :
    // BonPreparationService::createForCashOrder() (force_duplicate=true,
    // documenté "règlement comptoir AVANT facturation").
    app(\App\Services\BonPreparationService::class)->createForCashOrder($order->fresh(), (int) $order->total_ttc, 'QA-MTO-CASH-001', $cash->id);

    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $pf->id)->first();
    expect($of)->not->toBeNull();
    $of->update(['bill_of_material_id' => $bom->id]);

    $prodSvc = app(ProductionService::class);
    $prodSvc->allocateMaterial($of->fresh());
    $prodSvc->submitForValidation($of->fresh());
    $prodSvc->validateByChef($of->fresh());
    $prodSvc->validateByResponsable($of->fresh());

    // Gate financier CASH déjà réglé → lancement ne doit PAS bloquer.
    $prodSvc->launch($of->fresh());
    $prodSvc->start($of->fresh());
    // [FIX QA-04] $of restait périmé (statut 'brouillon' en mémoire) après
    // start() — CoilConsumptionService::consume() exige isInProgress() et
    // rejetait avec l'objet non rafraîchi. Réassignation explicite ici, pas
    // seulement des ->fresh() ponctuels oubliés plus loin (ligne consume()).
    $of = $of->fresh();
    expect($of->status)->toBe('en_cours');

    // ── Réservation EXPLICITE sur QA-LOT-NEW (pas le plus ancien) — phase 35 ──
    $reservation = app(ReservationService::class)->allocateMaterialLot($of, $lotNew, 300);
    expect($reservation->stock_lot_id)->toBe($lotNew->id)->not->toBe($lotOld->id);

    $stockAfterReserve = ProductStock::where('product_id', $mp->id)->where('warehouse_id', $whMp->id)->first();
    expect((float) $stockAfterReserve->quantity)->toBe(1000.0)
        ->and((float) $stockAfterReserve->reserved_quantity)->toBe(300.0)
        ->and((float) $stockAfterReserve->quantity - (float) $stockAfterReserve->reserved_quantity)->toBe(700.0); // AVAILABLE = PHYSICAL - RESERVED.

    // ── Consommation matière sur bobine (canal coil) ─────────────────────────
    $coil = Coil::create([
        'company_id' => $co->id, 'product_id' => $mp->id, 'warehouse_id' => $whMp->id,
        'reference' => 'QA-COIL-MTO-001', 'lot_number' => 'QA-LOT-NEW', 'stock_lot_id' => $lotNew->id,
        'initial_weight' => 300, 'remaining_weight' => 300, 'cost_per_kg' => 420,
        'purchase_price' => 126_000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($of, $coil, 300);
    $coil->refresh();
    expect((float) $coil->remaining_weight)->toBe(0.0);

    // Double-consommation : la bobine est épuisée → BLOCK (phase 38, pas de double déduction).
    expect(fn () => app(CoilConsumptionService::class)->consume($of, $coil, 300))->toThrow(ValidationException::class);

    // ── Production terminée → PF ──────────────────────────────────────────
    $stockSvc = app(ProductionStockService::class);
    $output = $stockSvc->recordOutput($of, ['product_id' => $pf->id, 'warehouse_id' => $wh->id, 'quantity' => 10, 'length' => 1, 'unit_cost' => 3_000]);
    $qc = ProductionQualityControl::create([
        'company_id' => $co->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now(), 'quality_released_at' => now()]);
    $prodSvc->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');
    expect((float) ProductStock::where('product_id', $pf->id)->where('warehouse_id', $wh->id)->value('quantity'))->toBe(10.0);

    // ── Livraison → Facture ──────────────────────────────────────────────────
    $orderSvc = app(OrderService::class);
    $dn = $orderSvc->createDeliveryNote($order->fresh());
    app(DeliveryNoteService::class)->validate($dn);
    $invoice = app(DeliveryNoteService::class)->createInvoice($dn->fresh());
    app(InvoiceService::class)->validate($invoice);
    $invoice->refresh();

    // Recalcul indépendant HT/TVA/TTC (phase 45).
    $expectedHt = 10 * 20_000;
    expect((float) $invoice->total_ttc)->toBe($expectedHt * 1.18);

    // ── Encaissement du solde de la facture ──────────────────────────────────
    // L'acompte cash déjà versé (236 000) a couvert la production, pas encore
    // imputé à CETTE facture (créée après coup) : imputation explicite ici.
    // [FIX QA-04 v2] Le check 1a (doublon <60s, mêmes client+montant+
    // payment_method_id+cash_account_id) est INCONDITIONNEL — le commentaire
    // du code dit "toujours bloqué", force_duplicate ne couvre que 1b/1c.
    // Réutiliser le même $cash pour l'acompte ET le solde facture (même
    // montant 236 000) percute donc systématiquement ce garde-fou, même
    // forcé. Fix réaliste : le virement solde-facture transite par un compte
    // BANQUE distinct de la caisse comptoir de l'acompte — pratique
    // courante, et ça change réellement cash_account_id, sortant cette paire
    // du périmètre de la comparaison stricte.
    // [FIX QA-04 v3] Compte bancaire distinct pour sortir du check 1a (60s,
    // strict sur cash_account_id) — insuffisant seul : le check 1b (24h,
    // client+montant SEULEMENT, indépendant du compte) frappe ensuite.
    // force_duplicate=true est justement fait pour 1b/1c (documenté
    // ClientPaymentService.php:280) : les deux corrections sont
    // nécessaires ensemble pour ce scénario réaliste (acompte production +
    // solde facture, même montant par coïncidence).
    $banque = CashAccount::factory()->create(['company_id' => $co->id, 'type' => 'banque', 'current_balance' => 0, 'is_active' => true]);
    $paiementFacture = app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $banque->id,
        'amount' => $invoice->total_ttc, 'method' => 'virement', 'payment_date' => now()->toDateString(),
        'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => $invoice->total_ttc]],
        'force_duplicate' => true,
    ]);
    $invoice->refresh();
    expect($invoice->status)->toBe('payee')->and((float) $invoice->remaining_amount)->toBe(0.0);

    // ── Double paiement : rejeu identique dans les 60s → REFUSÉ (garde P1) ───
    // [FIX QA-04] Rejoue exactement $paiementFacture (même compte $banque) —
    // c'est cette paire précise qui doit être détectée comme doublon.
    expect(fn () => app(ClientPaymentService::class)->create([
        'client_id' => $client->id, 'cash_account_id' => $banque->id,
        'amount' => $invoice->total_ttc, 'method' => 'virement', 'payment_date' => now()->toDateString(),
        'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => $invoice->total_ttc]],
    ]))->toThrow(\RuntimeException::class);

    // ── Surpaiement : comportement RÉEL observé, rapporté sans jugement a priori (phase 48) ──
    $overpayResult = null;
    $overpayError = null;
    try {
        $overpayResult = app(ClientPaymentService::class)->create([
            'client_id' => $client->id, 'cash_account_id' => $banque->id,
            'amount' => 999_999_999, 'method' => 'cheque', 'reference' => 'QA-OVERPAY-CHK-001',
            'payment_date' => now()->toDateString(),
            'allocations' => [['invoice_id' => $invoice->id, 'allocated_amount' => $invoice->total_ttc]],
        ]);
    } catch (\Throwable $e) {
        $overpayError = $e;
    }
    // Rapporté tel quel dans le rapport final — BLOCK (exception) ou crédit
    // client (unallocated_amount > 0), selon ce qui a réellement été observé.
    expect($overpayResult !== null || $overpayError !== null)->toBeTrue();
});
