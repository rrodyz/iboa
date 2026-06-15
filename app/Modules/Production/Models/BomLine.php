<?php
namespace App\Modules\Production\Models;
use App\Models\Product;
use App\Models\Unit;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    use HasFactory;
    protected $fillable = ['bill_of_material_id','product_id','label','quantity_per_meter','unit_id','waste_rate','sort_order'];
    protected $casts = ['quantity_per_meter'=>'decimal:4','waste_rate'=>'decimal:2'];

    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }

    protected static function newFactory()
    {
        return \Database\Factories\BomLineFactory::new();
    }
}
