<?php

/**
 * Non-régression : le générateur de numéros de documents ne doit JAMAIS
 * produire un numéro déjà existant, même quand des documents ont été créés
 * hors du service (seeder de démo, import, SQL manuel) — cas qui laissait
 * document_sequences.last_number en retard et provoquait un SQLSTATE[23000]
 * Duplicate entry à chaque création de devis/facture/commande.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Quote;
use App\Services\DocumentSequenceService;

uses(\Tests\Concerns\RefreshDatabase::class);

function seqCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => '2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(
        ['name' => 'Seq Test Co'],
        ['email' => 'seq@iboa.test', 'current_fiscal_year_id' => $fy->id]
    );
}

it('nextNumber ne collisionne jamais avec un document cree hors service', function () {
    $company = seqCompany();
    $client  = Client::factory()->create();
    $year    = now()->format('Y');

    // Simule un devis insere directement (seeder/import) SANS passer par le service.
    // Aucune ligne document_sequences n'existe -> sans le fix, le service repartirait a 001.
    Quote::create([
        'number'         => "DEV-{$year}-001",
        'company_id'     => $company->id,
        'fiscal_year_id' => $company->current_fiscal_year_id,
        'client_id'      => $client->id,
        'issued_at'      => now()->toDateString(),
    ]);

    $num = app(DocumentSequenceService::class)->nextNumber($company, 'devis');

    // Le numero genere ne doit PAS deja exister (sinon Duplicate entry au INSERT).
    expect($num)->not->toBe("DEV-{$year}-001")
        ->and(Quote::where('number', $num)->exists())->toBeFalse()
        // Resync correcte : le compteur s'aligne sur le MAX reel (001) puis incremente -> 002.
        ->and($num)->toBe("DEV-{$year}-002");
});

it('nextNumber reste monotone sur des appels successifs', function () {
    $company = seqCompany();
    $year    = now()->format('Y');
    $svc     = app(DocumentSequenceService::class);

    $a = $svc->nextNumber($company, 'devis');
    $b = $svc->nextNumber($company, 'devis');

    expect($a)->toBe("DEV-{$year}-001")
        ->and($b)->toBe("DEV-{$year}-002")
        ->and($a)->not->toBe($b);
});
