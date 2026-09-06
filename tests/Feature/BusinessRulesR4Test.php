<?php

/**
 * [R4] Règles métier du passage commande → bon de préparation → production.
 *
 * Ce fichier prouve, sur les services réels, les règles que l'audit R4 avait
 * trouvées absentes ou mortes :
 *
 *  - R4.2 comptant : pas de bon de préparation sans encaissement intégral ;
 *  - R4.3 acompte  : seuil canonique, pas de passe-droit sous le seuil ;
 *  - R4.4 crédit   : approbation hiérarchique obligatoire — la validation
 *                    commerciale n'émet plus le bon toute seule ;
 *  - R4.5 dépassement d'encours : blocage rattrapable par une approbation
 *                    exceptionnelle, invalidée si la commande change ensuite ;
 *  - R4.6 tôles bac : identification par catégorie de gestion, pas par une
 *                    liste de familles inexistantes en base.
 *
 * Les montants sont en FCFA entiers, comme partout dans le domaine.
 */

use App\Exceptions\CreditLimitExceededException;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\ItemCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\SalesSetting;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\Sales\PreparationEligibilityService;
use Illuminate\Support\Facades\DB;

uses(\Tests\Concerns\RefreshDatabase::class);

// ───────────────────────────── Montage commun ─────────────────────────────

function r4Company(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'R4-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true],
    );
    $co = Company::firstOrCreate(
        ['name' => 'R4 Co'],
        ['email' => 'r4@oa-metal.test', 'current_fiscal_year_id' => $fy->id],
    );
    app()->instance('current_company', $co);

    return $co;
}

/** Utilisateur authentifié porteur des permissions demandées. */
function r4User(array $permissions = []): User
{
    $user = User::factory()->create();

    // `sales.bypass_self_validation` est interrogée par le trait de workflow
    // (auto-validation) : Spatie lève PermissionDoesNotExist si elle n'existe
    // pas en base, même pour un utilisateur qui ne l'a pas. Elle doit donc
    // exister — sans être accordée.
    foreach ([...$permissions, 'sales.bypass_self_validation'] as $nom) {
        \Spatie\Permission\Models\Permission::findOrCreate($nom, 'web');
    }
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    test()->actingAs($user->fresh());

    return $user;
}

function r4Order(string $mode, int $ttc = 1000000, string $statut = 'confirme', array $clientAttrs = []): Order
{
    $co = r4Company();
    $client = Client::factory()->create(array_merge(['payment_mode' => $mode], $clientAttrs));

    return Order::create([
        'company_id' => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id' => $client->id,
        'number' => 'R4-CMD-'.uniqid(),
        'status' => $statut,
        'issued_at' => now(),
        'total_ttc' => $ttc,
        'subtotal_ht' => (int) round($ttc / 1.18),
        'invoiced_amount' => 0,
    ]);
}

/** Encaissement confirmé et alloué — la seule couverture que le domaine accepte. */
function r4Pay(Order $order, int $montant): void
{
    if ($montant <= 0) {
        return;
    }

    $invoice = \App\Models\Invoice::firstOrCreate(
        ['order_id' => $order->id],
        [
            'company_id' => $order->company_id,
            'fiscal_year_id' => $order->fiscal_year_id,
            'client_id' => $order->client_id,
            'number' => 'R4-FA-'.uniqid(),
            'status' => 'emise',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'currency_code' => 'XOF',
            'subtotal_ht' => $order->subtotal_ht,
            'total_ttc' => $order->total_ttc,
            'remaining_amount' => $order->total_ttc,
        ],
    );

    $payment = \App\Models\ClientPayment::create([
        'company_id' => $order->company_id,
        'client_id' => $order->client_id,
        'number' => 'R4-ENC-'.uniqid(),
        'amount' => $montant,
        'net_amount' => $montant,
        'payment_date' => now(),
        'status' => 'confirme',
        'allocated_amount' => $montant,
        'unallocated_amount' => 0,
    ]);

    \App\Models\ClientPaymentAllocation::create([
        'client_payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => $montant,
        'allocated_at' => now(),
    ]);
}

