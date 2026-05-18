<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqQuoteItem extends Model
{
    protected $table = 'rfq_quote_items';
    protected $fillable = [
        'rfq_quote_id', 'rfq_item_id', 'unit_price', 'discount_percent',
        'tax_rate', 'line_total_ht', 'line_total_ttc', 'delivery_days', 'notes',
    ];

    protected $casts = [
        'unit_price'       => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'tax_rate'         => 'decimal:2',
        'line_total_ht'    => 'decimal:4',
        'line_total_ttc'   => 'decimal:4',
    ];

    public function quote(): BelongsTo { return $this->belongsTo(RfqQuote::class, 'rfq_quote_id'); }
    public function item(): BelongsTo  { return $this->belongsTo(RfqItem::class, 'rfq_item_id'); }
}
