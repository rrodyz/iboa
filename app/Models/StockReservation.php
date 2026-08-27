<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [STOCK ↔ PRODUCTION/VENTES] Réservation de stock (produit fini ou matière)
 * pour une commande client, alimentée par la production.
 */
class StockReservation extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'order_id', 'production_order_id', 'product_id', 'warehouse_id',
        'stock_lot_id', 'coil_id', 'quantity', 'consumed_quantity',
        'status', 'reserved_at', 'released_at', 'created_by',
    ];

    protected $casts = [
        'quantity'           => 'decimal:2',
        'consumed_quantity'  => 'decimal:2',
        'reserved_at' => 'date',
        'released_at' => 'date',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\ProductionOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function stockLot(): BelongsTo { return $this->belongsTo(StockLot::class); }
    public function coil(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\Coil::class); }

    /**
     * [P1-D] Source unique du reste réservé : quantity − consumed_quantity,
     * jamais négatif. Une allocation « reserved » avec remainingReserved()==0
     * est intégralement consommée (voir StockReservation::status='consumed').
     */
    public function remainingReserved(): float
    {
        return max(0.0, (float) $this->quantity - (float) $this->consumed_quantity);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'reserved' => 'Réservé',
            'released' => 'Libéré',
            'consumed' => 'Consommé',
            default    => $this->status,
        };
    }
}
