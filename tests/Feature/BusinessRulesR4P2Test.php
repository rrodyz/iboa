<?php

/**
 * [R4 — P2] Traçabilité, chargement, et vue client 360°.
 *
 *  - R4.1  réouverture d'une commande annulée : responsable + motif + trace ;
 *  - R4.10 bon de préparation « chargé » exigé avant bon de livraison, garde
 *          posée au service et non au seul contrôleur, plus refus de la
 *          sur-livraison ;
 *  - R4.13 crédit autorisé / encours / disponible depuis le service canonique ;
 *  - R4.14 créé le / créé par ;
 *  - R4.15 modifié le (l'auteur d'une modification n'est pas traçable
 *          aujourd'hui — ce test fixe cet état plutôt que de le maquiller) ;
 *  - R4.16/17 dossier commercial consolidé, borné, et étanche entre clients ;
 *  - R4.18 toute approbation est retrouvable dans le journal commercial.
 */

use App\Models\BonPreparation;
use App\Models\Client;
use App\Models\CommercialValidation;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\Sales\PreparationEligibilityService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

// ───────────────────────────── Montage commun ─────────────────────────────

function p2Company(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'R4P2-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $co = Company::firstOrCreate(
        ['name' => 'R4P2 Co'],
        ['email' => 'r4p2@oa-metal.test', 'current_fiscal_year_id' => $fy->id],
    );
    app()->instance('current_company', $co);

    return $co;
}

/**
 * Utilisateur porteur des seules permissions demandées.
 *
 * `sales.bypass_self_validation` est créée sans être accordée : le trait de
 * workflow l'interroge, et Spatie lève si elle n'existe pas en base.
 */
function p2User(array $permissions = []): User
{
    $user = User::factory()->create(['company_id' => p2Company()->id, 'email_verified_at' => now()]);
    foreach ([...$permissions, 'sales.bypass_self_validation'] as $nom) {
        Permission::findOrCreate($nom, 'web');
    }
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    test()->actingAs($user->fresh());

    return $user;
}

function p2Admin(): User
{
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $u = User::factory()->create(['company_id' => p2Company()->id, 'email_verified_at' => now()]);
    $u->assignRole($role);
    test()->actingAs($u);

    return $u;
}

function p2Order(string $statut = 'confirme', ?Client $client = null, int $ttc = 100000): Order
{
    $co = p2Company();
    $client ??= Client::factory()->create(['is_active' => true]);

    return Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id'      => $client->id,
        'number'         => 'R4P2-CMD-'.uniqid(),
        'status'         => $statut,
        'issued_at'      => now(),
        'total_ttc'      => $ttc,
        'subtotal_ht'    => (int) round($ttc / 1.18),
    ]);
}

function p2Bp(Order $order, string $statut): BonPreparation
{
    return BonPreparation::create([
        'company_id'     => $order->company_id,
        'order_id'       => $order->id,
        'fiscal_year_id' => $order->fiscal_year_id,
        'number'         => 'R4P2-BP-'.uniqid(),
        'payment_mode'   => 'credit',
        'status'         => $statut,
    ]);
}

// ═══════════════ R4.1 — Réouverture d'une commande annulée ═══════════════

it('R4.1 — un utilisateur sans la permission ne peut pas rouvrir une commande annulée', function () {
    $order = p2Order('annule');
    p2User([]); // aucun droit de réouverture

    expect(fn () => app(CommercialWorkflowService::class)->reopenOrder($order, 'tentative sans droit'))
        ->toThrow(RuntimeException::class, 'orders.reopen');

    expect($order->fresh()->status)->toBe('annule');
});

it('R4.1 — un responsable ne peut pas rouvrir sans motif', function () {
    $order = p2Order('annule');
    p2User(['orders.reopen']);

    expect(fn () => app(CommercialWorkflowService::class)->reopenOrder($order, ''))
        ->toThrow(RuntimeException::class, 'motif');
    expect(fn () => app(CommercialWorkflowService::class)->reopenOrder($order, 'ok'))
        ->toThrow(RuntimeException::class, 'motif');

    expect($order->fresh()->status)->toBe('annule');
});

