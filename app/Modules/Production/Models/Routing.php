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

/** [PRODUCTION] Gamme opératoire (séquence d'opérations d'une nomenclature). */
class Routing extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = ['company_id', 'bill_of_material_id', 'code', 'name', 'is_active', 'notes', 'created_by'];
    protected $casts = ['is_active' => 'boolean'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function operations(): HasMany { return $this->hasMany(RoutingOperation::class)->orderBy('sequence'); }

    protected static function newFactory() { return \Database\Factories\RoutingFactory::new(); }
}
