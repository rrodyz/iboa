<?php

/**
 * [BUG-SOLDE-LISTE] Le « Solde dû » de la liste clients doit suivre les
 * règlements, quel que soit le chemin d'imputation.
 *
 * La liste clients n'effectue aucun calcul : elle affiche la colonne
 * persistée `clients.balance`, rafraîchie par Client::recalculateBalance().
 * Deux chemins la rafraîchissaient — la création d'un encaissement (événement
 * PaymentReceived) et son annulation — mais pas le lettrage a posteriori d'un
 * encaissement déjà existant sur une facture.
 *
 * Or c'est précisément le chemin du comptant OA METAL : l'argent est encaissé
 * au comptoir AVANT la facture, puis imputé quand la facture est émise. La
 * facture passait à « payée », le reste dû à zéro, et la liste continuait
 * d'afficher l'ancien montant — un client soldé y apparaissait débiteur.
 *
 * Les montants sont en FCFA entiers.
 */

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Services\ClientPaymentService;

uses(\Tests\Concerns\RefreshDatabase::class);

function soldeCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'SOLDE-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $co = Company::firstOrCreate(
        ['name' => 'SOLDE Co'],
        ['email' => 'solde@oa-metal.test', 'current_fiscal_year_id' => $fy->id],
    );
    app()->instance('current_company', $co);

    return $co;
}

/** Facture émise, non réglée. */
function soldeInvoice(Client $client, int $ttc): Invoice
{
    $co = soldeCompany();

    return Invoice::create([
        'company_id' => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'SOLDE-FA-'.uniqid(),
        'status' => 'emise',
        'issued_at' => now(),
        'due_at' => now()->addDays(30),
        'currency_code' => 'XOF',
        'subtotal_ht' => (int) round($ttc / 1.18),
        'total_ttc' => $ttc,
        'paid_amount' => 0,
        'remaining_amount' => $ttc,
    ]);
}

/** Encaissement confirmé non affecté — l'argent du comptoir, avant facture. */
function soldeAcompte(Client $client, int $montant): ClientPayment
{
    $co = soldeCompany();

    return ClientPayment::create([
        'company_id' => $co->id,
        'client_id' => $client->id,
        'number' => 'SOLDE-ENC-'.uniqid(),
        'amount' => $montant,
        'net_amount' => $montant,
        'payment_date' => now(),
        'status' => 'confirme',
        'is_acompte' => true,
        'allocated_amount' => 0,
        'unallocated_amount' => $montant,
    ]);
}

/** Ce que la liste clients affiche réellement : la colonne persistée. */
function soldeAffiche(Client $client): int
{
    return (int) $client->fresh()->balance;
}

// ─────────── Scénario 1 — facture soldée par lettrage a posteriori ───────────

it('affiche un solde nul quand la facture est intégralement lettrée après coup', function () {
    soldeCompany();
    $client = Client::factory()->create(['is_active' => true]);
    $facture = soldeInvoice($client, 754_020);
    $client->recalculateBalance();

    expect(soldeAffiche($client))->toBe(754_020); // dû avant règlement

    $encaissement = soldeAcompte($client, 754_020);
    app(ClientPaymentService::class)->addAllocation($encaissement, $facture->id, 754_020);

    expect($facture->fresh()->status)->toBe('payee')
        ->and((int) $facture->fresh()->remaining_amount)->toBe(0)
        ->and(soldeAffiche($client))->toBe(0);
});

// ─────────── Scénario 2 — règlement partiel ───────────

it('affiche le reste dû quand la facture n est que partiellement lettrée', function () {
    soldeCompany();
    $client = Client::factory()->create(['is_active' => true]);
    $facture = soldeInvoice($client, 1_000_000);
    $client->recalculateBalance();

    $encaissement = soldeAcompte($client, 400_000);
    app(ClientPaymentService::class)->addAllocation($encaissement, $facture->id, 400_000);

    expect($facture->fresh()->status)->toBe('partiellement_payee')
        ->and((int) $facture->fresh()->remaining_amount)->toBe(600_000)
        ->and(soldeAffiche($client))->toBe(600_000);
});

// ─────────── Scénario 3 — lettrages successifs jusqu'au solde ───────────

it('retombe à zéro après plusieurs lettrages partiels couvrant la facture', function () {
    soldeCompany();
    $client = Client::factory()->create(['is_active' => true]);
    $facture = soldeInvoice($client, 900_000);
    $client->recalculateBalance();

    $service = app(ClientPaymentService::class);
    $service->addAllocation(soldeAcompte($client, 500_000), $facture->id, 500_000);
    expect(soldeAffiche($client))->toBe(400_000);

    $service->addAllocation(soldeAcompte($client, 400_000), $facture->id, 400_000);
    expect(soldeAffiche($client))->toBe(0);
});

// ─────────── Anti-régression — les deux clients réels observés ───────────

it('équivalent POST-RESET-001 : facture réglée, encaissement imputé, solde nul', function () {
    soldeCompany();
    $client = Client::factory()->create(['is_active' => true, 'code' => 'EQ-POST-RESET']);
    $facture = soldeInvoice($client, 35_400);
    $client->recalculateBalance();

    app(ClientPaymentService::class)
        ->addAllocation(soldeAcompte($client, 35_400), $facture->id, 35_400);

    expect(soldeAffiche($client))->toBe(0);
});

it('équivalent CLI-00001 : encaissement comptoir avant facture, puis imputation', function () {
    soldeCompany();
    $client = Client::factory()->create(['is_active' => true, 'code' => 'EQ-CLI-00001']);

    // L'argent arrive d'abord — c'est le geste du comptoir.
    $encaissement = soldeAcompte($client, 754_020);
    $client->recalculateBalance();
    expect(soldeAffiche($client))->toBe(0); // aucune facture ouverte

    // La facture est émise ensuite, puis lettrée avec l'encaissement.
    $facture = soldeInvoice($client, 754_020);
    $client->recalculateBalance();
    expect(soldeAffiche($client))->toBe(754_020);

    app(ClientPaymentService::class)->addAllocation($encaissement, $facture->id, 754_020);

    expect(soldeAffiche($client))->toBe(0);
});