function r4Eligibility(Order $order): \App\Services\Sales\PreparationEligibility
{
    return app(PreparationEligibilityService::class)->evaluate($order->fresh());
}

// ─────────────── R4.2 — Comptant : encaissement intégral requis ───────────────

it('R4.2 — une commande comptant non réglée n ouvre pas de bon de préparation', function () {
    $order = r4Order(Client::PAYMENT_CASH, 1000000);

    $eligibilite = r4Eligibility($order);

    expect($eligibilite->allowed)->toBeFalse()
        ->and($eligibilite->requiredAmount)->toBe(1000000)
        ->and($eligibilite->coveredAmount)->toBe(0)
        ->and($eligibilite->missingAmount())->toBe(1000000);
});

it('R4.2 — un règlement partiel ne suffit pas au comptant', function () {
    $order = r4Order(Client::PAYMENT_CASH, 1000000);
    r4Pay($order, 999999);

    expect(r4Eligibility($order)->allowed)->toBeFalse()
        ->and(r4Eligibility($order)->missingAmount())->toBe(1);
});

it('R4.2 — le règlement intégral ouvre le bon de préparation', function () {
    $order = r4Order(Client::PAYMENT_CASH, 1000000);
    r4Pay($order, 1000000);

    $eligibilite = r4Eligibility($order);

    expect($eligibilite->allowed)->toBeTrue()
        ->and($eligibilite->missingAmount())->toBe(0)
        ->and($eligibilite->awaitingApproval)->toBeFalse();
});

it('R4.2 — le service de bon de préparation refuse une couverture insuffisante', function () {
    $order = r4Order(Client::PAYMENT_CASH, 1000000);
    r4User(['payments.create']);

    expect(fn () => app(\App\Services\BonPreparationService::class)
        ->createForCashOrder($order, 400000))
        ->toThrow(RuntimeException::class);

    expect($order->fresh()->hasBonPreparation())->toBeFalse();
});

// ─────────────────── R4.3 — Acompte : seuil canonique ───────────────────

it('R4.3 — sous le seuil d acompte, le bon de préparation reste fermé', function () {
    r4Company();
    $setting = SalesSetting::current();
    $setting->deposit_required_rate = 30;
    $setting->save();

    $order = r4Order(Client::PAYMENT_DEPOSIT, 1000000);
    r4Pay($order, 299999);

    $eligibilite = r4Eligibility($order);

    expect($eligibilite->requiredAmount)->toBe(300000)
        ->and($eligibilite->allowed)->toBeFalse()
        ->and($eligibilite->missingAmount())->toBe(1);
});

it('R4.3 — au seuil exact d acompte, le bon de préparation s ouvre', function () {
    r4Company();
    $setting = SalesSetting::current();
    $setting->deposit_required_rate = 30;
    $setting->save();

    $order = r4Order(Client::PAYMENT_DEPOSIT, 1000000);
    r4Pay($order, 300000);

    expect(r4Eligibility($order)->allowed)->toBeTrue();
});

// ──────────── R4.4 — Crédit : approbation hiérarchique obligatoire ────────────

it('R4.4 — un client à crédit attend une approbation, jamais un bon automatique', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 1000000, 'en_attente_validation', ['credit_limit' => 10000000]);
    $user = r4User(['sales.validate']);

    app(CommercialWorkflowService::class)->validateOrder($order, 'validation commerciale');

    $order = $order->fresh();

    expect($order->status)->toBe('confirme')
        ->and($order->hasBonPreparation())->toBeFalse()
        ->and($order->preparation_approval_status)->toBe(PreparationEligibilityService::APPROVAL_PENDING)
        ->and((int) $order->preparation_requested_by)->toBe($user->id)
        ->and($order->preparation_approval_context)->toHaveKey('credit_limit');

    $eligibilite = r4Eligibility($order);
    expect($eligibilite->allowed)->toBeFalse()
        ->and($eligibilite->awaitingApproval)->toBeTrue();
});

