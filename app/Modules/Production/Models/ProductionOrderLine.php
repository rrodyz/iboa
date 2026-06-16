<?php
namespace App\Modules\Production\Models;
use App\Models\Unit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderLine extends Model
{
    use HasFactory;
    protected $fillable = ['production_order_id','length','quantity','total_meters','unit_id','label','sort_order'];
    protected $casts = ['length'=>'decimal:2','quantity'=>'decimal:2','total_meters'=>'decimal:2'];

    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
}
