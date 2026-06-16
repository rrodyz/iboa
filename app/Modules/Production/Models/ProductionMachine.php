<?php
namespace App\Modules\Production\Models;
use App\Models\Company;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionMachine extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = ['company_id','code','name','type','hourly_cost','status','maintenance_frequency_days','is_active','notes','created_by'];

    public function maintenances(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(MachineMaintenance::class, 'machine_id'); }
    protected $casts = ['hourly_cost'=>'integer','is_active'=>'boolean'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function lines(): HasMany { return $this->hasMany(ProductionLine::class, 'machine_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionMachineFactory::new();
    }
}
