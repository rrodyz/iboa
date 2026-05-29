<?php

namespace App\Services;

use App\Models\CommercialValidation;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Quote;
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
    ) {}

    // ── Soumission ────────────────────────────────────────────────────────────

    /**
     * Soumet un document à validation.
     *
     * @param  Quote|Order|DeliveryNote|Invoice|CreditNote $document
     * @throws \RuntimeException
     */
    public function submit(mixed $document, ?string $motif = null): void
    {
        $this->assertPermission('sales.submit');
        $document->submit($motif);
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
        $quote->assertCanValidate();
        $quote->validateDocument('valide', $motif);
    }

    /**
     * Valide une commande.
     *
     * @throws \RuntimeException
     */
    public function validateOrder(Order $order, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $order->assertCanValidate();
        $order->validateDocument('confirme', $motif);
    }

    /**
     * Valide un bon de livraison.
     * Applique également les mouvements de sortie de stock, la libération des
     * réservations et la mise à jour du statut de la commande parente.
     *
     * @throws \RuntimeException
     */
    public function validateDeliveryNote(DeliveryNote $dn, ?string $motif = null): void
    {
        $this->assertPermission('sales.validate');
        $dn->assertCanValidate();

        DB::transaction(function () use ($dn, $motif) {
            $dn->validateDocument('valide', $motif);
            // [FIX-BL-STOCK] Créer les mouvements de sortie de stock après validation interne.
            $this->deliveryNoteService->applyStockOut($dn->fresh());
        });
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
            // Définir due_at si manquant avant de valider
            if (!$invoice->due_at) {
                $invoice->update([
                    'due_at' => now()->addDays(30)->toDateString(),
                ]);
            }

            $invoice->validateDocument('emise', $motif);

            // [FIX-FA-COMPTA] Appliquer les effets secondaires comptables/stock/événement.
            $this->invoiceService->applyValidationSideEffects($invoice->fresh(['client', 'company']));
        });
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
            $cn->validateDocument('valide', $motif);
            // [FIX-AVOIR-COMPTA] Appliquer les effets secondaires comptables/stock/événement.
            $this->creditNoteService->applyValidationSideEffects($cn->fresh(['client', 'company']));
        });
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

        // Statut d'annulation selon le type
        $cancelledStatus = match (true) {
            $document instanceof Invoice => 'annulee',
            default                      => 'annule',
        };

        $document->cancelDocument($cancelledStatus, $motif);
    }

    // ── Dashboard KPIs ────────────────────────────────────────────────────────

    /**
     * Retourne les KPIs du workflow validation pour le dashboard Ventes.
     */
    public function getDashboardKpis(): array
    {
        $companyId = Company::first()?->id;

        return [
            // Devis
            'quotes_brouillon'         => Quote::where('company_id', $companyId)->where('status', 'brouillon')->count(),
            'quotes_en_attente'        => Quote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'quotes_envoyes'           => Quote::where('company_id', $companyId)->where('status', 'envoye')->count(),

            // Commandes
            'orders_en_attente'        => Order::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'orders_confirmes'         => Order::where('company_id', $companyId)->where('status', 'confirme')->count(),

            // Bons de livraison
            'deliveries_en_attente'    => DeliveryNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'deliveries_a_facturer'    => DeliveryNote::where('company_id', $companyId)->where('status', 'valide')->count(),

            // Factures
            'invoices_en_attente'      => Invoice::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
            'invoices_emises'          => Invoice::where('company_id', $companyId)->whereIn('status', ['emise', 'envoyee'])->count(),
            'invoices_impayees'        => Invoice::where('company_id', $companyId)->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])->count(),

            // Avoirs
            'credit_notes_en_attente'  => CreditNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),

            // Documents refusés récents (7j)
            'recently_rejected'        => CommercialValidation::where('action', 'refus')
                                            ->where('created_at', '>=', now()->subDays(7))
                                            ->count(),

            // Total en attente toutes catégories
            'total_pending'            => Quote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + Order::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + DeliveryNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + Invoice::where('company_id', $companyId)->where('status', 'en_attente_validation')->count()
                                        + CreditNote::where('company_id', $companyId)->where('status', 'en_attente_validation')->count(),
        ];
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

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