it('R4.1 — un responsable rouvre avec motif : retour en brouillon, jamais en confirmée', function () {
    $order = p2Order('annule');
    p2User(['orders.reopen']);

    $rouverte = app(CommercialWorkflowService::class)
        ->reopenOrder($order, 'client revenu sur sa décision, marchandise encore disponible');

    expect($rouverte->status)->toBe('brouillon')
        ->and($rouverte->status)->not->toBe('confirme');
});

it('R4.1 — la réouverture est tracée : auteur, horodatage, ancien et nouveau statut', function () {
    $order = p2Order('annule');
    $user = p2User(['orders.reopen']);

    app(CommercialWorkflowService::class)->reopenOrder($order, 'accord direction du 5 septembre');

    $trace = DB::table('commercial_validations')
        ->where('document_type', 'order')->where('document_id', $order->id)
        ->where('nouveau_statut', 'brouillon')->latest('id')->first();

    expect($trace)->not->toBeNull()
        ->and((int) $trace->user_id)->toBe($user->id)
        ->and($trace->ancien_statut)->toBe('annule')
        ->and($trace->motif)->toBe('accord direction du 5 septembre')
        ->and($trace->created_at)->not->toBeNull();

    $meta = json_decode((string) $trace->metadata, true);
    expect($meta['operation'])->toBe('reopen')
        ->and((int) $meta['approved_by'])->toBe($user->id)
        ->and($meta['approved_at'])->not->toBeNull()
        ->and($meta['old_status'])->toBe('annule')
        ->and($meta['new_status'])->toBe('brouillon');
});

it('R4.1 — une commande non annulée ne se rouvre pas', function () {
    $order = p2Order('confirme');
    p2User(['orders.reopen']);

    expect(fn () => app(CommercialWorkflowService::class)->reopenOrder($order, 'motif valable ici'))
        ->toThrow(RuntimeException::class, 'Seules les commandes annulées');
});

// ═══════════════ R4.10 — Chargement terminé avant bon de livraison ═══════════════

it('R4.10 — bon de préparation en attente : le service refuse le bon de livraison', function () {
    p2Admin();
    $order = p2Order();
    p2Bp($order, 'en_attente');

    expect(fn () => app(\App\Services\DeliveryNoteService::class)->createFromOrder($order->fresh()))
        ->toThrow(RuntimeException::class, 'chargement');

    expect(DeliveryNote::where('order_id', $order->id)->count())->toBe(0);
});

it('R4.10 — chargement démarré mais non clôturé : toujours refusé', function () {
    p2Admin();
    $order = p2Order();
    p2Bp($order, 'en_cours');

    expect(fn () => app(\App\Services\DeliveryNoteService::class)->createFromOrder($order->fresh()))
        ->toThrow(RuntimeException::class, 'chargement');
});

it('R4.10 — bon de préparation chargé : le bon de livraison est autorisé', function () {
    p2Admin();
    $order = p2Order();
    p2Bp($order, 'charge');

    $bl = app(\App\Services\DeliveryNoteService::class)->createFromOrder($order->fresh());

    expect($bl->exists)->toBeTrue()
        ->and($bl->order_id)->toBe($order->id);
});

it('R4.10 — la garde tient sur la route, pas seulement dans le service', function () {
    p2Admin();
    $order = p2Order();
    p2Bp($order, 'en_attente');

    test()->post(route('ventes.commandes.delivery-note', $order))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(DeliveryNote::where('order_id', $order->id)->count())->toBe(0);
});

// ═══════════════ R4.13/R4.14/R4.15 — Fiche client ═══════════════

