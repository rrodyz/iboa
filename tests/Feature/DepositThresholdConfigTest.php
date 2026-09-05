<?php

/**
 * [BUG-A3-SALES-DEPOSIT-004 — FERMÉ par R3] Le seuil d'acompte exigible avant
 * production est désormais structuré, et ce fichier le vérifie comme un
 * COMPORTEMENT — conformément à ce que son en-tête précédent annonçait : « ces
 * cas resteront tels quels jusqu'à la fermeture de BUG-A3-SALES-DEPOSIT-004, où
 * ils redeviendront des tests de comportement ».
 *
 * Ce qui a changé :
 *   - `clients.payment_mode` accepte trois modes — cash / deposit / credit —
 *     dérivés de `Client::PAYMENT_MODES` par les Form Requests ET la liste
 *     déroulante (plus aucune chaîne en dur) ;
 *   - `sales_settings.deposit_required_rate` est la source canonique UNIQUE du
 *     taux, réellement lue par la garde financière
 *     (ProductionFinancialEligibilityService) : la changer change le montant
 *     exigé avant production.
 *
 * Ce qui n'a délibérément PAS changé — et reste vérifié ici pour que personne ne
 * rebranche une règle financière sur une donnée non structurée :
 *   - aucun taux d'acompte porté par la fiche client (ni colonne, ni clé
 *     étrangère vers payment_terms) : le taux est global à la société ;
 *   - `payment_terms.deposit_rate` et `item_categories.deposit_required`
 *     existent mais ne pilotent aucune garde ;
 *   - `clients.condition_paiement` reste du texte libre, sans effet de règle.
 *
 * La matrice complète du mode acompte (seuil strict, solde restant dû, arrondi,
 * fail-closed, garde MTO) vit dans DepositPaymentModeTest.
 */

use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Models\SalesSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Tests\Concerns\RefreshDatabase::class);

it('accepte le mode de règlement « acompte » par le chemin applicatif', function () {
    expect(Client::PAYMENT_MODES)->toBe(['cash', 'deposit', 'credit']);

    $regleStore = (new StoreClientRequest)->rules()['payment_mode'];

    // `UpdateClientRequest::rules()` lit `$this->route('client')` pour composer
    // ses règles d'unicité : il lui faut un résolveur de route.
    $client = Client::factory()->create();
    $update = new UpdateClientRequest;
    $update->setRouteResolver(fn () => new class($client)
    {
        public function __construct(private $client) {}

        public function parameter($nom, $defaut = null)
        {
            return $this->client;
        }
    });
    $regleUpdate = $update->rules()['payment_mode'];

    $accepte = fn (array $regles, string $mode) => validator(['payment_mode' => $mode], ['payment_mode' => $regles])->passes();

    foreach ([$regleStore, $regleUpdate] as $regles) {
        expect($accepte($regles, Client::PAYMENT_CASH))->toBeTrue()
            ->and($accepte($regles, Client::PAYMENT_DEPOSIT))->toBeTrue()
            ->and($accepte($regles, Client::PAYMENT_CREDIT))->toBeTrue()
            // La liste reste CLOSE : l'ancien libellé français et tout autre mode sont refusés.
            ->and($accepte($regles, 'acompte'))->toBeFalse()
            ->and($accepte($regles, 'leasing'))->toBeFalse();
    }
});

it('ne rattache aucun taux d\'acompte à la fiche client : le taux reste global', function () {
    // Le support par condition de règlement existe toujours...
    expect(Schema::hasColumn('payment_terms', 'deposit_required'))->toBeTrue();
    expect(Schema::hasColumn('payment_terms', 'deposit_rate'))->toBeTrue();

    // ...mais rien ne l'attache à un client : la garde ne lit QUE le taux global.
    expect(Schema::hasColumn('clients', 'payment_term_id'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'payment_terms_id'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'deposit_rate'))->toBeFalse();
    expect(Schema::hasColumn('clients', 'deposit_required_rate'))->toBeFalse();
});

it('applique le taux global aux clients acompte, et à eux seuls', function () {
    $fy = \App\Models\FiscalYear::firstOrCreate(['label' => 'DEP-2026'], ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]);
    $co = \App\Models\Company::firstOrCreate(['name' => 'DEP Co'], ['email' => 'dep@iboa.test', 'current_fiscal_year_id' => $fy->id]);
    app()->instance('current_company', $co);

    $setting = SalesSetting::current();
    $setting->deposit_required_rate = 30;
    $setting->save();

    $commande = function (string $mode) use ($co) {
        $client = Client::factory()->create(['payment_mode' => $mode, 'credit_limit' => 5000000]);

        return \App\Models\Order::create([
            'company_id' => $co->id, 'fiscal_year_id' => $co->current_fiscal_year_id,
            'client_id' => $client->id, 'number' => 'DEP-CMD-'.uniqid(),
            'status' => 'confirme', 'issued_at' => now(), 'total_ttc' => 1000000,
        ]);
    };

    // Acompte : le taux global fixe l'exigence.
    expect($commande(Client::PAYMENT_DEPOSIT)->requiredBeforeProduction())->toBe(300000);
    // Comptant : totalité du TTC — le taux d'acompte ne s'y applique pas.
    expect($commande(Client::PAYMENT_CASH)->requiredBeforeProduction())->toBe(1000000);
    // Crédit : aucun chemin par le paiement — l'éligibilité passe par le plafond.
    expect($commande(Client::PAYMENT_CREDIT)->requiredBeforeProduction())->toBeNull();

    // L'ancien libellé français n'est porté par aucune ligne.
    expect(DB::table('clients')->where('payment_mode', 'acompte')->count())->toBe(0);
});
