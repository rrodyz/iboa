<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SupplierInvoice;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'suppliers';

    protected $fillable = [
        'code',
        'type',
        'name',
        'phone',
        'phone2',
        'email',
        'website',
        'address',
        'city',
        'country',
        'ifu',
        'rccm',
        'rating',
        'avg_delivery_days',
        'balance',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rating'    => 'decimal:1',
        'balance'   => 'integer',
        'is_active' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(SupplierAddress::class);
    }

    public function purchaseConditions(): HasMany
    {
        return $this->hasMany(SupplierPurchaseCondition::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
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

    /**
     * Recalculate and persist the supplier's outstanding balance.
     * Balance = sum of remaining_amount on validated (confirmed payable) supplier invoices.
     * Only 'validee' and 'partiellement_payee' are real obligations; brouillon/recue
     * have not been confirmed yet and must not inflate the balance.
     * Call this after any event that modifies supplier invoice amounts or statuses.
     */
    public function recalculateBalance(): void
    {
        // [FIX-SOLDES-02] Only count invoices that are legally confirmed payables.
        $outstanding = $this->invoices()
            ->whereIn('status', ['validee', 'partiellement_payee'])
            ->sum('remaining_amount');

        $this->update(['balance' => (int) $outstanding]);
    }
}