it('R4.13 — la fiche client affiche crédit autorisé, encours et disponible', function () {
    p2Admin();
    $client = Client::factory()->create([
        'is_active' => true, 'payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 1_000_000,
    ]);
    p2Order('confirme', $client, 250_000);

    $reponse = test()->get(route('clients.show', $client));

    $reponse->assertOk()
        ->assertSee('Crédit autorisé')
        ->assertSee('Encours actuel')
        ->assertSee('Crédit disponible');

    // Les valeurs viennent du service canonique, pas d'un calcul de vue.
    $expo = app(\App\Services\CustomerCreditExposureService::class)->assessClient($client);
    expect($expo['limited'])->toBeTrue()
        ->and((int) $expo['limit'])->toBe(1_000_000)
        ->and((int) $expo['projected'])->toBe(250_000)
        ->and((int) $expo['available'])->toBe(750_000);
});

it('R4.13 — un client hors crédit affiche N/A plutôt qu un zéro trompeur', function () {
    p2Admin();
    $client = Client::factory()->create([
        'is_active' => true, 'payment_mode' => Client::PAYMENT_CASH, 'credit_limit' => 0,
    ]);

    test()->get(route('clients.show', $client))->assertOk()->assertSee('N/A');
});

it('R4.14 — la fiche affiche créé le et créé par', function () {
    $user = p2Admin();
    $client = Client::factory()->create(['is_active' => true, 'created_by' => $user->id]);

    test()->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Créé le')
        ->assertSee('Créé par')
        ->assertSee($user->name);
});

it('R4.15 — modifié le est affiché ; modifié par ne l est pas, faute de trace fiable', function () {
    p2Admin();
    $client = Client::factory()->create(['is_active' => true]);

    $reponse = test()->get(route('clients.show', $client));

    $reponse->assertOk()->assertSee('Modifié le');

    // Aucun acteur de modification n'est journalisé aujourd'hui pour un client :
    // afficher un « Modifié par » reviendrait à désigner quelqu'un au hasard.
    $reponse->assertDontSee('Modifié par');
    expect(DB::table('audit_logs')->where('model_type', Client::class)->count())->toBe(0);
});

// ═══════════════ R4.16 / R4.17 — Dossier commercial ═══════════════

it('R4.16 — le dossier commercial expose les sept familles de documents', function () {
    p2Admin();
    $client = Client::factory()->create(['is_active' => true]);
    $order = p2Order('confirme', $client);
    p2Bp($order, 'charge');

    test()->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Dossier commercial')
        ->assertSee('Devis')
        ->assertSee('Commandes')
        ->assertSee('Bons de préparation')
        ->assertSee('Bons de livraison')
        ->assertSee('Factures')
        ->assertSee('Règlements')
        ->assertSee('Avoirs');
});

it('R4.17 — les relations manquantes existent et rattachent les bons documents', function () {
    p2Admin();
    $client = Client::factory()->create(['is_active' => true]);
    $order = p2Order('confirme', $client);
    $bp = p2Bp($order, 'charge');
    $bl = app(\App\Services\DeliveryNoteService::class)->createFromOrder($order->fresh());

    expect($client->bonPreparations()->pluck('bon_preparations.id')->all())->toBe([$bp->id])
        ->and($client->deliveryNotes()->pluck('id')->all())->toBe([$bl->id]);
});

it('R4.17 — un client ne voit jamais les documents d un autre client', function () {
    p2Admin();
    $clientA = Client::factory()->create(['is_active' => true, 'name' => 'CLIENT ALPHA']);
    $clientB = Client::factory()->create(['is_active' => true, 'name' => 'CLIENT BETA']);

    $ordreA = p2Order('confirme', $clientA);
    $ordreB = p2Order('confirme', $clientB);
    $bpA = p2Bp($ordreA, 'charge');
    $bpB = p2Bp($ordreB, 'charge');
    $blA = app(\App\Services\DeliveryNoteService::class)->createFromOrder($ordreA->fresh());
    $blB = app(\App\Services\DeliveryNoteService::class)->createFromOrder($ordreB->fresh());

    expect($clientA->bonPreparations()->pluck('bon_preparations.id')->all())->toBe([$bpA->id])
        ->and($clientA->deliveryNotes()->pluck('id')->all())->toBe([$blA->id])
        ->and($clientA->orders()->pluck('id')->all())->toBe([$ordreA->id]);

    expect($clientB->bonPreparations()->pluck('bon_preparations.id')->all())->toBe([$bpB->id])
        ->and($clientB->deliveryNotes()->pluck('id')->all())->toBe([$blB->id]);

    // Et l'écran lui-même ne laisse pas fuiter le voisin.
    test()->get(route('clients.show', $clientA))
        ->assertOk()
        ->assertSee($ordreA->number)
        ->assertDontSee($ordreB->number);
});

