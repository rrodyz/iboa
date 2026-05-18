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
}
