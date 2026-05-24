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
use App\Models\ClientPaymentSchedule;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasAttachments, HasCompanyScope;

    protected $table = 'invoices';

    // Statuts
    const STATUS_DRAFT     = 'brouillon';
    const STATUS_VALIDATED = 'validee';
    const STATUS_SENT      = 'envoyee';
    const STATUS_PARTIAL   = 'partiellement_payee';
    const STATUS_PAID      = 'payee';
    const STATUS_OVERDUE   = 'en_retard';
    const STATUS_CANCELLED = 'annulee';

    protected $fillable = [
        'company_id',
        'client_id',
        'fiscal_year_id',
        'order_id',
        'delivery_note_id',
        'number',
        'type',
        'status',
        'issued_at',
        'due_at',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'paid_amount',
        'remaining_amount',
        'global_discount_percent',
        'global_discount_amount',
        'withholding_details',
        'withholding_amount',
        'net_to_pay',
        'billing_address',
        'notes',
        'terms',
        'footer_note',
        'payment_terms',
        'payment_term_id',
        'created_by',
        'validated_by',
        'validated_at',
        'sent_at',
        'is_recurring',
        'recurring_frequency',
        'next_recurring_date',
        'parent_invoice_id',
    ];

    protected $casts = [
        'issued_at'               => 'date',
        'due_at'                  => 'date',
        'next_recurring_date'     => 'date',
        'subtotal_ht'             => 'integer',
        'total_discount'          => 'integer',
        'total_tax'               => 'integer',
        'total_ttc'               => 'integer',
        'paid_amount'             => 'integer',
        'remaining_amount'        => 'integer',
        'global_discount_percent' => 'decimal:2',
        'global_discount_amount'  => 'integer',
        'withholding_details'     => 'array',
        'withholding_amount'      => 'integer',
        'net_to_pay'              => 'integer',
        'exchange_rate'           => 'decimal:6',
        'is_recurring'            => 'boolean',
        'validated_at'            => 'datetime',
        'sent_at'                 => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientPayment::class,
            'client_payment_allocations',
            'invoice_id',
            'client_payment_id'
        )->withPivot('amount', 'allocated_at')->withTimestamps();
    }

    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(ClientPaymentSchedule::class)->orderBy('due_date');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'Brouillon',
            'validee'               => 'Validée',
            'envoyee'               => 'Envoyée',
            'partiellement_payee'   => 'Part. payée',
            'payee'                 => 'Payée',
            'en_retard'             => 'En retard',
            'annulee'               => 'Annulée',
            default                 => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon'           => 'gray',
            'validee'             => 'blue',
            'envoyee'             => 'indigo',
            'partiellement_payee' => 'amber',
            'payee'               => 'green',
            'en_retard'           => 'red',
            'annulee'             => 'red',
            default               => 'gray',
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /** Invoices past due date with remaining balance — auto-mark as en_retard. */
    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', ['payee', 'annulee', 'brouillon'])
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', today());
    }

    /** Mark all overdue invoices as en_retard. Returns count updated. */
    public static function markOverdue(): int
    {
        return static::overdue()
            ->where('status', '!=', 'en_retard')
            ->update(['status' => 'en_retard']);
    }
}
