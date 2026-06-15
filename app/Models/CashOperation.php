<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [TRESO] Opération diverse de caisse (entrée/sortie manuelle).
 */
class CashOperation extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'cash_account_id',
        'number',
        'direction',
        'category',
        'amount',
        'operation_date',
        'label',
        'status',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'operation_date' => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
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

    public function directionLabel(): string
    {
        return $this->direction === 'entree' ? 'Entrée' : 'Sortie';
    }
}
