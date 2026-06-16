<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [TRESO] Virement interne entre deux comptes de trésorerie.
 */
class CashTransfer extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'from_cash_account_id',
        'to_cash_account_id',
        'number',
        'amount',
        'transfer_date',
        'reference',
        'notes',
        'status',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'transfer_date' => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'from_cash_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'to_cash_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isCancellable(): bool
    {
        return $this->status === 'valide';
    }
}
