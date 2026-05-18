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

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    public function displayName(): string
    {
        return $this->trade_name ?? $this->name;
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
