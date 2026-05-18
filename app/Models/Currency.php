<?php

namespace App\Models;

use App\Models\Traits\HasReferenceCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasFactory, HasReferenceCache;

    protected $table = 'currencies';

    // [PERF-PHASE3] Cache de référence — tri par code ISO
    protected const REFERENCE_ORDER_COLUMN = 'code';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'thousands_separator',
        'decimal_separator',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only the default currency.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: only active currencies.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * Exchange rates where this currency is the source.
     */
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    /**
     * Exchange rates where this currency is the target.
     */
    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Format an integer FCFA amount using the currency's separators.
     */
    public function formatAmount(int $amount): string
    {
        $thousands = $this->thousands_separator ?? ' ';
        $decimal   = $this->decimal_separator   ?? ',';
        $places    = (int) ($this->decimal_places ?? 0);

        return number_format($amount, $places, $decimal, $thousands);
    }
}
