<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'company_id', 'cash_account_id', 'number',
        'period_start', 'period_end', 'statement_date',
        'opening_balance', 'closing_balance', 'book_balance', 'difference',
        'status', 'notes', 'created_by', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'period_start'    => 'date',
        'period_end'      => 'date',
        'statement_date'  => 'date',
        'validated_at'    => 'datetime',
        'opening_balance' => 'integer',
        'closing_balance' => 'integer',
        'book_balance'    => 'integer',
        'difference'      => 'integer',
    ];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function validatedBy(): BelongsTo { return $this->belongsTo(User::class, 'validated_by'); }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('value_date')->orderBy('sort_order');
    }

    public function isEditable(): bool  { return $this->status === 'brouillon'; }

    public function statusLabel(): string
    {
        return match($this->status) { 'brouillon' => 'Brouillon', 'valide' => 'Validé', default => $this->status };
    }

    public function statusColor(): string
    {
        return match($this->status) { 'brouillon' => 'gray', 'valide' => 'green', default => 'gray' };
    }
}
