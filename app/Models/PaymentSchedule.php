<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [ACHATS-PRO-SCHEDULE] Échéance individuelle d'un cadencier.
 */
class PaymentSchedule extends Model
{
    protected $table = 'payment_schedules';

    protected $fillable = [
        'supplier_invoice_id', 'installment_number', 'due_date',
        'amount', 'paid_amount', 'status', 'label', 'notes',
    ];

    protected $casts = [
        'due_date'    => 'date',
        'amount'      => 'decimal:4',
        'paid_amount' => 'decimal:4',
    ];

    public function supplierInvoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class); }

    public function remainingAmount(): float { return (float) ($this->amount - $this->paid_amount); }
    public function isPaid(): bool           { return $this->status === 'paye'; }
    public function isOverdue(): bool        { return !$this->isPaid() && $this->status !== 'annule' && $this->due_date && $this->due_date->isPast(); }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) return 'En retard';
        return [
            'en_attente' => 'En attente',
            'partiel'    => 'Partiellement payée',
            'paye'       => 'Payée',
            'annule'     => 'Annulée',
        ][$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        if ($this->isOverdue()) return 'red';
        return [
            'en_attente' => 'gray',
            'partiel'    => 'amber',
            'paye'       => 'emerald',
            'annule'     => 'gray',
        ][$this->status] ?? 'gray';
    }
}
