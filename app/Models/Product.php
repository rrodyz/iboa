<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'products';

    protected $fillable = [
        'reference',
        'barcode',
        'name',
        'description',
        'image',
        'family_id',
        'brand_id',
        'unit_id',
        'tax_rate_id',
        'sale_account_id',
        'purchase_account_id',
        'stock_account_id',
        'default_supplier_id',
        'supplier_reference',
        'delivery_delay_days',
        'type',
        'is_stockable',
        'is_purchasable',
        'is_sellable',
        'purchase_price',
        'last_purchase_price',
        'weighted_avg_cost',
        'sale_price',
        'min_sale_price',
        'margin_rate_target',
        'stock_min',
        'stock_max',
        'reorder_point',
        'valuation_method',
        'weight',
        'weight_unit',
        'has_serial_number',
        'has_lot_number',
        'has_expiry_date',
        'is_active',
    ];

    protected $casts = [
        'is_stockable'      => 'boolean',
        'is_purchasable'    => 'boolean',
        'is_sellable'       => 'boolean',
        'has_serial_number' => 'boolean',
        'has_lot_number'    => 'boolean',
        'has_expiry_date'   => 'boolean',
        'is_active'         => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function saleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sale_account_id');
    }

    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    public function stockAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'stock_account_id');
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'parent_product_id');
    }

    public function usedInComponents(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'component_product_id');
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function productPriceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('is_sellable', true);
    }

    public function scopeStockable(Builder $query): Builder
    {
        return $query->where('is_stockable', true);
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    public function currentStock(?int $warehouseId = null): float
    {
        $query = $this->productStocks();

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (float) $query->sum('quantity');
    }
}
