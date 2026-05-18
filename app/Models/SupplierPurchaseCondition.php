<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPurchaseCondition extends Model
{
    use HasFactory;

    protected $table = 'supplier_purchase_conditions';

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_reference',
        'purchase_price',
        'discount_percent',
        'min_order_quantity',
        'lead_time_days',
        'currency_code',
        'valid_until',
        'is_preferred',
    ];

    protected $casts = [
        'purchase_price'    => 'integer',
        'discount_percent'  => 'decimal:2',
        'is_preferred'      => 'boolean',
        'valid_until'       => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
