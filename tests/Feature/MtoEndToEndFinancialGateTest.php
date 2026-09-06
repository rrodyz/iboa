<?php

/**
 * [P2 — Phase 6] Scénario MTO-01 (client comptant) bout-en-bout, avec le point
 * qu'OrderToCashFullChainTest court-circuite délibérément (« déjà couverte par
 * §13.2 ailleurs ») : la garde financière RÉELLE au lancement d'OF — tentative
 * de lancement AVANT tout encaissement (BLOCK, ProductionFinancialEligibility
 * Service via ProductionService::checkFinancialGate()), puis encaissement
 * intégral, puis lancement (PASS). Complète aussi la chaîne jusqu'à l'écriture
 * comptable (JournalEntry), non vérifiée par le test existant.
 *
 * Ne duplique pas ce qu'OrderToCashFullChainTest a déjà prouvé (plomberie
 * réservation/consommation/qualité/livraison/facture identique) — se
 * concentre sur le point de jonction financier manquant.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Services\ClientPaymentService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mtoE2eAdmin(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'MTOE2E-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $company = Company::firstOrCreate(['name' => 'MTO E2E Co'], ['email' => 'mtoe2e@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);

    return [$u, $company];
}

it('MTO-01 — client comptant : lancement OF bloqué sans paiement, débloqué après encaissement 100%, jusqu\'à l\'écriture comptable', function () {
    [$user, $company] = mtoE2eAdmin();
    test()->actingAs($user);

    // Client COMPTANT explicite — le point central du scénario MTO-01.
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'cash']);
    $unit = Unit::firstOrCreate(['name' => 'Pièce E2E'], ['abbreviation' => 'pce2e']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% E2E'], ['short_name' => 'TVA18E2E', 'rate' => 18, 'is_active' => true]);
    $warehouse = Warehouse::firstOrCreate(['code' => 'WH-E2E'], ['name' => 'Dépôt E2E', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]);

    $finished = Product::factory()->create(['is_stockable' => true, 'valuation_method' => 'cmp', 'production_mode' => 'mto']);

    $machine = ProductionMachine::create(['company_id' => $company->id, 'code' => 'MX-E2E', 'name' => 'Profileuse E2E', 'type' => 'profilage', 'hourly_cost' => 5000, 'status' => 'active', 'is_active' => true]);
    $line = ProductionLine::create(['company_id' => $company->id, 'machine_id' => $machine->id, 'code' => 'L-E2E', 'name' => 'Ligne E2E', 'is_active' => true]);
    $bom = BillOfMaterial::create(['company_id' => $company->id, 'product_id' => $finished->id, 'name' => 'BOM E2E', 'is_active' => true, 'labor_per_unit' => 100, 'machine_time_per_unit' => 2]);

    // ── 1-2 : Devis → validation → conversion Commande ──────────────────────
    $orderSvc = app(OrderService::class);
    $quoteSvc = app(\App\Services\QuoteService::class);
    $lineItem = ['product_id' => $finished->id, 'description' => 'Tôle bac E2E', 'quantity' => 20, 'unit_price' => 15_000, 'discount_percent' => 0, 'unit_id' => $unit->id, 'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18];

    $quote = $quoteSvc->create(['client_id' => $client->id, 'issued_at' => now()->toDateString(), 'items' => [$lineItem]]);
    $wf = app(CommercialWorkflowService::class);
    $wf->submit($quote);
    $wf->validateQuote($quote->fresh());

    $order = $quoteSvc->convertToOrder($quote->fresh());
    $wf->submit($order);
    $wf->validateOrder($order->fresh());
    $order->refresh();
    expect($order->status)->toBe('confirme');

    // ── 3 : SANS paiement, la production est refusée — À LA SOURCE ──────────
    //
    // Ce test prouvait auparavant le refus au LANCEMENT d'un OF déjà créé.
    // Depuis R4.7 le refus intervient plus tôt et plus fort : une commande
    // comptant non réglée ne produit aucun ordre de fabrication, pas même en
    // brouillon. L'assertion de blocage n'est pas retirée, elle est posée au
    // point où le blocage a désormais lieu.
    expect(ProductionOrder::where('order_id', $order->id)->exists())->toBeFalse();

    expect(fn () => app(ProductionService::class)->create([
        'order_id' => $order->id, 'product_id' => $finished->id, 'quantity_requested' => 20,
    ]))->toThrow(ValidationException::class);
    expect(ProductionOrder::where('order_id', $order->id)->exists())->toBeFalse();
    expect(ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->count())->toBe(0);

    // ── 4 : Encaissement intégral au comptoir → bon de préparation → OF ─────
    // Le règlement comptant crée l'encaissement confirmé non affecté (acompte
    // avant facture) ET le bon de préparation qui autorise la production.
    $totalTtc = (int) round(20 * 15_000 * 1.18);
    $cashAccount = CashAccount::factory()->create(['company_id' => $company->id, 'type' => 'banque', 'current_balance' => 0, 'is_active' => true]);
    app(\App\Services\BonPreparationService::class)
        ->createForCashOrder($order->fresh(), $totalTtc, 'E2E-MTO-01', $cashAccount->id);

    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $finished->id)->first();
    expect($of)->not->toBeNull();
    $of->update(['bill_of_material_id' => $bom->id, 'production_line_id' => $line->id]);

    $prodSvc = app(ProductionService::class);
    $prodSvc->allocateMaterial($of->fresh());
    $prodSvc->submitForValidation($of->fresh());
    $prodSvc->validateByChef($of->fresh());
    $prodSvc->validateByResponsable($of->fresh());

    // ── 5 : Lancement autorisé — couverture 100 % constatée par la gate ─────
    $prodSvc->launch($of->fresh());
    $prodSvc->start($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('en_cours');

    // ── 8-9 : Réservation/consommation matière ───────────────────────────────
    $coil = Coil::create(['company_id' => $company->id, 'reference' => 'BOB-E2E', 'initial_weight' => 300, 'remaining_weight' => 300, 'cost_per_kg' => 400, 'purchase_price' => 120_000, 'status' => 'disponible']);
    app(CoilConsumptionService::class)->consume($of, $coil, 150);
    expect((float) $coil->fresh()->remaining_weight)->toBe(150.0);

    // ── 10 : Production — entrée stock PF ────────────────────────────────────
    $stockSvc = app(ProductionStockService::class);
    $output = $stockSvc->recordOutput($of, ['product_id' => $finished->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20, 'length' => 1, 'unit_cost' => 2_000]);
    expect((float) ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(20.0);

    // ── 11-12 : Contrôle + libération qualité (obligatoire avant clôture) ────
    $qc = ProductionQualityControl::create(['company_id' => $company->id, 'production_order_id' => $of->id, 'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true, 'status' => 'conforme', 'controlled_at' => now()]);
    expect($qc->status)->toBe('conforme');
    $output->update(['status' => 'validee', 'validated_at' => now(), 'quality_released_at' => now()]);

    $prodSvc->finish($of->fresh());
    expect($of->fresh()->status)->toBe('termine');

    // ── 13-14 : Livraison, jamais avant libération qualité (déjà garanti par finish()) ──
    // [R4.10] Le bon de livraison constate ce qui a été chargé : chargement
    // démarré puis clôturé avant de l'établir.
    $bpSvc = app(\App\Services\BonPreparationService::class);
    $bpCharge = $order->fresh()->activeBonPreparation();
    $bpSvc->startLoading($bpCharge);
    $bpSvc->finishLoading($bpCharge->fresh());

    $dn = $orderSvc->createDeliveryNote($order->fresh());
    app(DeliveryNoteService::class)->validate($dn);
    $dn->refresh();
    $order->refresh();
    // [R4.11] La validation facture dans la foulée : la commande franchit
    // « livré » et ressort « facturé ».
    expect($dn->status)->toBe('valide')->and($order->status)->toBe('facture');

    // ── 15 : Facture + solde (déjà couvert par l'acompte) ────────────────────
    // [R4.11] La validation du bon de livraison a déjà émis la facture.
    $invoice = \App\Models\Invoice::where('delivery_note_id', $dn->id)->firstOrFail();
    app(InvoiceService::class)->validate($invoice);
    $invoice->refresh();
    expect((float) $invoice->total_ttc)->toBe((float) $totalTtc);

    // ── 16 : Écriture comptable — jamais vérifiée par les autres tests ───────
    $invoiceEntry = JournalEntry::where('reference', $invoice->number)->first();
    expect($invoiceEntry)->not->toBeNull('Aucune écriture comptable générée pour la facture '.$invoice->number);
    expect((int) $invoiceEntry->lines()->sum('debit'))->toBe((int) $invoiceEntry->lines()->sum('credit')); // partie double

    // ── Invariants finaux ─────────────────────────────────────────────────────
    expect((float) ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(0.0); // tout livré
    expect($of->fresh()->status)->toBe('termine');
    expect($order->fresh()->status)->toBe('facture'); // avancé depuis 'livre' par la facturation
});
