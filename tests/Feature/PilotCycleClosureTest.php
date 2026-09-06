<?php

/**
 * [P7.1 — Phase 12] Rejoue le cycle pilote réel qui a révélé le bug
 * `gross_material_cost` (clôture OF) et la lacune UX BP/chargement (§13.7),
 * en passant — contrairement à OrderToCashFullChainTest — RÉELLEMENT par
 * Bon de préparation → démarrer chargement → terminer chargement avant le
 * BL, et par un acompte comptant imputé après coup à la facture (comme le
 * cycle pilote réel : paiement caisse à la commande, facture ensuite,
 * imputation) plutôt qu'un encaissement post-facture immédiat.
 *
 * Assertions spécifiques à cette mission, non couvertes ailleurs dans un
 * seul et même parcours : OF clôturé (le point qui échouait), un seul
 * paiement client pour tout le cycle (pas de double encaissement), les
 * écritures comptables générées sont équilibrées, le compte client est
 * soldé après imputation.
 */

use App\Models\CashAccount;
use App\Models\Client;
use App\Models\ClientPayment;
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
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use App\Modules\Production\Services\CoilConsumptionService;
use App\Modules\Production\Services\ProductionService;
use App\Modules\Production\Services\ProductionStockService;
use App\Services\BonPreparationService;
use App\Services\ClientPaymentService;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function pilotAdmin(): User
{
    $role    = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $company = pilotCompany();
    $u       = User::factory()->create(['company_id' => $company->id]);
    $u->assignRole($role);
    return $u;
}

function pilotCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'PILOT-CYCLE-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Pilot Cycle Co'],
        ['email' => 'pilotcycle@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

it('rejoue commande comptant → OF → clôture → BP → chargement → BL → facture → imputation acompte sans double paiement, écritures équilibrées, client soldé', function () {
    $user    = pilotAdmin();
    $company = pilotCompany();
    $this->actingAs($user);

    $client  = Client::factory()->create(['is_active' => true, 'payment_mode' => Client::PAYMENT_CASH]);
    $unit    = Unit::firstOrCreate(['name' => 'Pièce PILOT'], ['abbreviation' => 'pcpi']);
    $taxRate = TaxRate::firstOrCreate(['name' => 'TVA 18% PILOT'], ['short_name' => 'TVA18PI', 'rate' => 18, 'is_active' => true]);
    $warehouse = Warehouse::firstOrCreate(
        ['code' => 'WH-PILOT'],
        ['name' => 'Dépôt Pilot', 'company_id' => $company->id, 'is_active' => true, 'is_default' => true]
    );

    $finished = Product::factory()->create([
        'is_stockable' => true, 'valuation_method' => 'cmp', 'production_mode' => 'mto',
    ]);
    $machine = ProductionMachine::create([
        'company_id' => $company->id, 'code' => 'MX-PILOT', 'name' => 'Profileuse Pilot',
        'type' => 'profilage', 'hourly_cost' => 5_000, 'status' => 'active', 'is_active' => true,
    ]);
    $line = ProductionLine::create([
        'company_id' => $company->id, 'machine_id' => $machine->id, 'code' => 'L-PILOT', 'name' => 'Ligne Pilot', 'is_active' => true,
    ]);
    $bom = BillOfMaterial::create([
        'company_id' => $company->id, 'product_id' => $finished->id, 'name' => 'BOM Pilot',
        'is_active' => true, 'labor_per_unit' => 100, 'machine_time_per_unit' => 2,
    ]);

    $orderSvc = app(OrderService::class);
    $workflow = app(CommercialWorkflowService::class);

    // [P7.1] Commande créée directement (pas via QuoteService::create()) —
    // App\Services\Sales\SalesTaxLabelService, appelé par QuoteService,
    // n'existe que dans les changements non committés du dépôt principal
    // (hors périmètre de ce worktree/branche, jamais touché). La création
    // de devis n'est pas l'objet de ce test ; passer par la commande
    // directement exerce exactement la même chaîne OF/production/paiement.
    $order = $orderSvc->create([
        'client_id' => $client->id, 'issued_at' => now()->toDateString(),
        'items' => [[
            'product_id' => $finished->id, 'description' => 'Fer à béton pilote',
            'quantity' => 50, 'unit_price' => 10_000, 'discount_percent' => 0, 'unit_id' => $unit->id,
            'tax_rate_id' => $taxRate->id, 'tax_rate_value' => 18,
        ]],
    ]);
    $workflow->submit($order);
    $workflow->validateOrder($order->fresh());
    $order->refresh();

    // ── Paiement comptant à la commande (acompte, avant facture — cycle réel) ──
    $totalTtc = (int) round(50 * 10_000 * 1.18);
    $bp = app(BonPreparationService::class)->createForCashOrder($order, $totalTtc, 'CAISSE-PILOT-001');
    expect(ClientPayment::where('client_id', $client->id)->count())->toBe(1);
    $onlyPayment = ClientPayment::where('client_id', $client->id)->first();
    expect((int) $onlyPayment->amount)->toBe($totalTtc);

    // ── OF : lancement → production → qualité → clôture (le point qui échouait) ──
    $of = ProductionOrder::where('order_id', $order->id)->where('product_id', $finished->id)->first();
    $of->update(['bill_of_material_id' => $bom->id, 'production_line_id' => $line->id]);

    $prodSvc = app(ProductionService::class);
    $prodSvc->allocateMaterial($of->fresh());
    $prodSvc->submitForValidation($of->fresh());
    $prodSvc->validateByChef($of->fresh());
    $prodSvc->validateByResponsable($of->fresh());
    $of->fresh()->update(['financial_authorization' => 'bypassed', 'financial_authorized_at' => now(), 'financial_authorized_by' => $user->id]);
    $prodSvc->launch($of->fresh());
    $prodSvc->start($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('en_cours');

    $coil = Coil::create([
        'company_id' => $company->id, 'reference' => 'BOB-PILOT', 'initial_weight' => 500,
        'remaining_weight' => 500, 'cost_per_kg' => 400, 'purchase_price' => 200_000, 'status' => 'disponible',
    ]);
    app(CoilConsumptionService::class)->consume($of, $coil, 200);

    $stockSvc = app(ProductionStockService::class);
    $output = $stockSvc->recordOutput($of, [
        'product_id' => $finished->id, 'warehouse_id' => $warehouse->id,
        'quantity' => 50, 'length' => 1, 'unit_cost' => 1_600,
    ]);

    $qc = ProductionQualityControl::create([
        'company_id' => $company->id, 'production_order_id' => $of->id,
        'thickness_ok' => true, 'length_ok' => true, 'color_ok' => true, 'visual_ok' => true,
        'status' => 'conforme', 'controlled_at' => now(),
    ]);
    $output->update(['status' => 'validee', 'validated_at' => now(), 'quality_released_at' => now()]);

    $prodSvc->finish($of->fresh());
    $of->refresh();
    expect($of->status)->toBe('termine'); // ← exactement l'appel qui échouait sur gross_material_cost

    $cost = ProductionCost::where('production_order_id', $of->id)->first();
    expect($cost)->not->toBeNull()
        ->and($cost->gross_material_cost)->not->toBeNull()
        ->and((float) $cost->total_cost)->toBeGreaterThan(0);

    expect((float) ProductStock::where('product_id', $finished->id)->where('warehouse_id', $warehouse->id)->value('quantity'))->toBe(50.0);

    // ── Bon de préparation : chargement (§13.7 — le prérequis UX de cette mission) ──
    expect($order->fresh()->isReadyForDelivery())->toBeFalse();
    app(BonPreparationService::class)->startLoading($bp->fresh());
    app(BonPreparationService::class)->finishLoading($bp->fresh());
    expect($order->fresh()->isReadyForDelivery())->toBeTrue();

    // ── BL ────────────────────────────────────────────────────────────────────
    $dn = $orderSvc->createDeliveryNote($order->fresh());
    app(DeliveryNoteService::class)->validate($dn);
    $dn->refresh();
    expect($dn->status)->toBe('valide');

    // ── Facture + imputation de l'acompte DÉJÀ existant (pas un nouveau paiement) ──
    // [R4.11] La validation du bon de livraison a déjà émis la facture :
    // on la récupère au lieu de la créer. Un second appel serait refusé —
    // c'est la garde anti-double facturation qui le prouve.
    $invoice = \App\Models\Invoice::where('delivery_note_id', $dn->id)->firstOrFail();
    app(InvoiceService::class)->validate($invoice);
    $invoice->refresh();

    app(ClientPaymentService::class)->addAllocation($onlyPayment->fresh(), $invoice->id, $totalTtc);
    $invoice->refresh();

    expect($invoice->status)->toBe('payee')
        ->and((int) $invoice->remaining_amount)->toBe(0);

    // ── Un seul paiement pour tout le cycle ──────────────────────────────────
    expect(ClientPayment::where('client_id', $client->id)->count())->toBe(1);

    // ── Compte client soldé ───────────────────────────────────────────────────
    expect((int) \App\Models\Invoice::where('client_id', $client->id)->sum('remaining_amount'))->toBe(0);

    // ── Écritures comptables équilibrées ─────────────────────────────────────
    $entries = JournalEntry::where('reference', $invoice->number)
        ->orWhere('reference', $invoice->number . '-STK')
        ->orWhere('reference', 'like', '%' . $onlyPayment->reference . '%')
        ->get();
    expect($entries)->not->toBeEmpty();
    foreach ($entries as $entry) {
        expect((float) $entry->total_debit)->toBe((float) $entry->total_credit);
    }
});