it('R4.4 — l approbation du responsable émet le bon de préparation', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 1000000, 'en_attente_validation', ['credit_limit' => 10000000]);
    r4User(['sales.validate', 'bon_preparations.validate']);
    $workflow = app(CommercialWorkflowService::class);

    $workflow->validateOrder($order, 'validation commerciale');
    $decidee = $workflow->decidePreparationApproval($order->fresh(), true, 'client fidèle, encours maîtrisé');

    expect($decidee->preparation_approval_status)->toBe(PreparationEligibilityService::APPROVAL_APPROVED)
        ->and($decidee->hasBonPreparation())->toBeTrue()
        ->and(r4Eligibility($decidee)->allowed)->toBeTrue();
});

it('R4.4 — le refus laisse la commande sans bon de préparation', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 1000000, 'en_attente_validation', ['credit_limit' => 10000000]);
    r4User(['sales.validate', 'bon_preparations.validate']);
    $workflow = app(CommercialWorkflowService::class);

    $workflow->validateOrder($order, 'validation commerciale');
    $decidee = $workflow->decidePreparationApproval($order->fresh(), false, 'encours déjà tendu');

    expect($decidee->preparation_approval_status)->toBe(PreparationEligibilityService::APPROVAL_REJECTED)
        ->and($decidee->hasBonPreparation())->toBeFalse()
        ->and(r4Eligibility($decidee)->allowed)->toBeFalse();
});

it('R4.4 — une décision déjà prise ne peut pas être rejouée', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 1000000, 'en_attente_validation', ['credit_limit' => 10000000]);
    r4User(['sales.validate', 'bon_preparations.validate']);
    $workflow = app(CommercialWorkflowService::class);

    $workflow->validateOrder($order, 'validation commerciale');
    $workflow->decidePreparationApproval($order->fresh(), true, 'accord');

    expect(fn () => $workflow->decidePreparationApproval($order->fresh(), false, 'revirement'))
        ->toThrow(RuntimeException::class, "Aucune demande d'approbation en attente");
});

it('R4.4 — la décision est tracée dans le journal commercial', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 1000000, 'en_attente_validation', ['credit_limit' => 10000000]);
    $user = r4User(['sales.validate', 'bon_preparations.validate']);
    $workflow = app(CommercialWorkflowService::class);

    $workflow->validateOrder($order, 'validation commerciale');
    $workflow->decidePreparationApproval($order->fresh(), true, 'accord direction');

    $trace = DB::table('commercial_validations')
        ->where('document_type', 'order')->where('document_id', $order->id)
        ->where('nouveau_statut', 'preparation_approved')->first();

    expect($trace)->not->toBeNull()
        ->and((int) $trace->user_id)->toBe($user->id)
        ->and($trace->motif)->toBe('accord direction');
});

// ────────── R4.5 — Dépassement d encours : blocage non terminal ──────────

it('R4.5 — le dépassement ouvre une demande d approbation au lieu d un refus définitif', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 200000, 'brouillon', ['credit_limit' => 150000]);
    r4User(['sales.submit']);

    expect(fn () => app(CommercialWorkflowService::class)->submit($order))
        ->toThrow(RuntimeException::class, 'approbation exceptionnelle');

    $order = $order->fresh();

    expect($order->status)->toBe('brouillon')
        ->and($order->credit_overrun_status)->toBe('pending')
        ->and($order->credit_overrun_context['overrun_amount'])->toBe(50000)
        ->and($order->credit_overrun_context['credit_limit'])->toBe(150000);
});

it('R4.5 — l approbation exceptionnelle débloque la soumission', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 200000, 'brouillon', ['credit_limit' => 150000]);
    r4User(['sales.submit', 'sales_credit_overrun.approve']);
    $workflow = app(CommercialWorkflowService::class);

    try {
        $workflow->submit($order);
    } catch (RuntimeException) {
        // blocage attendu — la demande d'approbation est ouverte
    }

    $workflow->decideCreditOverrun($order->fresh(), true, 'garantie bancaire reçue');
    $workflow->submit($order->fresh());

    expect($order->fresh()->status)->not->toBe('brouillon');
});

