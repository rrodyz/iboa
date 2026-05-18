<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'credit_notes';

    protected $fillable = [
        'company_id',
        'client_id',
        'invoice_id',
        'number',
        'status',
        'issued_at',
        'reason',
        'currency_code',
        'subtotal_ht',
        'total_tax',
        'total_ttc',
        'applied_amount',
        'remaining_credit',
        'notes',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'issued_at'        => 'date',
        'subtotal_ht'      => 'integer',
        'total_tax'        => 'integer',
        'total_ttc'        => 'integer',
        'applied_amount'   => 'integer',
        'remaining_credit' => 'integer',
        'validated_at'     => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class)->orderBy('sort_order');
    }

    // [FIX-MINEUR] Missing inverse relation for payment allocations
    public function allocations(): HasMany
    {
        return $this->hasMany(ClientPaymentAllocation::class, 'credit_note_id');
    }
}
