<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPaymentAllocation extends Model
{
    protected $table = 'client_payment_allocations';

    protected $fillable = [
        'client_payment_id',
        'invoice_id',
        'credit_note_id',
        'amount',
        'allocated_at',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'allocated_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function clientPayment(): BelongsTo
    {
        return $this->belongsTo(ClientPayment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
