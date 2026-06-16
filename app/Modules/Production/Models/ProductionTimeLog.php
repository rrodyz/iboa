<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [PRODUCTION ↔ RH] Pointage du temps passé par un opérateur sur un OF.
 * Source du coût de main-d'œuvre RÉEL (vs estimation nomenclature).
 */
class ProductionTimeLog extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'production_order_id', 'employee_id',
        'hours', 'hourly_cost', 'labor_cost', 'is_overtime', 'entry_date', 'notes', 'created_by',
    ];

    protected $casts = [
        'hours'       => 'decimal:2',
        'hourly_cost' => 'decimal:2',
        'labor_cost'  => 'integer',
        'is_overtime' => 'boolean',
        'entry_date'  => 'date',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
