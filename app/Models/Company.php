<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'companies';

    /**
     * [PERF-PHASE3] Récupère l'entreprise courante avec mise en cache (1h).
     * Eager-load des relations souvent utilisées : currentFiscalYear, defaultCurrency.
     * Le cache est invalidé automatiquement à toute modification via le hook booted().
     */
    public static function current(): ?self
    {
        return Cache::remember('company:current', 3600, function () {
            return static::with(['currentFiscalYear', 'defaultCurrency'])->first();
        });
    }

    /**
     * Variante non-cachée pour les opérations qui ne peuvent pas accepter
     * une vue legerement obsolète (rare — réserver à l'édition).
     */
    public static function currentFresh(): ?self
    {
        Cache::forget('company:current');
        return static::current();
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('company:current'));
        static::deleted(fn () => Cache::forget('company:current'));
    }

    protected $fillable = [
        'name',
        'trade_name',
        'slogan',
        'logo',
        'address',
        'city',
        'region',
        'country',
        'postal_code',
        'phone',
        'phone2',
        'fax',
        'email',
        'website',
        'legal_form',
        'rccm',
        'ifu',
        'nif',
        'is_vat_subject',
        'vat_number',
        'share_capital',
        'share_capital_currency',
        'default_currency_id',
        'current_fiscal_year_id',
    ];

    protected $casts = [
        'is_vat_subject' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * Bank accounts belonging to this company.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CompanyBankAccount::class);
    }

    /**
     * Document layout/print settings for this company.
     */
    public function documentSetting(): HasOne
    {
        return $this->hasOne(DocumentSetting::class);
    }

    /**
     * Warehouses belonging to this company.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Document numbering sequences for this company.
     */
    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /**
     * The default currency of this company.
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * The currently active fiscal year.
     */
    public function currentFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'current_fiscal_year_id');
    }

    /**
     * Users linked to this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