it('R4.5 — le refus laisse la commande bloquée', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 200000, 'brouillon', ['credit_limit' => 150000]);
    r4User(['sales.submit', 'sales_credit_overrun.approve']);
    $workflow = app(CommercialWorkflowService::class);

    try {
        $workflow->submit($order);
    } catch (RuntimeException) {
    }

    $workflow->decideCreditOverrun($order->fresh(), false, 'encours non couvert');

    expect($order->fresh()->credit_overrun_status)->toBe('rejected')
        ->and($order->fresh()->hasValidCreditOverrunApproval())->toBeFalse();

    expect(fn () => $workflow->submit($order->fresh()))->toThrow(RuntimeException::class);
});

it('R4.5 — une commande modifiée après approbation perd sa dérogation', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 200000, 'brouillon', ['credit_limit' => 150000]);
    r4User(['sales.submit', 'sales_credit_overrun.approve']);
    $workflow = app(CommercialWorkflowService::class);

    try {
        $workflow->submit($order);
    } catch (RuntimeException) {
    }
    $workflow->decideCreditOverrun($order->fresh(), true, 'accord ponctuel sur 200 000');

    expect($order->fresh()->hasValidCreditOverrunApproval())->toBeTrue();

    $order->forceFill(['total_ttc' => 900000])->save();

    expect($order->fresh()->hasValidCreditOverrunApproval())->toBeFalse();
});

it('R4.5 — le contrôle de crédit lève une exception typée porteuse de l exposition', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 200000, 'brouillon', ['credit_limit' => 150000]);

    try {
        DB::transaction(fn () => app(\App\Services\CustomerCreditExposureService::class)->assertMaySubmit($order));
        $this->fail('Le dépassement aurait dû être refusé.');
    } catch (CreditLimitExceededException $e) {
        expect($e->overrunAmount())->toBe(50000)
            ->and($e->exposure['limit'])->toBe(150000);
    }
});

it('R4.5 — arbitrer sans demande ouverte est refusé', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 100000, 'brouillon', ['credit_limit' => 150000]);
    r4User(['sales_credit_overrun.approve']);

    expect(fn () => app(CommercialWorkflowService::class)->decideCreditOverrun($order, true, 'sans objet'))
        ->toThrow(RuntimeException::class, "Aucun dépassement d'encours en attente");
});

it('R4.5 — arbitrer sans la permission est refusé', function () {
    $order = r4Order(Client::PAYMENT_CREDIT, 100000, 'brouillon', ['credit_limit' => 150000]);
    r4User([]);

    expect(fn () => app(CommercialWorkflowService::class)->decideCreditOverrun($order, true, 'tentative'))
        ->toThrow(RuntimeException::class, 'sales_credit_overrun.approve');
});

// ───────────── R4.6 — Tôles bac : catégorie de gestion, pas famille ─────────────

function r4Categorie(string $code): ItemCategory
{
    return ItemCategory::create([
        'company_id' => r4Company()->id,
        'code' => $code,
        'name' => 'Catégorie '.$code,
        'nature' => 'produit_fini',
        'strategy' => 'mto',
        'is_active' => true,
        'is_stockable' => true,
    ]);
}

it('R4.6 — la portée tôle bac suit la catégorie de gestion PF_TOLE_MTO', function () {
    r4Company();
    $categorie = r4Categorie(Product::CATEGORIE_TOLE_BAC);
    $autre = r4Categorie('PF_AUTRE');

    $tole = Product::factory()->create(['item_category_id' => $categorie->id]);
    $hors = Product::factory()->create(['item_category_id' => $autre->id]);

    $ids = Product::query()->toleBac()->pluck('id');

    expect($ids)->toContain($tole->id)
        ->and($ids)->not->toContain($hors->id);
});

it('R4.6 — les articles historiques sans catégorie restent reconnus par leur famille', function () {
    r4Company();
    $famille = ProductFamily::create([
        'name' => 'Tôles bac historiques',
        'code' => Product::FAMILLES_TOLE_BAC_LEGACY[0],
    ]);
    $legacy = Product::factory()->create(['item_category_id' => null, 'family_id' => $famille->id]);

    expect(Product::query()->toleBac()->pluck('id'))->toContain($legacy->id);
});
