<?php

/**
 * [P3 — Phase 5 & 6] Acompte (comptant partiel) et crédit, sur le mécanisme
 * RÉEL de l'ERP — ProductionFinancialEligibilityService. Aucun pourcentage
 * d'acompte n'est inventé : le mode comptant exige la totalité du TTC (aucune
 * notion de pourcentage configurable trouvée dans le code), donc « acompte
 * insuffisant » = tout montant strictement inférieur au TTC, quel qu'il soit.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
});

function uatCdSociete(string $suffix): array
{
    $fy = FiscalYear::create(['label' => 'UATCD'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'UAT CD Co'.$suffix, 'email' => 'uatcd'.$suffix.'@uat.io', 'current_fiscal_year_id' => $fy->id]);
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    test()->actingAs($u);

    return [$co, $u];
}

function uatCdOrder(Company $co, Client $client, int $totalTtc, string $suffix): array
{
    // is_manufacturable=false : écarte la garde nomenclature pour éprouver
    // uniquement la garde FINANCIÈRE, non le circuit BOM (hors périmètre ici).
    $product = Product::factory()->create(['is_manufacturable' => false, 'production_mode' => 'mto']);
    $order = Order::create(['company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id, 'client_id' => $client->id, 'number' => 'CMD-CD-'.$suffix, 'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => $totalTtc]);
    OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => $totalTtc, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => $totalTtc, 'line_tax' => 0, 'line_total_ttc' => $totalTtc]);
    $of = ProductionOrder::create(['company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $product->id, 'number' => 'OF-CD-'.$suffix, 'quantity_requested' => 1, 'status' => 'brouillon']);

    return [$order, $of, $product];
}

// ── Phase 5 : acompte (comptant partiel) ─────────────────────────────────

it('PHASE 5 — aucun acompte : lancement bloqué', function () {
    [$co] = uatCdSociete('dep1');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'cash']);
    [, $of] = uatCdOrder($co, $client, 500_000, 'dep1');

    expect(fn () => app(ProductionService::class)->launch($of->fresh()))->toThrow(ValidationException::class);
});

it('PHASE 5 — acompte insuffisant (partiel) : lancement bloqué', function () {
    [$co] = uatCdSociete('dep2');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'cash']);
    [$order, $of] = uatCdOrder($co, $client, 500_000, 'dep2');

    \App\Models\ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $client->id,
        'number' => 'PAY-DEP2-'.uniqid(), 'amount' => 200_000, 'status' => 'confirme',
        'is_acompte' => true, 'unallocated_amount' => 200_000, 'allocated_amount' => 0,
        'payment_date' => now()->toDateString(), 'method' => 'especes',
    ]);

    expect((int) $order->fresh()->confirmedReceipts())->toBe(200_000);
    expect(fn () => app(ProductionService::class)->launch($of->fresh()))->toThrow(ValidationException::class);
});

it('PHASE 5 — acompte conforme (100% du TTC) : lancement autorisé', function () {
    [$co] = uatCdSociete('dep3');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'cash']);
    [$order, $of] = uatCdOrder($co, $client, 500_000, 'dep3');

    \App\Models\ClientPayment::create([
        'company_id' => $co->id, 'client_id' => $client->id,
        'number' => 'PAY-DEP3-'.uniqid(), 'amount' => 500_000, 'status' => 'confirme',
        'is_acompte' => true, 'unallocated_amount' => 500_000, 'allocated_amount' => 0,
        'payment_date' => now()->toDateString(), 'method' => 'especes',
    ]);

    expect((int) $order->fresh()->confirmedReceipts())->toBe(500_000);
    app(ProductionService::class)->launch($of->fresh());
    expect($of->fresh()->status)->not->toBe('brouillon');
});

// ── Phase 6 : crédit ──────────────────────────────────────────────────────

it('CAS 1 — plafond suffisant : PASS', function () {
    [$co] = uatCdSociete('cr1');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    [, $of] = uatCdOrder($co, $client, 2_000_000, 'cr1');

    app(ProductionService::class)->launch($of->fresh());
    expect($of->fresh()->status)->not->toBe('brouillon');
});

it('CAS 2 — plafond dépassé : BLOCK', function () {
    [$co] = uatCdSociete('cr2');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 1_000_000]);
    [, $of] = uatCdOrder($co, $client, 5_000_000, 'cr2');

    expect(fn () => app(ProductionService::class)->launch($of->fresh()))->toThrow(ValidationException::class);
});

it('CAS 3 — impayé échu : BLOCK malgré plafond suffisant', function () {
    [$co] = uatCdSociete('cr3');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 10_000_000]);
    [, $of] = uatCdOrder($co, $client, 500_000, 'cr3');

    Invoice::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
        'number' => 'FAC-CR3-'.uniqid(), 'status' => 'en_retard',
        'issued_at' => now()->subDays(60), 'due_at' => now()->subDays(30),
        'total_ttc' => 300_000, 'paid_amount' => 0, 'remaining_amount' => 300_000,
    ]);

    expect(fn () => app(ProductionService::class)->launch($of->fresh()))->toThrow(ValidationException::class);
});

it('CAS 4 — dérogation OF autorisée : PASS malgré blocage financier', function () {
    [$co, $user] = uatCdSociete('cr4');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 0]);
    [, $of] = uatCdOrder($co, $client, 500_000, 'cr4');

    $of->fresh()->update([
        'financial_authorization' => 'approved', 'financial_authorized_at' => now(),
        'financial_authorized_by' => $user->id, 'financial_notes' => 'Dérogation CAS4 UAT',
    ]);

    app(ProductionService::class)->launch($of->fresh());
    expect($of->fresh()->status)->not->toBe('brouillon');
});

it('CAS 5/6 — dérogation commande (niveau Order) devenue stale après modification, PASS après ré-approbation', function () {
    [$co, $user] = uatCdSociete('cr56');
    $client = Client::factory()->create(['is_active' => true, 'payment_mode' => 'credit', 'credit_limit' => 0]);
    [$order] = uatCdOrder($co, $client, 500_000, 'cr56');

    test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Dérogation initiale CAS5'])->assertRedirect();
    expect($order->fresh()->hasValidProductionApproval())->toBeTrue();

    $item = $order->fresh()->items->first();
    $item->update(['quantity' => $item->quantity * 2, 'line_total_ht' => $item->line_total_ht * 2, 'line_total_ttc' => $item->line_total_ttc * 2]);
    $order->update(['total_ttc' => $order->total_ttc * 2]);

    // CAS 5 : STALE
    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();

    // CAS 6 : ré-approbation → PASS
    test()->post(route('ventes.commandes.approve-production', $order->fresh()), ['motif' => 'Ré-approbation CAS6'])->assertRedirect();
    expect($order->fresh()->hasValidProductionApproval())->toBeTrue();
});
