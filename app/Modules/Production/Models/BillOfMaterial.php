<?php
namespace App\Modules\Production\Models;
use App\Models\Company;
use App\Models\Product;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Nomenclature / recette d'un type de tôle bac. */
class BillOfMaterial extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'bills_of_materials';
    protected $fillable = [
        'company_id','product_id','scrap_product_id','defect_product_id','name','sheet_type','thickness','coil_width','usable_width',
        'standard_waste_rate','consumption_per_meter','machine_time_per_unit','labor_per_unit',
        'std_material_cost','std_labor_cost','std_machine_cost','std_overhead_cost',
        'is_active','notes','created_by',
    ];
    protected $casts = [
        'thickness'=>'decimal:2','coil_width'=>'decimal:1','usable_width'=>'decimal:1','standard_waste_rate'=>'decimal:2',
        'consumption_per_meter'=>'decimal:4','machine_time_per_unit'=>'decimal:2','labor_per_unit'=>'decimal:2','is_active'=>'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function scrapProduct(): BelongsTo { return $this->belongsTo(Product::class, 'scrap_product_id'); }
    public function defectProduct(): BelongsTo { return $this->belongsTo(Product::class, 'defect_product_id'); }
    public function lines(): HasMany { return $this->hasMany(BomLine::class); }
    public function routing(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Routing::class)->where('is_active', true); }

    protected static function newFactory()
    {
        return \Database\Factories\BillOfMaterialFactory::new();
    }
}
