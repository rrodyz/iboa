<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SupplierInvoice extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasAttachments, HasCompanyScope;

    protected $table = 'supplier_invoices';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'reception_id',
        'number',
        'supplier_invoice_number',
        'status',
        'received_at',
        'due_at',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_tax',
        'total_ttc',
        'paid_amount',
        'remaining_amount',
        'notes',
        'payment_term_id',
        'dispute_reason',
        'created_by',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'received_at'    => 'date',
        'due_at'         => 'date',
        'validated_at'   => 'datetime',
        'subtotal_ht'    => 'integer',
        'total_tax'      => 'integer',
        'total_ttc'      => 'integer',
        'paid_amount'    => 'integer',
        'remaining_amount'=> 'integer',
        'exchange_rate'  => 'decimal:6',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(Reception::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(
            SupplierPayment::class,
            'supplier_payment_allocations',
            'supplier_invoice_id',
            'supplier_payment_id'
        )->withPivot('amount', 'allocated_at')->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    // [ACHATS-PRO-SCHEDULE] Cadencier de paiement (échéances multiples)
    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('installment_number');
    }

    public function hasSchedule(): bool
    {
        return $this->paymentSchedules()->exists();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validated_by');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_at', '<', Carbon::today())
                     ->whereNotIn('status', ['payee', 'annulee']);
    }
}
