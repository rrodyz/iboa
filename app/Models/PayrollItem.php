<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id',
        // ─ Salaire de base
        'base_salary', 'worked_days', 'total_days',
        // ─ Primes fixes
        'total_allowances_taxable', 'total_allowances_non_taxable',
        // ─ Heures supplémentaires
        'hs_25_hours', 'hs_25_amount',
        'hs_50_hours', 'hs_50_amount',
        'hs_nuit_hours', 'hs_nuit_amount',
        // ─ Absences
        'absence_days', 'absence_amount',
        // ─ Primes/retenues ponctuelles
        'primes_exceptionnelles', 'autres_gains',
        'avances_deductions', 'autres_retenues',
        // ─ Totaux cotisations
        'salaire_brut', 'cnss_base', 'cnss_employee', 'cnss_employer',
        'salaire_imposable', 'nb_parts', 'iuts_amount',
        'salaire_net', 'cout_employeur',
        // ─ Cumuls YTD
        'cumul_brut_ytd', 'cumul_cnss_ytd', 'cumul_iuts_ytd', 'cumul_net_ytd',
        // ─ Snapshot employé
        'employee_name', 'employee_matricule', 'job_title', 'department_name',
        'notes',
    ];

    protected $casts = [
        'base_salary'                  => 'integer',
        'worked_days'                  => 'integer',
        'total_days'                   => 'integer',
        'total_allowances_taxable'     => 'integer',
        'total_allowances_non_taxable' => 'integer',
        'hs_25_hours'                  => 'float',
        'hs_25_amount'                 => 'integer',
        'hs_50_hours'                  => 'float',
        'hs_50_amount'                 => 'integer',
        'hs_nuit_hours'                => 'float',
        'hs_nuit_amount'               => 'integer',
        'absence_days'                 => 'float',
        'absence_amount'               => 'integer',
        'primes_exceptionnelles'       => 'integer',
        'autres_gains'                 => 'integer',
        'avances_deductions'           => 'integer',
        'autres_retenues'              => 'integer',
        'salaire_brut'                 => 'integer',
        'cnss_base'                    => 'integer',
        'cnss_employee'                => 'integer',
        'cnss_employer'                => 'integer',
        'salaire_imposable'            => 'integer',
        'nb_parts'                     => 'float',
        'iuts_amount'                  => 'integer',
        'salaire_net'                  => 'integer',
        'cout_employeur'               => 'integer',
        'cumul_brut_ytd'               => 'integer',
        'cumul_cnss_ytd'               => 'integer',
        'cumul_iuts_ytd'               => 'integer',
        'cumul_net_ytd'                => 'integer',
    ];

    public function payrollRun(): BelongsTo  { return $this->belongsTo(PayrollRun::class); }
    public function employee(): BelongsTo    { return $this->belongsTo(Employee::class); }

    /** Taux moyen IUTS pour affichage bulletin */
    public function getTauxMoyenIutsAttribute(): float
    {
        return $this->salaire_imposable > 0
            ? round($this->iuts_amount / $this->salaire_imposable * 100, 2)
            : 0;
    }

    /** Total heures supplémentaires */
    public function getTotalHsAmountAttribute(): int
    {
        return $this->hs_25_amount + $this->hs_50_amount + $this->hs_nuit_amount;
    }
}
