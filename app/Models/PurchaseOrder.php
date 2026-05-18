<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'fiscal_year_id',
        'number',
        'status',
        'ordered_at',
        'expected_at',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_tax',
        'total_ttc',
        'notes',
        'delivery_address',
        'created_by',
        'validated_by',
        'validated_at',
        // [ACHATS-PRO-APPROVAL] Approval workflow
        'approval_status',
        'submitted_for_approval_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'ordered_at'   => 'date',
        'expected_at'  => 'date',
        'validated_at' => 'datetime',
        'submitted_for_approval_at' => 'datetime',
        'approved_at'  => 'datetime',
        'subtotal_ht'  => 'integer',
        'total_tax'    => 'integer',
        'total_ttc'    => 'integer',
        'exchange_rate'=> 'decimal:6',
    ];

    // [ACHATS-PRO-APPROVAL] Helpers
    public function requiresApproval(): bool   { return $this->approval_status !== 'non_requis'; }
    public function isAwaitingApproval(): bool { return $this->approval_status === 'en_attente'; }
    public function isApproved(): bool         { return $this->approval_status === 'approuve'; }
    public function isRejected(): bool         { return $this->approval_status === 'rejete'; }
    public function approvedBy()               { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }

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

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(Reception::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
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

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
