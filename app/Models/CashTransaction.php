<?php

namespace App\Models;

use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasCreator;
    protected $table = 'cash_transactions';

    protected $fillable = [
        'cash_account_id',
        'type',
        'reference_type',
        'reference_id',
        'amount',
        'balance_after',
        'label',
        'transaction_date',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'integer',
        'balance_after'    => 'integer',
        'transaction_date' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
