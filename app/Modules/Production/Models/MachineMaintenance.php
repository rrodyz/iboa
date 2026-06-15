<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION] Intervention de maintenance machine (préventive / corrective). */
class MachineMaintenance extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'machine_id', 'type', 'title', 'status', 'planned_at',
        'started_at', 'ended_at', 'downtime_minutes', 'cost', 'operator_id', 'notes', 'created_by',
    ];
    protected $casts = [
        'planned_at' => 'date', 'started_at' => 'datetime', 'ended_at' => 'datetime',
        'downtime_minutes' => 'decimal:2', 'cost' => 'integer',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function operator(): BelongsTo { return $this->belongsTo(Employee::class, 'operator_id'); }

    public function typeLabel(): string { return $this->type === 'corrective' ? 'Corrective' : 'Préventive'; }
    public function statusLabel(): string { return match($this->status){ 'planifie'=>'Planifiée','en_cours'=>'En cours',default=>'Terminée' }; }

    protected static function newFactory() { return \Database\Factories\MachineMaintenanceFactory::new(); }
}
