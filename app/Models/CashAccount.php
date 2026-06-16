<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope;

    protected $table = 'cash_accounts';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'bank_name',
        'bank_branch',
        'account_number',
        'iban',
        'swift_bic',
        'payment_method_id',
        'currency_code',
        'opening_balance',
        'current_balance',
        'min_balance',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'integer',
        'current_balance' => 'integer',
        'min_balance'     => 'integer',
        'is_default'      => 'boolean',
        'is_active'       => 'boolean',
    ];

    /** [TRESO] Solde sous le seuil d'alerte ? */
    public function isLowBalance(): bool
    {
        return (int) $this->min_balance > 0 && (int) $this->current_balance < (int) $this->min_balance;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class)->orderByDesc('transaction_date')->orderByDesc('id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function typeBadge(): string
    {
        return match ($this->type) {
            'caisse'       => 'Caisse',
            'banque'       => 'Banque',
            'mobile_money' => 'Mobile Money',
            default        => $this->type,
        };
    }
}
