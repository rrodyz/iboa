<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ProductPromotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_promotions';

    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'min_quantity',
        'min_amount',
        'product_id',
        'family_id',
        'client_id',
        'is_active',
    ];

    protected $casts = [
        'value'     => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'date',
        'ends_at'   => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query
            ->where('is_active', true)
            ->where('starts_at', '<=', $today)
            ->where('ends_at', '>=', $today);
    }
}
