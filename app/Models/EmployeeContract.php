<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContract extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'base_salary',
        'payroll_profile_id', 'status', 'notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'base_salary' => 'integer',
    ];

    public function employee(): BelongsTo       { return $this->belongsTo(Employee::class); }
    public function payrollProfile(): BelongsTo { return $this->belongsTo(PayrollProfile::class, 'payroll_profile_id'); }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'CDI'        => 'CDI – Contrat à durée indéterminée',
            'CDD'        => 'CDD – Contrat à durée déterminée',
            'stage'      => 'Stage',
            'consultant' => 'Consultant / Prestataire',
            default      => $this->type,
        };
    }

    public function scopeActive($q) { return $q->where('status', 'actif'); }
}
