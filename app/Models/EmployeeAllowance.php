<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAllowance extends Model
{
    protected $fillable = [
        'employee_id', 'payroll_allowance_type_id', 'pay_rubric_id',
        'amount', 'start_date', 'end_date', 'is_active', 'notes',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function type(): BelongsTo     { return $this->belongsTo(PayrollAllowanceType::class, 'payroll_allowance_type_id'); }
    public function rubric(): BelongsTo   { return $this->belongsTo(PayRubric::class, 'pay_rubric_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
