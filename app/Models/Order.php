<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Traits\HasCommercialWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasCommercialWorkflow;

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
    ];

    protected $casts = [
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
}
