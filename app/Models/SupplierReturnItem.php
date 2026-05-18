<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    protected $table = 'supplier_return_items';

    protected $fillable = [
        'supplier_return_id',
        'product_id',
        'unit_id',
        'tax_rate_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'tax_rate_value',
        'line_total_ht',
        'line_tax',
        'line_total_ttc',
        'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'integer',
        'discount_percent' => 'decimal:2',
        'tax_rate_value'   => 'decimal:2',
        'line_total_ht'    => 'integer',
        'line_tax'         => 'integer',
        'line_total_ttc'   => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