it('R4.17 — les listes du dossier sont bornées, jamais l historique intégral', function () {
    p2Admin();
    $client = Client::factory()->create(['is_active' => true]);
    for ($i = 0; $i < 14; $i++) {
        p2Order('confirme', $client);
    }

    $reponse = test()->get(route('clients.show', $client));
    $reponse->assertOk();

    $documents = $reponse->viewData('documents');
    $compteurs = $reponse->viewData('compteurs');

    expect($documents['orders'])->toHaveCount(10)
        ->and($compteurs['orders'])->toBe(14);
});

// ═══════════════ R4.18 — Traçabilité des approbations ═══════════════

it('R4.18 — les trois décisions laissent une trace exploitable', function () {
    $co = p2Company();
    $user = p2User(['sales.submit', 'sales.validate', 'bon_preparations.validate', 'orders.reopen', 'sales_credit_overrun.approve']);
    $workflow = app(CommercialWorkflowService::class);

    // 1. Approbation du passage en préparation (client à crédit).
    $clientCredit = Client::factory()->create(['payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 10_000_000]);
    $commande = p2Order('en_attente_validation', $clientCredit, 500_000);
    $workflow->validateOrder($commande, 'validation commerciale');
    $workflow->decidePreparationApproval($commande->fresh(), true, 'encours maîtrisé');

    // 2. Réouverture d'une commande annulée.
    $annulee = p2Order('annule', $clientCredit);
    $workflow->reopenOrder($annulee, 'reprise du dossier après accord client');

    $traces = CommercialValidation::where('document_type', 'order')
        ->whereIn('document_id', [$commande->id, $annulee->id])->get();

    $approbation = $traces->firstWhere('nouveau_statut', 'preparation_approved');
    expect($approbation)->not->toBeNull()
        ->and((int) $approbation->user_id)->toBe($user->id)
        ->and($approbation->motif)->toBe('encours maîtrisé')
        ->and($approbation->metadata)->toHaveKey('contexte_credit');

    $reouverture = $traces->firstWhere('ancien_statut', 'annule');
    expect($reouverture)->not->toBeNull()
        ->and($reouverture->metadata['operation'])->toBe('reopen');

    // 3. Le dépassement d'encours conserve son instantané financier.
    $clientServe = Client::factory()->create(['payment_mode' => Client::PAYMENT_CREDIT, 'credit_limit' => 150_000]);
    $depassement = p2Order('brouillon', $clientServe, 200_000);
    // Le blocage est attendu — mais on vérifie QUE c'est bien le dépassement qui
    // bloque. Avaler n'importe quelle RuntimeException masquerait un refus de
    // permission et laisserait le test vert sans rien prouver.
    expect(fn () => $workflow->submit($depassement))
        ->toThrow(RuntimeException::class, 'approbation exceptionnelle');
    expect($depassement->fresh()->credit_overrun_status)->toBe('pending');
    $workflow->decideCreditOverrun($depassement->fresh(), true, 'garantie reçue');

    $arbitrage = CommercialValidation::where('document_type', 'order')
        ->where('document_id', $depassement->id)
        ->where('nouveau_statut', 'credit_overrun_approved')->first();

    expect($arbitrage)->not->toBeNull()
        ->and((int) $arbitrage->user_id)->toBe($user->id)
        ->and($arbitrage->motif)->toBe('garantie reçue')
        ->and($arbitrage->metadata['contexte_credit']['overrun_amount'])->toBe(50_000);

    expect($co->exists)->toBeTrue();
});
