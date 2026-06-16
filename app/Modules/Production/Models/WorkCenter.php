<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * [PRODUCTION] Centre de travail (work center) — unité de capacité/coût.
 * Regroupe une machine + un coût horaire + une capacité journalière.
 * Socle des gammes opératoires (routings) et de la planification.
 */
class WorkCenter extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'machine_id', 'code', 'name',
        'capacity_hours_per_day', 'cost_per_hour', 'efficiency_rate',
        'is_active', 'notes', 'created_by',
    ];

    protected $casts = [
        'capacity_hours_per_day' => 'decimal:2',
        'cost_per_hour'          => 'decimal:2',
        'efficiency_rate'        => 'decimal:2',
        'is_active'              => 'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }

    protected static function newFactory()
    {
        return \Database\Factories\WorkCenterFactory::new();
    }
}
