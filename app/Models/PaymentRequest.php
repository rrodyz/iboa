<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [TRESO] Demande de paiement.
 */
class PaymentRequest extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'supplier_id', 'payment_method_id', 'supplier_invoice_id',
        'number', 'object', 'beneficiary', 'amount', 'due_date', 'priority',
        'status', 'required_role', 'supplier_payment_id', 'notes',
        'requested_by', 'submitted_at', 'validated_by', 'validated_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'due_date'     => 'date',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'rejected_at'  => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // ── Helpers workflow ──────────────────────────────────────────────────────
    public function isEditable(): bool   { return $this->status === 'brouillon'; }
    public function isSubmittable(): bool { return $this->status === 'brouillon'; }
    public function isValidatable(): bool { return $this->status === 'soumis'; }
    public function isRejectable(): bool  { return $this->status === 'soumis'; }
    public function isPayable(): bool     { return $this->status === 'valide'; }

    public function beneficiaryName(): string
    {
        return $this->supplier?->name ?? $this->beneficiary ?? '—';
    }
}
