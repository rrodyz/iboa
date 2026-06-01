<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CreditNote;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'clients';

    // Types
    const TYPE_ENTREPRISE  = 'entreprise';
    const TYPE_PARTICULIER = 'particulier';

    protected $fillable = [
        'code',
        'type',
        'name',
        'trade_name',
        'civility',
        'phone',
        'phone2',
        'mobile',
        'email',
        'website',
        'address',
        'city',
        'country',
        'ifu',
        'rccm',
        'tax_regime',
        'tax_division',
        'category',
        'assigned_to',
        'tax_rate_id',
        // Exonération TVA
        'is_tax_exempt',
        'tax_exemption_reason',
        'tax_exemption_number',
        'credit_limit',
        'payment_days',
        'payment_terms',
        'default_discount',
        'balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'credit_limit'     => 'integer',
        'default_discount' => 'decimal:2',
        'balance'          => 'integer',
        'is_active'        => 'boolean',
        'is_tax_exempt'    => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function taxRates(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'client_tax_rates');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class);
    }

    public function assignedCommercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_ENTREPRISE, self::TYPE_PARTICULIER]);
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    public function displayName(): string
    {
        return $this->trade_name ?? $this->name;
    }

    /**
     * Retourne true si le client est exonéré de TVA.
     * Utilisé par les services pour forcer tax_rate_value = 0 côté serveur.
     */
    public function isTaxExempt(): bool
    {
        return (bool) $this->is_tax_exempt;
    }

    /** Vérifier si l'encours dépasse le plafond de crédit. */
    public function isOverCreditLimit(): bool
    {
        if (!$this->credit_limit || $this->credit_limit <= 0) return false;
        return $this->balance > $this->credit_limit;
    }

    /** Montant disponible avant d'atteindre le plafond. */
    public function getAvailableCreditAttribute(): int
    {
        if (!$this->credit_limit || $this->credit_limit <= 0) return PHP_INT_MAX;
        return max(0, (int)$this->credit_limit - (int)$this->balance);
    }

    /** Taux d'utilisation du crédit en %. */
    public function getCreditUsagePercentAttribute(): float
    {
        if (!$this->credit_limit || $this->credit_limit <= 0) return 0;
        return round(($this->balance / $this->credit_limit) * 100, 1);
    }

    /**
     * Recalculate and persist the client's outstanding balance.
     * Balance = open invoice amounts − available credit-note credits.
     * Call this after any event that modifies invoice amounts, statuses, or credit notes.
     */
    public function recalculateBalance(): void
    {
        $outstanding = $this->invoices()
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->sum('remaining_amount');

        // [FIX-SOLDES-01] Subtract any credit-note credit still available to apply.
        // A validated avoir reduces what the client effectively owes.
        $availableCredit = $this->creditNotes()
            ->where('status', 'valide')
            ->sum('remaining_credit');

        $this->update(['balance' => max(0, (int) ($outstanding - $availableCredit))]);
    }
}
