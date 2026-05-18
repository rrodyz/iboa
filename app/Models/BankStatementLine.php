<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'bank_reconciliation_id', 'journal_entry_line_id',
        'value_date', 'label', 'reference',
        'debit', 'credit', 'is_matched', 'sort_order',
    ];

    protected $casts = [
        'value_date' => 'date',
        'debit'      => 'integer',
        'credit'     => 'integer',
        'is_matched' => 'boolean',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class);
    }

    public function getAmountAttribute(): int
    {
        return $this->credit - $this->debit;
    }
}
