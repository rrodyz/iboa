<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Échéance de paiement client sur une facture.
 * Permet de fractionner le règlement d'une facture en plusieurs versements.
 */
class ClientPaymentSchedule extends Model
{
    protected $table = 'client_payment_schedules';

    protected $fillable = [
        'invoice_id', 'installment_number', 'due_date',
        'amount', 'paid_amount', 'status', 'label', 'notes',
    ];

    protected $casts = [
        'due_date'    => 'date',
        'amount'      => 'decimal:0',
        'paid_amount' => 'decimal:0',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paye';
    }

    public function isOverdue(): bool
    {
        return !$this->isPaid()
            && $this->status !== 'annule'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) return 'En retard';
        return [
            'en_attente' => 'En attente',
            'partiel'    => 'Partiel',
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
