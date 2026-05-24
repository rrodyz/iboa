<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'quotes';

    // Statuts possibles
    const STATUS_DRAFT     = 'brouillon';
    const STATUS_VALIDATED = 'valide';
    const STATUS_CONVERTED = 'converti';
    const STATUS_EXPIRED   = 'expire';
    const STATUS_REFUSED   = 'refuse';
    const STATUS_CANCELLED = 'annule';

    protected $fillable = [
        'company_id',
        'client_id',
        'fiscal_year_id',
        'number',
        'reference',
        'status',
        'issued_at',
        'expires_at',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'global_discount_percent',
        'global_discount_amount',
        'notes',
        'terms',
        'footer_note',
        'created_by',
        'validated_by',
        'validated_at',
        'converted_to_order_id',
    ];

    protected $casts = [
        'issued_at'               => 'date',
        'expires_at'              => 'date',
        'subtotal_ht'             => 'integer',
        'total_discount'          => 'integer',
        'total_tax'               => 'integer',
        'total_ttc'               => 'integer',
        'global_discount_percent' => 'decimal:2',
        'global_discount_amount'  => 'integer',
        'exchange_rate'           => 'decimal:6',
        'validated_at'            => 'datetime',
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
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_to_order_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast() && $this->status === 'valide';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'Brouillon',
            'valide'    => $this->is_expired ? 'Expiré' : 'Validé',
            'converti'  => 'Converti',
            'expire'    => 'Expiré',
            'refuse'    => 'Refusé',
            'annule'    => 'Annulé',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon' => 'gray',
            'valide'    => $this->is_expired ? 'orange' : 'blue',
            'converti'  => 'green',
            'expire'    => 'orange',
            'refuse'    => 'red',
            'annule'    => 'red',
            default     => 'gray',
        };
    }
}
