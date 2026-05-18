<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyBankAccount extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope;

    protected $table = 'company_bank_accounts';

    protected $fillable = [
        'company_id',
        'bank_name',
        'account_holder',
        'account_number',
        'iban',
        'swift_bic',
        'branch',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The company that owns this bank account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
