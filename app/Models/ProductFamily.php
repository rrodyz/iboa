<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductFamily extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_families';

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'description',
        'sale_account_id',
        'purchase_account_id',
        'stock_account_id',
        'image',
        'depth',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The parent family (self-referential).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'parent_id');
    }

    /**
     * Direct child families (self-referential).
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProductFamily::class, 'parent_id');
    }

    /**
     * Products belonging to this family.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'family_id');
    }

    /**
     * Compte comptable de vente (classe 7).
     */
    public function saleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sale_account_id');
    }

    /**
     * Compte comptable d'achat (classe 6).
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    /**
     * Compte comptable de stock (classe 3).
     */
    public function stockAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'stock_account_id');
    }
}
