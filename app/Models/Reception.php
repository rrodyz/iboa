<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reception extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'receptions';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'number',
        'status',
        'received_at',
        'warehouse_id',
        'type',
        'has_quality_check',
        'quality_notes',
        'notes',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'received_at'      => 'date',
        'validated_at'     => 'datetime',
        'has_quality_check'=> 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceptionItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validated_by');
    }
}
