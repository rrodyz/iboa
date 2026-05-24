<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [RH-PRO] Remboursement mensuel d'un prêt salarié.
 */
class EmployeeLoanPayment extends Model
{
    protected $fillable = [
        'employee_loan_id', 'payroll_run_id',
        'period_month', 'period_year',
        'amount', 'balance_after', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year'  => 'integer',
        'amount'       => 'integer',
        'balance_after'=> 'integer',
    ];

    public function loan(): BelongsTo       { return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id'); }
    public function payrollRun(): BelongsTo { return $this->belongsTo(PayrollRun::class); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
}
