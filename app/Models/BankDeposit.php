<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankDeposit extends Model
{
    use SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'bank_deposits';

    protected $fillable = [
        'company_id', 'number', 'cash_account_id', 'source_cash_account_id',
        'deposit_date', 'total_amount', 'reference', 'notes',
        'status', 'created_by', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'validated_at' => 'datetime',
        'total_amount' => 'integer',
    ];

    public function cashAccount(): BelongsTo    { return $this->belongsTo(CashAccount::class); }
    public function sourceCashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class, 'source_cash_account_id'); }
    public function createdBy(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }
    public function validatedBy(): BelongsTo    { return $this->belongsTo(User::class, 'validated_by'); }

    public function items(): HasMany
    {
        return $this->hasMany(BankDepositItem::class)->orderBy('sort_order');
    }

    public function isEditable(): bool { return $this->status === 'brouillon'; }

    public function statusLabel(): string
    {
        return match($this->status) { 'brouillon' => 'Brouillon', 'valide' => 'Validé', default => $this->status };
    }

    public function statusColor(): string
    {
        return match($this->status) { 'brouillon' => 'gray', 'valide' => 'green', default => 'gray' };
    }
}
