<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION] Work Order : opération d'un OF (instance de gamme). */
class ProductionOrderOperation extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'production_order_id', 'routing_operation_id', 'work_center_id', 'operator_id',
        'sequence', 'name', 'planned_minutes', 'real_minutes', 'status', 'started_at', 'ended_at', 'created_by',
    ];
    protected $casts = [
        'planned_minutes' => 'decimal:2', 'real_minutes' => 'decimal:2',
        'started_at' => 'datetime', 'ended_at' => 'datetime',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function routingOperation(): BelongsTo { return $this->belongsTo(RoutingOperation::class); }
    public function workCenter(): BelongsTo { return $this->belongsTo(WorkCenter::class); }
    public function operator(): BelongsTo { return $this->belongsTo(Employee::class, 'operator_id'); }

    public function statusLabel(): string
    {
        return match ($this->status) { 'pending' => 'À faire', 'in_progress' => 'En cours', 'done' => 'Terminée', default => $this->status };
    }

    protected static function newFactory() { return \Database\Factories\ProductionOrderOperationFactory::new(); }
}
