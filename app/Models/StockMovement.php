<?php

namespace App\Models;

use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory, HasCreator;

    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'valuation_method',
        'avg_cost_after',
        'occurred_at',
        'reference_type',
        'reference_id',
        'lot_number',
        'serial_number',
        'expiry_date',
        'from_warehouse_id',
        'to_warehouse_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity'      => 'decimal:4',
        'unit_cost'     => 'decimal:2',
        'total_cost'    => 'decimal:2',
        'avg_cost_after'=> 'decimal:2',
        'occurred_at'   => 'datetime',
        'expiry_date'   => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * [AUDIT-ERP-D] Relation polymorphique vers le document source.
     * Usage : $movement->referenceable → le modèle source (DeliveryNote, Reception, Invoice…).
     *
     * Valeurs de reference_type reconnues :
     *   delivery_note | reception | invoice | supplier_invoice
     *   credit_note   | inventory_session | stock_transfer | adjustment
     */
    public function referenceable(): MorphTo
    {
        return $this->morphTo('referenceable', 'reference_type', 'reference_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true if this movement type increases stock.
     */
    public function isInbound(): bool
    {
        return in_array($this->type, ['entree', 'retour_client'])
            || ($this->type === 'ajustement' && (float) $this->quantity > 0);
    }

    /**
     * Returns true if this movement type decreases stock.
     */
    public function isOutbound(): bool
    {
        return in_array($this->type, ['sortie', 'retour_fournisseur', 'transfert'])
            || ($this->type === 'ajustement' && (float) $this->quantity < 0);
    }
}
