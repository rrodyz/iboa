<?php

namespace App\Models;

use App\Models\Traits\HasReferenceCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    use HasFactory, HasReferenceCache;

    protected $table = 'tax_rates';

    // [PERF-PHASE3] Cache de référence — tri par taux
    protected const REFERENCE_ORDER_COLUMN = 'rate';

    protected $fillable = [
        'name',
        'short_name',
        'rate',
        'type',
        'collected_account_id',
        'deductible_account_id',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate'       => 'decimal:2',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope: only the default tax rate.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: only active tax rates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tax_rate_id');
    }

    /**
     * Compte de TVA collectée par défaut pour ce taux.
     */
    public function collectedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'collected_account_id');
    }

    /**
     * Compte de TVA déductible par défaut pour ce taux.
     */
    public function deductibleAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deductible_account_id');
    }
}
