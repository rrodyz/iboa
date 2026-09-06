<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Services\Production\ProductionFinancialEligibilityService;
use App\Traits\HasCommercialWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasCommercialWorkflow, HasAttachments;

    const DOCUMENT_TYPE = 'order';

    protected $table = 'orders';

    protected $fillable = [
        'company_id',
        'client_id',
        'fiscal_year_id',
        'quote_id',
        'number',
        'reference',
        'status',
        'production_approved',
        'production_approved_at',
        'production_approved_by',
        'production_approval_reason',
        'production_approval_unpaid',
        'production_approval_expires_at',
        'production_approval_fingerprint',
        // [R4.4/R4.5] Approbation hiérarchique du passage en bon de préparation.
        'preparation_approval_status',
        'preparation_requested_by',
        'preparation_requested_at',
        'preparation_approved_by',
        'preparation_approved_at',
        'preparation_approval_reason',
        'preparation_approval_context',
        // [R4.5] Dérogation exceptionnelle au plafond d'encours.
        'credit_overrun_status',
        'credit_overrun_requested_by',
        'credit_overrun_requested_at',
        'credit_overrun_approved_by',
        'credit_overrun_approved_at',
        'credit_overrun_reason',
        'credit_overrun_context',
        'credit_overrun_fingerprint',
        'issued_at',
        'expires_at',
        'delivery_date',
        'delivery_warehouse_id',
        'delivery_address',
        'billing_address',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'global_discount_percent',
        'global_discount_amount',
        'invoiced_amount',
        'notes',
        'terms',
        'footer_note',
        'created_by',
        'validated_by',
        'validated_at',
        'submitted_by',
        'submitted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        // [Maquette Commande client]
        'contact_id', 'sales_rep_id', 'price_mode', 'net_prices', 'price_list',
        'payment_terms', 'payment_method', 'fiscal_representative', 'fiscal_regime',
        'default_tax_label', 'project_reference',
        'carrier', 'vehicle_number', 'delivery_location', 'incoterm', 'priority', 'total_weight_kg',
    ];

    protected $casts = [
        'net_prices'              => 'boolean',
        'production_approved'     => 'boolean',
        'production_approved_at'  => 'datetime',
        'production_approval_expires_at' => 'date',
        'production_approval_unpaid'     => 'integer',
        'preparation_requested_at'       => 'datetime',
        'preparation_approved_at'        => 'datetime',
        'preparation_approval_context'   => 'array',
        'credit_overrun_requested_at'    => 'datetime',
        'credit_overrun_approved_at'     => 'datetime',
        'credit_overrun_context'         => 'array',
        'total_weight_kg'         => 'decimal:2',
        'issued_at'               => 'date',
        'expires_at'              => 'date',
        'delivery_date'           => 'date',
        'subtotal_ht'             => 'integer',
        'total_discount'          => 'integer',
        'total_tax'               => 'integer',
        'total_ttc'               => 'integer',
        'global_discount_percent' => 'decimal:2',
        'global_discount_amount'  => 'integer',
        'invoiced_amount'         => 'integer',
        'exchange_rate'           => 'decimal:6',
        'validated_at'            => 'datetime',
        'submitted_at'            => 'datetime',
        'rejected_at'             => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // [Maquette Commande client]
    public function contact(): BelongsTo { return $this->belongsTo(ClientContact::class, 'contact_id'); }
    public function salesRep(): BelongsTo { return $this->belongsTo(User::class, 'sales_rep_id'); }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function deliveryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'delivery_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order');
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(\App\Modules\Production\Models\ProductionOrder::class);
    }

    public function bonPreparations(): HasMany
    {
        return $this->hasMany(BonPreparation::class);
    }

    public function productionApprovedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'production_approved_by');
    }

    /** OF actif (non annulé) lié à la commande. */
    public function hasActiveProductionOrder(): bool
    {
        return $this->productionOrders()->where('status', '!=', 'annule')->exists();
    }

    /**
     * [MTO §1.3 — méthode centrale] Encaissements confirmés rattachables à la commande :
     *   1. allocations confirmées sur les factures de la commande ;
     *   2. paiements caisse enregistrés sur les bons de préparation actifs (comptant) ;
     *   3. acomptes libres confirmés du client (non alloués).
     * Utilisée PAR LE TABLEAU d'éligibilité ET par la gate financière de lancement OF
     * (même règle, même source — exigence de recette).
     */
    /**
     * Montant retenu UNIQUEMENT pour l'éligibilité de la commande à la production
     * (tableau coordinateur + gate financière de lancement OF).
     *
     * Le résultat est PLAFONNÉ au TTC de la commande et ne représente pas
     * nécessairement le total comptable ou bancaire réellement encaissé.
     * Ne jamais l'utiliser pour : états d'encaissement, trésorerie, solde client,
     * comptabilité, remboursements, balance âgée, détection de trop-perçus.
     *
     * Pas de mémoïsation : une décision financière doit toujours refléter l'état
     * courant (un paiement ajouté/annulé dans la même exécution est vu au prochain appel).
     */
    public function confirmedReceipts(): int
    {
        // Seuls les paiements CONFIRMÉS comptent (brouillons/annulés/rejetés exclus) ;
        // un BP annulé est exclu (statuts actifs seulement). L'acompte libre affecté
        // ensuite à une facture est un TRANSFERT (unallocated ↓, allocation ↑) — pas
        // de double comptage par construction.
        $invoiceIds = \App\Models\Invoice::where('order_id', $this->id)->pluck('id');
        $viaInvoices = $invoiceIds->isNotEmpty()
            ? (int) \App\Models\ClientPaymentAllocation::whereIn('invoice_id', $invoiceIds)
                ->whereHas('clientPayment', fn ($q) => $q->where('status', 'confirme'))->sum('amount')
            : 0;

        // [Anti-double BP↔encaissement] Les BP liés à un encaissement central
        // (client_payment_id) comptent via l'acompte du client, pas ici.
        $viaCaisse = (int) $this->bonPreparations()
            ->whereIn('status', ['en_attente', 'en_cours', 'charge'])
            ->whereNull('client_payment_id')
            ->sum('payment_amount');

        $acomptesLibres = (int) \App\Models\ClientPayment::where('client_id', $this->client_id)
            ->where('status', 'confirme')->where('is_acompte', true)
            ->sum('unallocated_amount');

        // [FIX anomalie ÉLEVÉE — acomptes libres partagés] Un même acompte libre ne
        // peut pas rendre plusieurs commandes éligibles : les commandes SŒURS du
        // client ayant déjà un OF ACTIF « réservent » la part d'acompte libre qui a
        // couvert leur exigence (requis − leurs encaissements propres). Déduction
        // conservatrice : en cas de doute, la commande est SOUS-éligible, jamais sur-éligible.
        if ($acomptesLibres > 0) {
            $siblings = static::where('client_id', $this->client_id)
                ->where('id', '!=', $this->id)
                ->whereHas('productionOrders', fn ($q) => $q->where('status', '!=', 'annule'))
                ->get();
            foreach ($siblings as $sibling) {
                $required = $sibling->requiredBeforeProduction();
                if ($required === null) {
                    continue; // crédit : éligible par approbation, ne consomme pas d'acompte
                }
                $ownReceipts = (int) \App\Models\Invoice::where('order_id', $sibling->id)->pluck('id')
                    ->pipe(fn ($ids) => $ids->isNotEmpty()
                        ? \App\Models\ClientPaymentAllocation::whereIn('invoice_id', $ids)
                            ->whereHas('clientPayment', fn ($q) => $q->where('status', 'confirme'))->sum('amount')
                        : 0)
                    + (int) $sibling->bonPreparations()
                        ->whereIn('status', ['en_attente', 'en_cours', 'charge'])->sum('payment_amount');
                $claimed = max(0, $required - $ownReceipts);
                $acomptesLibres = max(0, $acomptesLibres - $claimed);
                if ($acomptesLibres === 0) {
                    break;
                }
            }
        }

        // Plafond au TTC : si le même argent caisse (BP) est ensuite ressaisi en
        // trésorerie et alloué à la facture (aucun lien BP↔ClientPayment n'existe),
        // la somme sur-compterait — le plafond rend ce cumul inoffensif pour
        // l'éligibilité et la gate (comparaisons bornées à 100 % du TTC).
        return min($viaInvoices + $viaCaisse + $acomptesLibres, (int) $this->total_ttc);
    }

    /**
     * [MTO §1.3] Montant à encaisser avant production. `null` = aucun chemin par
     * le paiement (crédit, mode inconnu) : l'éligibilité passe alors par le
     * plafond de crédit ou par une dérogation.
     *
     * Ne décide de rien à elle seule — elle sert à répartir les acomptes libres
     * entre commandes sœurs dans {@see confirmedReceipts()}. La décision
     * complète est {@see productionFinancialRequirement()}.
     */
    public function requiredBeforeProduction(): ?int
    {
        return app(ProductionFinancialEligibilityService::class)->requiredAmount($this);
    }

    /**
     * [BUG-A3-MTO-FIN-001] Exigence financière complète et son verdict.
     *
     * Point d'entrée unique pour les écrans comme pour la garde de lancement.
     * Le calcul n'écrit RIEN : afficher l'éligibilité ne peut plus créer
     * d'autorisation en base.
     */
    public function productionFinancialRequirement(?\App\Modules\Production\Models\ProductionOrder $productionOrder = null, bool $lock = false): \App\Services\Production\ProductionFinancialRequirement
    {
        return app(ProductionFinancialEligibilityService::class)->evaluate($this, $productionOrder, $lock);
    }

    /** [MTO §1.3] Éligibilité financière — calculée, jamais persistée. */
    public function isFinanciallyEligibleForProduction(?\App\Modules\Production\Models\ProductionOrder $productionOrder = null): bool
    {
        return $this->productionFinancialRequirement($productionOrder)->satisfied;
    }

    /**
     * [P1-B — anti-dérogation périmée] Empreinte du CONTRAT FINANCIER pertinent
     * pour l'exigence de production — jamais tout le JSON de la commande.
     * Champs retenus parce qu'ils influencent directement l'exposition/le
     * montant à couvrir dans {@see \App\Services\Production\
     * ProductionFinancialEligibilityService} : client (et son mode de
     * règlement, qui bascule cash↔crédit), price_mode/payment_method/
     * payment_terms (conditions de règlement de LA commande), et par ligne
     * product_id/quantity/unit_price/discount_percent/tax_rate_value — jamais
     * les notes, adresses de livraison ou autres champs non financiers.
     *
     * Déterministe : lignes triées par id (ordre stable, indépendant de
     * l'ordre de récupération SQL), valeurs décimales lues via les casts
     * Eloquent existants (déjà des chaînes normalisées, ex. "12.6700" —
     * aucune re-formatage flottant source de non-déterminisme), aucun
     * timestamp ni identifiant sans rapport avec le contrat.
     */
    public function productionFinancialFingerprint(): string
    {
        $lines = $this->items->sortBy('id')->values()->map(fn (OrderItem $item) => [
            'product_id' => $item->product_id,
            'quantity' => (string) $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_percent' => (string) $item->discount_percent,
            'tax_rate_value' => (string) $item->tax_rate_value,
        ])->all();

        // [Déterminisme] `price_mode`/`subtotal_ht`/`total_discount`/`total_tax`
        // portent un DEFAULT SQL ('ttc', 0, 0, 0 — migrations orders). Un modèle
        // fraîchement ::create()-é sans ces clés explicites les garde NULL en
        // mémoire tant qu'il n'a pas été relu (fresh()/find()) : sans ce ??,
        // l'empreinte calculée juste après la création diffère de celle
        // recalculée sur une instance relue plus tard pour le MÊME contrat —
        // exactement le non-déterminisme que ce fingerprint doit exclure.
        return hash('sha256', json_encode([
            'client_id' => $this->client_id,
            'client_payment_mode' => $this->client?->payment_mode,
            'price_mode' => $this->price_mode ?? 'ttc',
            'payment_method' => $this->payment_method,
            'payment_terms' => $this->payment_terms,
            'subtotal_ht' => $this->subtotal_ht ?? 0,
            'total_discount' => $this->total_discount ?? 0,
            'total_tax' => $this->total_tax ?? 0,
            'total_ttc' => $this->total_ttc,
            'lines' => $lines,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * [MTO §1.3] Approbation gérant valide : posée, non expirée, ET dont le
     * contrat financier n'a pas changé depuis (P1-B). Une commande modifiée
     * après approbation (client, prix, quantité, remise, mode de règlement…)
     * rend l'ancienne dérogation « stale » — elle reste en base pour l'audit
     * (approved_by/at/reason inchangés) mais ne couvre plus la production.
     * `production_approval_fingerprint` NULL (approbation posée avant ce
     * correctif) est fail-closed : jamais vérifiable, jamais valide.
     */
    public function hasValidProductionApproval(): bool
    {
        if (! $this->production_approved) {
            return false;
        }
        if ($this->production_approval_expires_at !== null && $this->production_approval_expires_at->lt(today())) {
            return false;
        }
        if ($this->production_approval_fingerprint === null) {
            return false;
        }

        return hash_equals($this->production_approval_fingerprint, $this->productionFinancialFingerprint());
    }

    /**
     * [R4.5] Dérogation de dépassement d'encours valide : approuvée ET portant
     * l'empreinte du contrat financier courant.
     *
     * La dérogation couvre UN montant sur UN client, pas la commande en
     * général : si les lignes, la remise, le client ou son mode de règlement
     * changent après l'approbation, l'exception ne couvre plus ce qui a été
     * approuvé. Empreinte absente = jamais vérifiable = jamais valide, comme
     * pour l'approbation production.
     */
    public function hasValidCreditOverrunApproval(): bool
    {
        if ($this->credit_overrun_status !== 'approved') {
            return false;
        }
        if ($this->credit_overrun_fingerprint === null) {
            return false;
        }

        return hash_equals($this->credit_overrun_fingerprint, $this->productionFinancialFingerprint());
    }

    /**
     * [Flux tôle bac §3 / MTO §1.3] Pré-filtre SQL de l'éligibilité : confirmée,
     * ≥ 1 article MTO, sans OF actif, ET (approbation valide OU BP actif).
     * Le volet financier exact (montant encaissé ≥ requis, 3 sources) n'est pas
     * exprimable proprement en SQL : il est appliqué par le contrôleur du tableau
     * via isFinanciallyEligibleForProduction() — même méthode que la gate OF.
     */
    public function scopeEligibleForProduction($query)
    {
        return $query->whereIn('status', ['confirme', 'en_preparation'])
            ->whereHas('items.product', fn ($q) => $q->where('production_mode', 'mto'))
            ->whereDoesntHave('productionOrders', fn ($q) => $q->where('status', '!=', 'annule'))
            ->where(fn ($q) => $q
                ->where(fn ($a) => $a
                    ->where('production_approved', true)
                    ->where(fn ($v) => $v
                        ->whereNull('production_approval_expires_at')
                        ->orWhereDate('production_approval_expires_at', '>=', today())))
                ->orWhereHas('bonPreparations', fn ($b) => $b->whereIn('status', ['en_attente', 'en_cours', 'charge'])));
    }

    /** Retourne true si la commande a un bon de préparation actif (pas annulé). */
    public function hasBonPreparation(): bool
    {
        return $this->bonPreparations()->whereIn('status', ['en_attente', 'en_cours', 'charge'])->exists();
    }

    /** Bon de préparation actif de la commande (en attente, en cours ou chargé). */
    public function activeBonPreparation(): ?BonPreparation
    {
        return $this->bonPreparations()
            ->whereIn('status', ['en_attente', 'en_cours', 'charge'])
            ->latest('id')
            ->first();
    }

    /**
     * [SYNC] Recalcule le montant facturé depuis les factures actives liées —
     * appelé à la validation ET à l'annulation d'une facture.
     */
    public static function resyncInvoicedAmount(?int $orderId): void
    {
        if (! $orderId) {
            return;
        }
        $total = Invoice::where('order_id', $orderId)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->sum('total_ttc');
        static::withoutGlobalScopes()->where('id', $orderId)
            ->update(['invoiced_amount' => $total]);
    }

    /**
     * [CDC §13.7] Le BL ne se crée qu'après préparation + contrôle chargement :
     * si un bon de préparation existe, il doit être « chargé ». Sans BP
     * (flux direct hors préparation), la livraison reste possible.
     */
    public function isReadyForDelivery(): bool
    {
        $bp = $this->activeBonPreparation();

        return $bp === null || $bp->isCharge();
    }

    // ── Accessors workflow ────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'Brouillon',
            'en_attente_validation' => 'En attente de validation',
            'confirme'              => 'Confirmé',
            'en_preparation'        => 'En préparation',
            'partiellement_livre'   => 'Partiellement livré',
            'livre'                 => 'Livré',
            'facture'               => 'Facturé',
            'annule'                => 'Annulé',
            default                 => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'gray',
            'en_attente_validation' => 'yellow',
            'confirme'              => 'green',
            'en_preparation'        => 'blue',
            'partiellement_livre'   => 'indigo',
            'livre'                 => 'teal',
            'facture'               => 'purple',
            'annule'                => 'red',
            default                 => 'gray',
        };
    }

    protected function getValidatedStatuses(): array
    {
        return ['confirme', 'en_preparation', 'partiellement_livre', 'livre', 'facture'];
    }

    /**
     * [Ventes §17] Statuts depuis lesquels une commande peut être annulée.
     *
     * La version générique du trait s'arrête à `brouillon` et
     * `en_attente_validation`. Appliquée aux commandes, elle rendait
     * `cancelDocument()` — donc le motif, l'auteur et le journal d'audit —
     * INACCESSIBLE dès la confirmation. L'interface contournait le blocage avec
     * un second bouton « Annuler » branché sur un chemin sans motif : une
     * commande confirmée disparaissait sans qu'on sache ni qui, ni pourquoi.
     *
     * La liste ci-dessous est le complément exact des statuts refusés par
     * OrderService::cancel() — `annule`, `facture`, `livre` — pour que les deux
     * gardes ne puissent pas diverger.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [
            'brouillon',
            'en_attente_validation',
            'confirme',
            'en_preparation',
            'partiellement_livre',
        ], true);
    }
}
