<?php
namespace App\Modules\Production\Models;
use App\Models\Company;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionLine extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = ['company_id','machine_id','code','name','is_active','created_by'];
    protected $casts = ['is_active'=>'boolean'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\ProductionLineFactory::new();
    }
}
