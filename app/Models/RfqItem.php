<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqItem extends Model
{
    protected $table = 'rfq_items';
    protected $fillable = ['rfq_id', 'product_id', 'description', 'quantity', 'unit_id', 'sort_order'];
    protected $casts = ['quantity' => 'decimal:4'];

    public function rfq(): BelongsTo       { return $this->belongsTo(Rfq::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function quoteItems(): HasMany  { return $this->hasMany(RfqQuoteItem::class); }
}
