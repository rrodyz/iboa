<?php

namespace App\Services;

use App\Events\OrderConfirmed;
use App\Models\Client;
use App\Models\CommercialValidation;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
use App\Modules\Production\Services\ProductionDeliveryGuard;
use App\Modules\Production\Services\ReservationService;
use App\Exceptions\CreditLimitExceededException;
use App\Notifications\ValidationStepNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service centralisé pour les transitions de statut des documents commerciaux.
 *
 * Toute la logique métier de validation est ici — les controllers appellent
 * ce service, jamais les méthodes du trait directement (sauf pour lire l'état).
 */
class CommercialWorkflowService
{
    public function __construct(
        private DeliveryNoteService $deliveryNoteService,
        private InvoiceService $invoiceService,
        private CreditNoteService $creditNoteService,
        private BonPreparationService $bonPrepService,
        private CustomerCreditExposureService $creditExposureService,
    ) {}

    // ── Soumission ────────────────────────────────────────────────────────────

    /**
     * Soumet un document à validation.
     *
     * @param  Quote|Order|DeliveryNote|Invoice|CreditNote  $document
     *
     * @throws \RuntimeException
     */
    public function submit(mixed $document, ?string $motif = null): void
    {
        $this->assertPermission('sales.submit');

        if ($document instanceof Order || $document instanceof Quote) {
            // [D2 — défense en profondeur] Contrôlé EN PREMIER, avant le prix
            // plancher et le crédit. Le contrôle posé à la saisie ne dispense pas
            // de celui-ci : un article peut devenir incomplet après coup. Et un
            // document dont un article n'a pas de stratégie d'approvisionnement
            // n'a pas à être évalué plus loin — le refus qui suivrait porterait
            // sur un autre motif et masquerait la vraie cause.
            $resolver = app(\App\Services\Sales\FulfillmentStrategyResolver::class);
            $resolver->assertLines($resolver->lignesDe($document));

            app(SalesFloorWaiverService::class)->assertDocumentMayProceed($document);
        }

        if ($document instanceof Order) {
            $soumission = function () use ($document, $motif) {
                // [Ventes §3] ORDRE DE VERROUILLAGE GLOBAL : client D'ABORD, commandes ensuite.
                //
                // Cet ordre n'est pas cosmétique. Le contrôle de crédit lit les commandes
                // ouvertes du client en lecture verrouillante : il verrouille donc des
                // lignes de `orders` APRÈS avoir pris le verrou client. Si la commande
                // courante était verrouillée AVANT le client, deux soumissions concurrentes
                // sur le même client formaient une attente circulaire —
                // « SQLSTATE[40001] Deadlock found », observé en course réelle.
                Client::query()->whereKey($document->client_id)->lockForUpdate()->firstOrFail();

                $fresh = Order::lockForUpdate()->findOrFail($document->id);
                if (! $fresh->isSubmittable()) {
                    throw new \RuntimeException("Cette commande a déjà été soumise (statut : {$fresh->status}).");
                }

                // [R4.5] Une dérogation de dépassement accordée ET toujours valide
                // (contrat financier inchangé) remplace le contrôle : le responsable
                // a déjà tranché sur exactement ce montant. Sinon le contrôle
                // s'applique et lève CreditLimitExceededException.
                if (! $fresh->hasValidCreditOverrunApproval()) {
                    $this->creditExposureService->assertMaySubmit($fresh);
                }
                $fresh->submit($motif);

                return $fresh->fresh('client');
            };

            try {
                $document = DB::transaction($soumission);
            } catch (CreditLimitExceededException $e) {
                // La demande est ouverte APRÈS le rollback, hors transaction :
                // écrite à l'intérieur, elle disparaîtrait avec l'annulation et le
                // blocage redeviendrait terminal — exactement ce que R4.5 interdit.
                // La commande, elle, reste non soumise tant que nul n'a tranché.
                $this->requestCreditOverrunApproval($document->fresh(), $e);

                throw new \RuntimeException($e->getMessage()
                    .' Une demande d\'approbation exceptionnelle a été ouverte : un responsable'
                    .' doit l\'accorder avant que la commande puisse être soumise.');
            }
        } else {
            $document->submit($motif);
        }

        // [CDC §13.1/§17] Document soumis → notifie exactement le rôle qui valide
        // ce type de document, jamais un envoi large. Message enrichi :
        // demandeur + client + montant pour décider sans ouvrir le document.
        $demandeur = Auth::user()?->name ?? 'Utilisateur';
        $client = $document->client?->name ?? Client::find($document->client_id ?? 0)?->name;
        $montant = isset($document->total_ttc)
            ? number_format((int) $document->total_ttc, 0, ',', ' ').' FCFA'
            : null;
        $contexte = implode(' · ', array_filter([
            $demandeur ? "par {$demandeur}" : null,
            $client ? "client {$client}" : null,
            $montant,
        ]));

        match (true) {
            $document instanceof Order => ValidationStepNotification::sendToRoles(
                ['comptable', 'daf'],
                title: 'Validation financière requise',
                message: "Commande {$document->number} soumise {$contexte} — validation Finance en attente.",
                url: route('ventes.commandes.show', $document),
                modelType: 'Order', modelId: $document->id, type: 'order_submitted',
            ),
            $document instanceof Quote => ValidationStepNotification::sendToRoles(
                ['responsable_commercial'],
                title: 'Devis à valider',
                message: "Devis {$document->number} soumis {$contexte} — validation requise.",
                url: route('ventes.devis.show', $document),
                modelType: 'Quote', modelId: $document->id, type: 'quote_submitted',
            ),
            $document instanceof DeliveryNote => ValidationStepNotification::sendToRoles(
                ['responsable_commercial'],
                title: 'Bon de livraison à valider',
                message: "BL {$document->number} soumis {$contexte} — validation requise avant expédition.",
                url: route('ventes.bons-livraison.show', $document),
                modelType: 'DeliveryNote', modelId: $document->id, type: 'delivery_note_submitted',
            ),
            $document instanceof Invoice => ValidationStepNotification::sendToRoles(
                ['comptable'],
                title: 'Facture à valider',
                message: "Facture {$document->number} soumise {$contexte} — validation comptable requise.",
                url: route('ventes.factures.show', $document),
                modelType: 'Invoice', modelId: $document->id, type: 'invoice_submitted',
            ),
            $document instanceof CreditNote => ValidationStepNotification::sendToRoles(
                ['comptable'],
                title: 'Avoir à valider',
                message: "Avoir {$document->number} soumis {$contexte} — validation comptable requise.",
                url: route('ventes.avoirs.show', $document),
                modelType: 'CreditNote', modelId: $document->id, type: 'credit_note_submitted',
            ),
            default => null,
        };
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Valide un devis.
     *
     * @throws \RuntimeException
     */
    public function validateQuote(Quote $quote, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        app(SalesFloorWaiverService::class)->assertDocumentMayProceed($quote);
        $quote->assertCanValidate();
        $quote->validateDocument('valide', $motif);

        $this->notifyCreatorOfValidation($quote, 'Devis validé', "Devis {$quote->number} validé — prêt à transmettre au client.", route('ventes.devis.show', $quote), 'Quote');
    }

    /**
     * Valide une commande (validation financière — en_attente_validation → confirme).
     * Déclenche OrderConfirmed, identique au circuit direct OrderService::confirm() :
     * réservation stock (ReserveStockOnOrderConfirmed).
     *
     * [R4.7] La confirmation ne crée plus d'ordre de fabrication : l'OF
     * automatique MTO attend ProductionAuthorized — bon de préparation émis ou
     * dérogation gérant.
     *
     * @throws \RuntimeException
     */
    public function validateOrder(Order $order, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        app(SalesFloorWaiverService::class)->assertDocumentMayProceed($order);
        $order->assertCanValidate();

        DB::transaction(function () use ($order, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double validation.
            $fresh = Order::lockForUpdate()->findOrFail($order->id);
            if (! $fresh->isValidatable()) {
                throw new \RuntimeException("Cette commande a déjà été traitée (statut : {$fresh->status}).");
            }
            $fresh->validateDocument('confirme', $motif);

            event(new OrderConfirmed($fresh->fresh()));
        });

        // [CDC §13.1] Validation financière obtenue → Chef Production génère l'OF.
        $fresh = $order->fresh();
        ValidationStepNotification::sendToRoles(
            ['chef_production', 'directeur_usine'],
            title: 'Commande confirmée — OF à générer',
            message: "Commande {$fresh->number} validée financièrement — ordre de fabrication à générer.",
            url: route('ventes.commandes.show', $fresh),
            modelType: 'Order',
            modelId: $fresh->id,
            type: 'order_validated',
            icon: 'cog',
            color: 'blue',
        );

        // [R4.4] Commande à crédit : le bon de préparation n'est PLUS créé
        // automatiquement. La validation commerciale ouvre une demande
        // d'approbation hiérarchique ; seul un responsable
        // (permission `bon_preparations.validate`) la transforme en bon de
        // préparation — donc en autorisation de chargement et en visibilité
        // production. Le contexte de crédit est figé au moment de la demande :
        // une décision prise plus tard doit rester justifiable.
        $client = $fresh->client ?? Client::find($fresh->client_id);
        if ($client && $client->isCredit() && ! $fresh->hasBonPreparation()) {
            $this->requestPreparationApproval($fresh, $client);
        }
    }

    /**
     * [R4.4/R4.5/R4.18] Ouvre (ou laisse ouverte) la demande d'approbation du
     * passage en bon de préparation, avec le contexte financier du moment.
     */
    public function requestPreparationApproval(Order $order, ?Client $client = null): void
    {
        if (in_array($order->preparation_approval_status, [
            \App\Services\Sales\PreparationEligibilityService::APPROVAL_PENDING,
            \App\Services\Sales\PreparationEligibilityService::APPROVAL_APPROVED,
        ], true)) {
            return; // demande déjà ouverte ou déjà tranchée favorablement
        }

        $client ??= $order->client;
        $exposition = $client
            ? app(\App\Services\CustomerCreditExposureService::class)->assess($order)
            : [];

        $order->forceFill([
            'preparation_approval_status' => \App\Services\Sales\PreparationEligibilityService::APPROVAL_PENDING,
            'preparation_requested_by'    => Auth::id(),
            'preparation_requested_at'    => now(),
            'preparation_approval_context' => [
                'credit_limit'      => $exposition['limit'] ?? null,
                'current_exposure'  => $exposition['outstanding'] ?? null,
                'order_amount'      => (int) $order->total_ttc,
                'projected_exposure'=> $exposition['projected'] ?? null,
                'overrun_amount'    => isset($exposition['projected'], $exposition['limit']) && ($exposition['limited'] ?? false)
                    ? max(0, (int) $exposition['projected'] - (int) $exposition['limit'])
                    : 0,
            ],
        ])->save();

        ValidationStepNotification::sendToRoles(
            ['responsable_commercial', 'daf', 'directeur'],
            title: 'Passage en préparation à approuver',
            message: "Commande {$order->number} (client à crédit) — approbation requise avant bon de préparation.",
            url: route('ventes.commandes.show', $order),
            modelType: 'Order',
            modelId: $order->id,
            type: 'preparation_approval_requested',
            icon: 'clipboard-document-check',
            color: 'amber',
        );
    }

    /**
     * [R4.4/R4.5/R4.18] Décision du responsable sur le passage en préparation.
     *
     * L'approbation crée le bon de préparation dans la MÊME transaction : une
     * décision approuvée sans bon émis laisserait la commande dans un état
     * intermédiaire invérifiable. La trace (auteur, motif, contexte crédit figé)
     * part dans le journal commercial, jamais seulement dans un log applicatif.
     */
    public function decidePreparationApproval(Order $order, bool $approuve, ?string $motif = null): Order
    {
        return DB::transaction(function () use ($order, $approuve, $motif) {
            $verrouillee = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($verrouillee->preparation_approval_status !== \App\Services\Sales\PreparationEligibilityService::APPROVAL_PENDING) {
                throw new \RuntimeException('Aucune demande d\'approbation en attente sur cette commande.');
            }

            $statut = $approuve
                ? \App\Services\Sales\PreparationEligibilityService::APPROVAL_APPROVED
                : \App\Services\Sales\PreparationEligibilityService::APPROVAL_REJECTED;

            $verrouillee->forceFill([
                'preparation_approval_status' => $statut,
                'preparation_approved_by'     => Auth::id(),
                'preparation_approved_at'     => now(),
                'preparation_approval_reason' => $motif,
            ])->save();

            $verrouillee->logWorkflowAction(
                $approuve ? CommercialValidation::ACTION_VALIDATION : CommercialValidation::ACTION_REFUS,
                'preparation_pending',
                $approuve ? 'preparation_approved' : 'preparation_rejected',
                $motif,
                ['contexte_credit' => $verrouillee->preparation_approval_context],
            );

            if ($approuve && ! $verrouillee->hasBonPreparation()) {
                $this->bonPrepService->createForCreditOrder($verrouillee);
            }

            return $verrouillee->fresh();
        });
    }

    /**
     * [R4.1/R4.18] Réouverture d'une commande annulée par un responsable.
     *
     * Deux exigences, et elles ne sont pas décoratives : la permission
     * `orders.reopen` (le commercial qui a vu sa commande annulée ne se la
     * rouvre pas lui-même) et un motif écrit. Une commande annulée puis remise
     * en circulation sans justification est indistinguable d'une manipulation ;
     * la décision part donc dans le journal commercial avec son auteur, son
     * horodatage et les deux statuts encadrant la transition.
     *
     * La commande revient en BROUILLON, jamais directement en confirmée : elle
     * doit reparcourir la soumission et la validation, donc repasser sous les
     * contrôles de prix plancher et d'encours.
     */
    public function reopenOrder(Order $order, ?string $motif = null): Order
    {
        $this->assertPermission('orders.reopen');

        $motif = trim((string) $motif);
        if (mb_strlen($motif) < 5) {
            throw new \RuntimeException('Un motif d\'au moins 5 caractères est obligatoire pour rouvrir une commande annulée.');
        }

        return DB::transaction(function () use ($order, $motif) {
            $verrouillee = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($verrouillee->status !== 'annule') {
                throw new \RuntimeException("Seules les commandes annulées peuvent être réouvertes (statut : {$verrouillee->status}).");
            }

            $ancienStatut = $verrouillee->status;
            $verrouillee->update(['status' => 'brouillon', 'rejection_reason' => null]);

            $verrouillee->logWorkflowAction(
                CommercialValidation::ACTION_VALIDATION,
                $ancienStatut,
                'brouillon',
                $motif,
                [
                    'operation'     => 'reopen',
                    'approved_by'   => Auth::id(),
                    'approved_at'   => now()->toIso8601String(),
                    'requested_by'  => Auth::id(),
                    'requested_at'  => now()->toIso8601String(),
                    'old_status'    => $ancienStatut,
                    'new_status'    => 'brouillon',
                ],
            );

            return $verrouillee->fresh();
        });
    }

    /**
     * [R4.5] Ouvre la demande d'approbation exceptionnelle d'un dépassement
     * d'encours, avec l'exposition figée au moment du refus.
     *
     * Idempotent : une demande déjà en attente n'est pas réécrite (l'auteur et
     * l'heure d'origine sont ce qui sera audité), et une dérogation déjà
     * accordée n'est pas ré-ouverte. Un refus antérieur, en revanche, rouvre :
     * la commande a pu être renégociée entre-temps.
     */
    public function requestCreditOverrunApproval(Order $order, CreditLimitExceededException $e): void
    {
        if ($order->credit_overrun_status === 'pending' || $order->hasValidCreditOverrunApproval()) {
            return;
        }

        $order->forceFill([
            'credit_overrun_status'       => 'pending',
            'credit_overrun_requested_by' => Auth::id(),
            'credit_overrun_requested_at' => now(),
            'credit_overrun_approved_by'  => null,
            'credit_overrun_approved_at'  => null,
            'credit_overrun_reason'       => null,
            'credit_overrun_fingerprint'  => null,
            'credit_overrun_context'      => [
                'credit_limit'       => (int) ($e->exposure['limit'] ?? 0),
                'current_exposure'   => (int) ($e->exposure['outstanding'] ?? 0),
                'open_orders'        => (int) ($e->exposure['open_orders'] ?? 0),
                'deposits'           => (int) ($e->exposure['deposits'] ?? 0),
                'order_amount'       => (int) ($e->exposure['new_order'] ?? $order->total_ttc),
                'projected_exposure' => (int) ($e->exposure['projected'] ?? 0),
                'overrun_amount'     => $e->overrunAmount(),
            ],
        ])->save();

        ValidationStepNotification::sendToRoles(
            ['responsable_commercial', 'daf', 'directeur'],
            title: 'Dépassement d\'encours à arbitrer',
            message: sprintf(
                'Commande %s — dépassement de %s FCFA du plafond client. Approbation exceptionnelle requise.',
                $order->number,
                number_format($e->overrunAmount(), 0, ',', ' '),
            ),
            url: route('ventes.commandes.show', $order),
            modelType: 'Order',
            modelId: $order->id,
            type: 'credit_overrun_requested',
            icon: 'exclamation-triangle',
            color: 'red',
        );
    }

    /**
     * [R4.5/R4.18] Décision du responsable sur un dépassement d'encours.
     *
     * L'approbation grave l'empreinte du contrat financier : elle couvre CE
     * montant sur CE client, pas la commande indéfiniment. Toute retouche
     * ultérieure (lignes, remise, client, mode de règlement) invalide la
     * dérogation et ramène la commande sous le contrôle de plafond.
     */
    public function decideCreditOverrun(Order $order, bool $approuve, ?string $motif = null): Order
    {
        $this->assertPermission('sales_credit_overrun.approve');

        return DB::transaction(function () use ($order, $approuve, $motif) {
            $verrouillee = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($verrouillee->credit_overrun_status !== 'pending') {
                throw new \RuntimeException('Aucun dépassement d\'encours en attente d\'arbitrage sur cette commande.');
            }

            $verrouillee->forceFill([
                'credit_overrun_status'      => $approuve ? 'approved' : 'rejected',
                'credit_overrun_approved_by' => Auth::id(),
                'credit_overrun_approved_at' => now(),
                'credit_overrun_reason'      => $motif,
                'credit_overrun_fingerprint' => $approuve ? $verrouillee->productionFinancialFingerprint() : null,
            ])->save();

            $verrouillee->logWorkflowAction(
                $approuve ? CommercialValidation::ACTION_VALIDATION : CommercialValidation::ACTION_REFUS,
                'credit_overrun_pending',
                $approuve ? 'credit_overrun_approved' : 'credit_overrun_rejected',
                $motif,
                ['contexte_credit' => $verrouillee->credit_overrun_context],
            );

            return $verrouillee->fresh();
        });
    }

    /**
     * Valide un bon de livraison.
     * Applique également les mouvements de sortie de stock, la libération des
     * réservations et la mise à jour du statut de la commande parente.
     *
     * @throws \RuntimeException
     */
    public function validateDeliveryNote(DeliveryNote $dn, ?string $motif = null, ?string $derogationQualite = null): void
    {
        $this->assertPermission('sales.validate');
        $dn->assertCanValidate();

        // [MTO §15] Qualité et quantité CONFORME, article par article. `$motif` est
        // le motif de WORKFLOW : le réutiliser ici ferait d'une note de validation
        // ordinaire une acceptation de risque qualité. D'où un paramètre séparé.
        app(ProductionDeliveryGuard::class)->assertDeliverable($dn, $derogationQualite);

        DB::transaction(function () use ($dn, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double
            // validation (double-clic) → double sortie de stock.
            $dn = DeliveryNote::lockForUpdate()->findOrFail($dn->id);
            if (! $dn->isValidatable()) {
                throw new \RuntimeException("Ce document a déjà été traité (statut : {$dn->status}).");
            }
            $dn->validateDocument('valide', $motif);
            // [FIX-BL-STOCK] Créer les mouvements de sortie de stock après validation interne.
            $this->deliveryNoteService->applyStockOut($dn->fresh());
        });

        $fresh = $dn->fresh(['order.invoices']);

        // [CDC §bon-livraison] BL validé → génère automatiquement la facture
        // (si la commande n'a pas déjà une facture active).
        $order = $fresh->order;
        if ($order && ! $order->invoices()->whereIn('status', ['en_attente_validation', 'emise', 'envoyee', 'partiellement_payee', 'payee'])->exists()) {
            try {
                $this->invoiceService->createFromDeliveryNote($fresh);
            } catch (\Throwable) {
                // Ne bloque pas la validation BL si la création facture échoue
                // (ex: BL partiel — InvoiceService gère ce cas)
            }
        }

        // [CDC §13 Livraison] BL validé, stock sorti → notifie le commercial créateur,
        // pas tout le rôle — c'est lui qui suit ce client.
        if ($fresh->createdBy) {
            $fresh->createdBy->notify(new ValidationStepNotification(
                title: 'Livraison expédiée',
                message: "BL {$fresh->number} validé — marchandise sortie de stock.",
                url: route('ventes.bons-livraison.show', $fresh),
                modelType: 'DeliveryNote', modelId: $fresh->id,
                type: 'delivery_note_validated', icon: 'truck', color: 'green',
            ));
        }
    }

    /**
     * Valide une facture.
     * Applique également la comptabilisation GL, la sortie de stock et l'événement
     * InvoiceValidated — identique au circuit direct InvoiceService::validate().
     *
     * @throws \RuntimeException
     */
    public function validateInvoice(Invoice $invoice, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $invoice->assertCanValidate();

        DB::transaction(function () use ($invoice, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double
            // validation (double-clic) → double comptabilisation GL + double sortie stock.
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->isValidatable()) {
                throw new \RuntimeException("Cette facture a déjà été traitée (statut : {$invoice->status}).");
            }

            // Définir due_at si manquant avant de valider
            if (! $invoice->due_at) {
                $invoice->update([
                    'due_at' => now()->addDays(30)->toDateString(),
                ]);
            }

            $invoice->validateDocument('emise', $motif);

            // [FIX-FA-COMPTA] Appliquer les effets secondaires comptables/stock/événement.
            $this->invoiceService->applyValidationSideEffects($invoice->fresh(['client', 'company']));
        });

        $fresh = $invoice->fresh();
        $this->notifyCreatorOfValidation($fresh, 'Facture validée', "Facture {$fresh->number} validée — comptabilisée.", route('ventes.factures.show', $fresh), 'Invoice');
    }

    /**
     * Valide un avoir.
     * Applique également la comptabilisation GL, le retour de stock et l'événement
     * CreditNoteValidated — identique au circuit direct CreditNoteService::validate().
     *
     * @throws \RuntimeException
     */
    public function validateCreditNote(CreditNote $cn, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $cn->assertCanValidate();

        DB::transaction(function () use ($cn, $motif) {
            // [CONCURRENCE] Verrou + re-check statut frais : empêche la double
            // validation (double-clic) → double comptabilisation GL + double retour stock.
            $cn = CreditNote::lockForUpdate()->findOrFail($cn->id);
            if (! $cn->isValidatable()) {
                throw new \RuntimeException("Cet avoir a déjà été traité (statut : {$cn->status}).");
            }
            $cn->validateDocument('valide', $motif);
            // [FIX-AVOIR-COMPTA] Appliquer les effets secondaires comptables/stock/événement.
            $this->creditNoteService->applyValidationSideEffects($cn->fresh(['client', 'company']));
        });

        $fresh = $cn->fresh();
        $this->notifyCreatorOfValidation($fresh, 'Avoir validé', "Avoir {$fresh->number} validé — comptabilisé.", route('ventes.avoirs.show', $fresh), 'CreditNote');
    }

    // ── Refus ─────────────────────────────────────────────────────────────────

    /**
     * Refuse un document (retour en brouillon avec motif).
     *
     * @throws \RuntimeException
     */
    public function reject(mixed $document, string $motif): void
    {
        $this->assertPermission('sales.reject');
        $document->rejectDocument($motif);
    }

    // ── Annulation ────────────────────────────────────────────────────────────

    /**
     * Annule un document avec motif.
     *
     * @throws \RuntimeException
     */
    public function cancel(mixed $document, string $motif): void
    {
        $this->assertPermission('sales.cancel');

        // [Matrice annulations] L'avoir a des effets riches (stock, GL,
        // application facture) : son annulation passe par le service dédié qui
        // inverse tout — un simple changement de statut laissait le stock
        // gonflé, la facture faussée et l'écriture en place.
        if ($document instanceof CreditNote) {
            app(CreditNoteService::class)->cancel($document, $motif);

            return;
        }

        // [Ventes §17] La commande a sa propre implémentation : elle libère les
        // réservations de stock et verrouille la ligne contre une course
        // annulation/confirmation. Déléguer évite d'avoir deux annulations de
        // commande divergentes — le défaut exact que ce lot corrige.
        if ($document instanceof Order) {
            app(OrderService::class)->cancel($document, $motif);

            return;
        }

        // Statut d'annulation selon le type
        $cancelledStatus = match (true) {
            $document instanceof Invoice => 'annulee',
            default => 'annule',
        };

        $document->cancelDocument($cancelledStatus, $motif);

        // La libération des réservations de produit fini a migré dans
        // OrderService::cancel(), à qui les commandes sont désormais déléguées
        // plus haut — ce point n'est plus atteint pour un Order.

        // [SYNC] Annulation d'une facture → recalcule le montant facturé de la commande.
        if ($document instanceof Invoice) {
            Order::resyncInvoicedAmount($document->order_id);
        }
    }

    // ── Dashboard KPIs ────────────────────────────────────────────────────────

    /**
     * Retourne les KPIs du workflow validation pour le dashboard Ventes.
     */
    public function getDashboardKpis(): array
    {
        $companyId = currentCompany()?->id;

        // [Perf] Un COUNT GROUPÉ par table, au lieu d'un COUNT par statut.
        //
        // La version précédente lançait 17 requêtes, dont 5 qui REJOUAIENT à
        // l'identique une requête déjà exécutée quelques lignes plus haut, pour
        // recalculer `total_pending`. Sur le tableau de bord Ventes, cela
        // représentait 12 requêtes strictement redondantes à chaque affichage.
        //
        // Le comptage groupé passe par le modèle : les scopes de société et de
        // suppression logique restent appliqués, la sémantique est inchangée.
        $countByStatus = function (string $modelClass) use ($companyId): array {
            return $modelClass::query()
                ->where('company_id', $companyId)
                ->groupBy('status')
                ->pluck(\Illuminate\Support\Facades\DB::raw('COUNT(*)'), 'status')
                ->map(fn ($n) => (int) $n)
                ->all();
        };

        $quotes = $countByStatus(Quote::class);
        $orders = $countByStatus(Order::class);
        $deliveries = $countByStatus(DeliveryNote::class);
        $invoices = $countByStatus(Invoice::class);
        $creditNotes = $countByStatus(CreditNote::class);

        /** Somme des statuts demandés, absents comptés pour zéro. */
        $sum = fn (array $counts, array $statuses) => array_sum(array_map(
            fn (string $s) => $counts[$s] ?? 0,
            $statuses
        ));

        $quotesPending = $quotes['en_attente_validation'] ?? 0;
        $ordersPending = $orders['en_attente_validation'] ?? 0;
        $deliveriesPending = $deliveries['en_attente_validation'] ?? 0;
        $invoicesPending = $invoices['en_attente_validation'] ?? 0;
        $creditNotesPending = $creditNotes['en_attente_validation'] ?? 0;

        return [
            // Devis
            'quotes_brouillon' => $quotes['brouillon'] ?? 0,
            'quotes_en_attente' => $quotesPending,
            'quotes_envoyes' => $quotes['envoye'] ?? 0,

            // Commandes
            'orders_en_attente' => $ordersPending,
            'orders_confirmes' => $orders['confirme'] ?? 0,

            // Bons de livraison
            'deliveries_en_attente' => $deliveriesPending,
            'deliveries_a_facturer' => $deliveries['valide'] ?? 0,

            // Factures
            'invoices_en_attente' => $invoicesPending,
            'invoices_emises' => $sum($invoices, ['emise', 'envoyee']),
            'invoices_impayees' => $sum($invoices, ['emise', 'envoyee', 'partiellement_payee', 'en_retard']),

            // Avoirs
            'credit_notes_en_attente' => $creditNotesPending,

            // Documents refusés récents (7j)
            'recently_rejected' => CommercialValidation::where('action', 'refus')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),

            // Total en attente toutes catégories — dérivé des compteurs déjà
            // obtenus, plus jamais recalculé par cinq requêtes supplémentaires.
            'total_pending' => $quotesPending + $ordersPending + $deliveriesPending
                + $invoicesPending + $creditNotesPending,
        ];
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * [CDC §13/§17] Confirme au créateur du document que sa demande de
     * validation a abouti — notification ciblée à la personne précise, pas
     * à un rôle entier.
     */
    private function notifyCreatorOfValidation(mixed $document, string $title, string $message, string $url, string $modelType): void
    {
        $creator = $document->createdBy;
        if (! $creator) {
            return;
        }
        $creator->notify(new ValidationStepNotification(
            $title, $message, $url, $modelType, $document->id,
            type: 'document_validated', icon: 'check-circle', color: 'green',
        ));
    }

    /**
     * @throws \RuntimeException si l'utilisateur n'a pas la permission requise.
     */
    private function assertPermission(string $permission): void
    {
        if (! Auth::user()?->can($permission)) {
            throw new \RuntimeException(
                "Vous n'avez pas la permission d'effectuer cette action ({$permission})."
            );
        }
    }
}
