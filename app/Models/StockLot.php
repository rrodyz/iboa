<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLot extends Model
{
    protected $table = 'stock_lots';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'lot_number',
        'supplier_lot_number',
        'serial_number',
        'expiry_date',
        'quantity',
        'initial_quantity',
        'reserved_quantity',
        'stock_uom',
        'kg_per_linear_meter',
        'unit_cost',
        'received_at',
        'source_type',
        'source_id',
        'created_by',
        'status',
        'quality_status',
        'qty_released',
        'qty_quarantine',
        'qty_rejected',
        'valuation_status',
        'valuation_reason',
        'valuation_responsible_id',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_at' => 'date',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:0',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** [P1-E] Réservations formelles P1-D liées à CE lot précisément (jamais les réservations génériques produit+dépôt sans stock_lot_id — voir StockLotQueryService). */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'stock_lot_id');
    }

    /** [P1-E] Bobine(s) physiques rattachées à ce lot (0, 1 ou plusieurs — jamais supposer 1:1). */
    public function coils(): HasMany
    {
        return $this->hasMany(\App\Modules\Production\Models\Coil::class, 'stock_lot_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeDisponible(Builder $query): Builder
    {
        return $query->where('status', 'disponible')
            ->where('valuation_status', 'valorisation_definitive');
    }

    public function scopeValued(Builder $query): Builder
    {
        return $query->where('valuation_status', 'valorisation_definitive')
            ->where('unit_cost', '>', 0);
    }

    /** Lots expiring within $days days (still available). */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'disponible')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->where('status', 'disponible');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /** Days until expiry. Negative = already expired. Null = no expiry date. */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'disponible' => 'Disponible',
            'reserve' => 'Réservé',
            'expire' => 'Expiré',
            'consomme' => 'Consommé',
            default => $this->status,
        };
    }

    /** [P1-E] Libellé du statut qualité — valeurs réelles observées (PurchaseQualityService, Coil::QUALITY_*). */
    public function qualityStatusLabel(): ?string
    {
        return match ($this->quality_status) {
            null => null,
            'recu' => 'Reçu',
            'en_attente' => 'En attente',
            'quarantaine' => 'Quarantaine',
            'libere' => 'Libéré',
            'libere_partiel' => 'Libéré partiel',
            'refuse' => 'Refusé',
            'retour_attente' => 'Retour en attente',
            'retourne' => 'Retourné',
            'annule' => 'Annulé',
            default => $this->quality_status,
        };
    }

    /**
     * [P1-E FINAL MICRO-GATE] Libellé du statut de VALORISATION — distinct de
     * quality_status : quality_status juge l'aptitude physique de la matière
     * (contrôle qualité réception/production), valuation_status juge si le
     * lot est correctement COÛTÉ pour la comptabilité (bloque la consommation
     * indépendamment de la qualité — cf. CoilConsumptionService::consume()
     * qui vérifie les deux séparément). Valeurs réelles observées (migration
     * 2026_07_25_180000, AuditUnvaluedStock, CoilConsumptionService).
     */
    public function valuationStatusLabel(): string
    {
        return match ($this->valuation_status) {
            null, 'valorisation_definitive' => 'Valorisé',
            'valorisation_manquante' => 'Coût manquant',
            'bloque_comptabilite' => 'Bloqué compta',
            default => $this->valuation_status,
        };
    }
}
