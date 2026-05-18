<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqQuote extends Model
{
    protected $table = 'rfq_quotes';
    protected $fillable = [
        'rfq_id', 'rfq_supplier_id', 'supplier_reference', 'valid_until',
        'currency_code', 'exchange_rate', 'subtotal_ht', 'total_tax', 'total_ttc',
        'delivery_days', 'notes', 'is_winner',
    ];

    protected $casts = [
        'valid_until'   => 'date',
        'exchange_rate' => 'decimal:6',
        'subtotal_ht'   => 'decimal:4',
        'total_tax'     => 'decimal:4',
        'total_ttc'     => 'decimal:4',
        'is_winner'     => 'boolean',
    ];

    public function rfq(): BelongsTo         { return $this->belongsTo(Rfq::class); }
    public function rfqSupplier(): BelongsTo { return $this->belongsTo(RfqSupplier::class); }
    public function items(): HasMany         { return $this->hasMany(RfqQuoteItem::class); }
    public function supplier()               { return $this->rfqSupplier->supplier ?? null; }
}
