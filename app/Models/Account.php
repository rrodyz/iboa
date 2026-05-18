<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Account extends Model
{
    use HasCompanyScope;

    protected $table = 'accounts';

    protected $fillable = [
        'company_id',
        'account_class_id',
        'parent_id',
        'code',
        'name',
        'type',
        'is_detail',
        'is_active',
        'debit_balance',
        'credit_balance',
    ];

    protected $casts = [
        'is_detail'      => 'boolean',
        'is_active'      => 'boolean',
        'debit_balance'  => 'integer',
        'credit_balance' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountClass(): BelongsTo
    {
        return $this->belongsTo(AccountClass::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_detail', true)->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Computed
    // -------------------------------------------------------------------------

    public function getBalanceAttribute(): int
    {
        return $this->debit_balance - $this->credit_balance;
    }

    public function getFullNameAttribute(): string
    {
        return $this->code . ' — ' . $this->name;
    }
}
