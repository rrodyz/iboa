<?php

/**
 * [FIX P1-A / P1-B — autorisation financière MTO par profils habilités +
 * invalidation d'une autorisation après modification commerciale]
 *
 * P1-A : « Administrateur des ventes » (rôle A3 = responsable_commercial,
 * seul palier de responsabilité commerciale au-dessus du commercial simple
 * dans le référentiel de rôles) rejoint DG (directeur) et DAF (daf) parmi
 * les profils habilités à accorder la dérogation financière exceptionnelle
 * de production (permission production.approve_financial). DG et DAF
 * fonctionnaient déjà AVANT ce correctif (preuve rouge : DG 302/approved,
 * DAF 302/approved, sur 8e9bcec, seedeur de rôles réel) — seul
 * responsable_commercial était bloqué (403). Ce correctif n'ajoute qu'UNE
 * permission déjà existante à UN rôle déjà existant — aucune nouvelle
 * permission créée.
 *
 * P1-B : Order::hasValidProductionApproval() vérifie désormais, en plus du
 * booléen et de l'expiration, que le CONTRAT FINANCIER (client, mode de
 * règlement, lignes, totaux — Order::productionFinancialFingerprint())
 * n'a pas changé depuis l'approbation. Preuve rouge confirmée sur 8e9bcec :
 * une commande triplée après approbation restait « hasValidProductionApproval
 * () === true ». L'historique (approved_by/at/reason/unpaid) n'est jamais
 * effacé par une invalidation — seule la VALIDITÉ change.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
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

function mfaSetup(string $suffix): array
{
    $fy = FiscalYear::create(['label' => 'MFA'.$suffix, 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = Company::create(['name' => 'MFA Co'.$suffix, 'email' => 'mfa'.$suffix.'@mfa.io', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    // Client crédit sans plafond : exigenceCredit() refuse toujours (financièrement bloqué, mécanisme réel).
    $client = Client::create(['company_id' => $co->id, 'name' => 'Client MFA'.$suffix, 'code' => 'CLI-MFA'.$suffix, 'payment_mode' => 'credit', 'credit_limit' => 0, 'is_active' => true]);
    $product = Product::factory()->create(['is_manufacturable' => false, 'production_mode' => 'mto']);

    $order = Order::create([
        'company_id' => $co->id, 'client_id' => $client->id, 'fiscal_year_id' => $fy->id,
        'number' => 'CMD-MFA-'.$suffix, 'status' => 'confirme', 'issued_at' => now(),
        'subtotal_ht' => 1000000, 'total_tax' => 0, 'total_ttc' => 1000000,
    ]);
    $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'description' => 'x', 'quantity' => 10, 'unit_price' => 100000, 'discount_percent' => 0, 'tax_rate_value' => 0, 'line_total_ht' => 1000000, 'line_tax' => 0, 'line_total_ttc' => 1000000]);

    return [$co, $order, $client, $product, $item];
}

function mfaUser(Company $co, string $role): User
{
    $u = User::factory()->create(['company_id' => $co->id, 'email_verified_at' => now()]);
    $u->assignRole(Role::findByName($role, 'web'));

    return $u;
}

function mfaApprove(User $approver, Order $order, string $motif = 'Dérogation exceptionnelle'): \Illuminate\Testing\TestResponse
{
    test()->actingAs($approver);

    return test()->post(route('ventes.commandes.approve-production', $order), ['motif' => $motif]);
}

// ── A01-A04 : permissions ────────────────────────────────────────────────

it('A01 — DG (directeur) peut approuver la production', function () {
    [$co, $order] = mfaSetup('a01');
    $response = mfaApprove(mfaUser($co, 'directeur'), $order);

    $response->assertRedirect();
    expect($order->fresh()->production_approved)->toBeTrue();
});

it('A02 — Administrateur des ventes (responsable_commercial) peut désormais approuver la production', function () {
    [$co, $order] = mfaSetup('a02');
    $response = mfaApprove(mfaUser($co, 'responsable_commercial'), $order);

    $response->assertRedirect();
    expect($order->fresh()->production_approved)->toBeTrue();
});

it('A03 — DAF conserve sa permission (non-régression)', function () {
    [$co, $order] = mfaSetup('a03');
    $response = mfaApprove(mfaUser($co, 'daf'), $order);

    $response->assertRedirect();
    expect($order->fresh()->production_approved)->toBeTrue();
});

it('A04 — utilisateur non habilité (commercial simple) reste bloqué', function () {
    [$co, $order] = mfaSetup('a04');
    $response = mfaApprove(mfaUser($co, 'commercial'), $order);

    $response->assertForbidden();
    expect($order->fresh()->production_approved)->toBeFalse();
});

it('A13 — production.approve_financial n\'accorde aucun droit annexe (pas de super-permission)', function () {
    [$co] = mfaSetup('a13');
    $u = mfaUser($co, 'responsable_commercial');
    test()->actingAs($u);

    // Permissions comptables/admin jamais accordées à responsable_commercial,
    // ni avant ni après ce correctif — seule production.approve_financial a
    // été ajoutée à son rôle (une ligne dans le seeder, rien d'autre).
    expect($u->can('accounting.manage'))->toBeFalse();
    expect($u->can('payments.create'))->toBeFalse();
    expect($u->can('users.manage'))->toBeFalse();
    expect($u->can('production.approve_financial'))->toBeTrue();
});

// ── A05-A08 : audit trail ────────────────────────────────────────────────

it('A05/A06/A07/A08 — piste d\'audit complète : approbateur, date, motif, snapshot impayé', function () {
    [$co, $order] = mfaSetup('audit');
    $approver = mfaUser($co, 'daf');

    $before = now();
    mfaApprove($approver, $order, 'Client fidèle, retard exceptionnel de trésorerie')->assertRedirect();
    $order->refresh();

    expect($order->production_approved_by)->toBe($approver->id);
    expect($order->production_approved_at)->not->toBeNull();
    expect($order->production_approved_at->gte($before->subSecond()))->toBeTrue();
    expect($order->production_approval_reason)->toBe('Client fidèle, retard exceptionnel de trésorerie');
    expect($order->production_approval_unpaid)->toBe(1000000); // rien encaissé : snapshot = TTC intégral
});

it('motif obligatoire — approbation refusée sans justification', function () {
    [$co, $order] = mfaSetup('motif');
    test()->actingAs(mfaUser($co, 'daf'));

    $response = test()->post(route('ventes.commandes.approve-production', $order), ['motif' => '']);

    $response->assertSessionHasErrors('motif');
    expect($order->fresh()->production_approved)->toBeFalse();
});

// ── A09-A11 : lancement OF ───────────────────────────────────────────────

function mfaProductionOrder(Company $co, Order $order, Product $product): ProductionOrder
{
    return ProductionOrder::create([
        'company_id' => $co->id, 'order_id' => $order->id, 'product_id' => $product->id,
        'number' => 'OF-'.$order->number, 'quantity_requested' => 10, 'status' => 'brouillon',
    ]);
}

it('A09 — commande financièrement bloquée sans approbation : lancement OF refusé', function () {
    [$co, $order, , $product] = mfaSetup('a09');
    $of = mfaProductionOrder($co, $order, $product);

    expect(fn () => app(ProductionService::class)->launch($of, force: true))
        ->toThrow(ValidationException::class);
});

it('A10 — approbation valide (DG) autorise le lancement OF malgré le blocage financier', function () {
    [$co, $order, , $product] = mfaSetup('a10');
    mfaApprove(mfaUser($co, 'directeur'), $order)->assertRedirect();
    $of = mfaProductionOrder($co, $order->fresh(), $product);

    app(ProductionService::class)->launch($of, force: true);

    expect($of->fresh()->status)->toBe('lance');
});

it('A11 — approbation expirée n\'est plus valide', function () {
    [$co, $order] = mfaSetup('a11');
    test()->actingAs(mfaUser($co, 'daf'));
    test()->post(route('ventes.commandes.approve-production', $order), ['motif' => 'Approbation courte', 'valide_jours' => 1])->assertRedirect();

    $order->refresh();
    expect($order->hasValidProductionApproval())->toBeTrue();

    $order->update(['production_approval_expires_at' => today()->subDay()]);
    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

// ── B01-B12 : invalidation après modification commerciale (P1-B) ───────

it('B01 — approbation initialement valide', function () {
    [$co, $order] = mfaSetup('b01');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    expect($order->fresh()->hasValidProductionApproval())->toBeTrue();
});

it('B02 — changement de quantité rend l\'approbation obsolète', function () {
    [$co, $order, , , $item] = mfaSetup('b02');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $item->update(['quantity' => $item->quantity + 20]);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

it('B03 — changement de prix unitaire rend l\'approbation obsolète', function () {
    [$co, $order, , , $item] = mfaSetup('b03');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $item->update(['unit_price' => $item->unit_price + 5000]);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

it('B04 — changement de remise rend l\'approbation obsolète', function () {
    [$co, $order, , , $item] = mfaSetup('b04');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $item->update(['discount_percent' => 10]);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

it('B05 — changement de client rend l\'approbation obsolète', function () {
    [$co, $order] = mfaSetup('b05');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $autreClient = Client::create(['company_id' => $co->id, 'name' => 'Autre client', 'code' => 'CLI-AUTRE-B05', 'payment_mode' => 'credit', 'credit_limit' => 0, 'is_active' => true]);
    $order->update(['client_id' => $autreClient->id]);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

it('B06 — changement du mode de règlement du client rend l\'approbation obsolète', function () {
    [$co, $order, $client] = mfaSetup('b06');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $client->update(['payment_mode' => 'cash']);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

it('B07 — changement de price_mode (commande) rend l\'approbation obsolète', function () {
    [$co, $order] = mfaSetup('b07');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $order->update(['price_mode' => $order->price_mode === 'ht' ? 'ttc' : 'ht']);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});

it('B08 — une note interne non financière ne rend PAS l\'approbation obsolète', function () {
    [$co, $order] = mfaSetup('b08');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();

    $order->update(['notes' => 'Livraison à confirmer avec le client, RAS financièrement.']);

    expect($order->fresh()->hasValidProductionApproval())->toBeTrue();
});

it('B09 — approbation obsolète bloque le lancement OF', function () {
    [$co, $order, , $product, $item] = mfaSetup('b09');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();
    $of = mfaProductionOrder($co, $order->fresh(), $product);

    $item->update(['quantity' => $item->quantity * 5]);

    expect(fn () => app(ProductionService::class)->launch($of, force: true))
        ->toThrow(ValidationException::class);
});

it('B10/B11 — ré-approbation après modification produit un nouveau contrat valide et débloque le lancement', function () {
    [$co, $order, , $product, $item] = mfaSetup('b1011');
    $approver = mfaUser($co, 'daf');
    mfaApprove($approver, $order, 'Première approbation')->assertRedirect();
    $of = mfaProductionOrder($co, $order->fresh(), $product);

    $item->update(['quantity' => $item->quantity * 2, 'line_total_ht' => $item->line_total_ht * 2, 'line_total_ttc' => $item->line_total_ttc * 2]);
    $order->update(['total_ttc' => $order->total_ttc * 2, 'subtotal_ht' => $order->subtotal_ht * 2]);
    expect($order->fresh()->hasValidProductionApproval())->toBeFalse(); // B10 préalable : bien obsolète avant ré-approbation

    // Ré-approbation DIRECTE, sans révocation préalable — un OF actif ('brouillon')
    // existe déjà, ce qui interdirait justement revoke-production() ; la garde de
    // approveProduction() teste hasValidProductionApproval() (pas le seul drapeau
    // brut) et laisse donc passer une nouvelle approbation sur un contrat stale.
    mfaApprove($approver, $order->fresh(), 'Ré-approbation après révision commerciale')->assertRedirect();

    expect($order->fresh()->hasValidProductionApproval())->toBeTrue(); // B10 : nouveau contrat valide

    app(ProductionService::class)->launch($of->fresh(), force: true); // B11 : lancement débloqué
    expect($of->fresh()->status)->toBe('lance');
});

it('B12 — approbation historique sans empreinte (legacy) est fail-closed : obsolète', function () {
    [$co, $order] = mfaSetup('b12');
    mfaApprove(mfaUser($co, 'daf'), $order)->assertRedirect();
    expect($order->fresh()->hasValidProductionApproval())->toBeTrue();

    // Simule une approbation posée AVANT ce correctif (empreinte jamais calculée).
    $order->update(['production_approval_fingerprint' => null]);

    expect($order->fresh()->hasValidProductionApproval())->toBeFalse();
});
