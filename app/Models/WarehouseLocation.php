<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseLocation extends Model
{
    protected $table = 'warehouse_locations';

    protected $fillable = [
        'warehouse_id', 'code', 'name', 'zone', 'aisle', 'rack', 'level',
        'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'location_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'location_id');
    }

    public function fullCode(): string
    {
        return collect([$this->zone, $this->aisle, $this->rack, $this->level])
            ->filter()
            ->implode('-') ?: $this->code;
    }
}
