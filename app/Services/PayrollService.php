<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\PayrollVariable;
use App\Models\SalaryAdvance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [RH-PAIE] Service de calcul de paie — normes Burkina Faso (CNSS + IUTS).
 *
 * Les taux (CNSS, IUTS) sont désormais lus depuis la table payroll_settings
 * pour rendre le moteur entièrement paramétrable via l'interface.
 * Les constantes ci-dessous restent comme FALLBACK si la table est vide.
 *
 * ─── Cotisations CNSS ─────────────────────────────────────────────────────────
 *   Employé  :  5,5 % du salaire brut plafonné à 650 000 FCFA/mois
 *   Patronal : 16,0 % du salaire brut plafonné à 650 000 FCFA/mois
 *
 * ─── IUTS ────────────────────────────────────────────────────────────────────
 *   Base : Salaire brut - CNSS employé
 *   Méthode : quotient familial (base / nb_parts → barème × nb_parts)
 */
class PayrollService
{
    // ─── Constantes de fallback (si payroll_settings non configuré) ───────────
    const CNSS_EMPLOYEE_RATE = 5.5;
    const CNSS_EMPLOYER_RATE = 16.0;
    const CNSS_CEILING       = 650_000;
    const WORK_DAYS_MONTH    = 26;
    const WORK_HOURS_DAY     = 8;
    const IUTS_BRACKETS = [
        [25_000,       0],
        [40_000,      12],
        [60_000,      17],
        [80_000,      22],
        [120_000,     27],
        [PHP_INT_MAX, 33],
    ];

    /** Paramètres chargés depuis la DB pour la session de calcul. */
    private ?PayrollSetting $settings = null;

    // ─── Chargement des paramètres ───────────────────────────────────────────
    private function loadSettings(int $companyId): PayrollSetting
    {
        if (! $this->settings || $this->settings->company_id !== $companyId) {
            $this->settings = PayrollSetting::forCompany($companyId);
        }
        return $this->settings;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API publique
    // ─────────────────────────────────────────────────────────────────────────

    /** Crée un bulletin vide (brouillon). */
    public function createRun(array $data): PayrollRun
    {
        $company = Company::firstOrFail();

        if (PayrollRun::where('company_id', $company->id)
            ->where('period_month', $data['period_month'])
            ->where('period_year',  $data['period_year'])
            ->exists()
        ) {
            throw new \RuntimeException(
                "Un bulletin de paie existe déjà pour {$data['period_month']}/{$data['period_year']}."
            );
        }

        return PayrollRun::create([
            'company_id'     => $company->id,
            'fiscal_year_id' => $company->current_fiscal_year_id,
            'period_month'   => $data['period_month'],
            'period_year'    => $data['period_year'],
            'status'         => 'brouillon',
            'notes'          => $data['notes'] ?? null,
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * Calcule la paie de tous les employés actifs.
     * Intègre les variables mensuelles (HS, absences, avances) saisies.
     */
    public function calculate(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            if (!$run->isEditable()) {
                throw new \RuntimeException('Ce bulletin est déjà validé et ne peut plus être recalculé.');
            }

            // Charger les paramètres de paie depuis la DB
            $this->loadSettings($run->company_id);

            // Supprimer les anciens calculs
            $run->items()->delete();

            // Charger les employés + leurs données
            $employees = Employee::with([
                    'activeContract',
                    'allowances.type',
                    'department',
                ])
                ->where('company_id', $run->company_id)
                ->where('status', 'actif')
                ->whereHas('activeContract')
                ->orderBy('last_name')
                ->get();

            // Variables mensuelles déjà saisies pour ce run (groupées par employee_id)
            $variables = PayrollVariable::where('payroll_run_id', $run->id)
                ->get()
                ->groupBy('employee_id');

            // Cumuls YTD des runs précédents du même exercice (même year)
            $ytdByEmployee = $this->getYtdCumuls($run);

            $totals = [
                'total_brut'          => 0,
                'total_cnss_employee' => 0,
                'total_cnss_employer' => 0,
                'total_iuts'          => 0,
                'total_net'           => 0,
                'employee_count'      => 0,
            ];

            foreach ($employees as $employee) {
                $empVars = $variables->get($employee->id, collect());
                $ytd     = $ytdByEmployee[$employee->id] ?? ['brut' => 0, 'cnss' => 0, 'iuts' => 0, 'net' => 0];

                $item = $this->calculateItem($employee, $run, $empVars, $ytd);
                $run->items()->create($item);

                $totals['total_brut']          += $item['salaire_brut'];
                $totals['total_cnss_employee'] += $item['cnss_employee'];
                $totals['total_cnss_employer'] += $item['cnss_employer'];
                $totals['total_iuts']          += $item['iuts_amount'];
                $totals['total_net']           += $item['salaire_net'];
                $totals['employee_count']++;
            }

            // Marquer les avances approuvées comme récupérées
            $this->recoverAdvances($run);

            $run->update(array_merge($totals, ['status' => 'calcule']));
            return $run->fresh('items.employee');
        });
    }

    /** Valide le bulletin (statut : valide). */
    public function validate(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            $run = PayrollRun::lockForUpdate()->findOrFail($run->id);

            if ($run->status !== 'calcule') {
                throw new \RuntimeException('Seuls les bulletins calculés peuvent être validés.');
            }

            $run->update([
                'status'       => 'valide',
                'validated_by' => Auth::id(),
                'validated_at' => now(),
            ]);

            return $run->fresh();
        });
    }

    /** Marque comme payé. */
    public function markPaid(PayrollRun $run, string $paidAt): PayrollRun
    {
        if ($run->status !== 'valide') {
            throw new \RuntimeException('Seuls les bulletins validés peuvent être marqués comme payés.');
        }
        $run->update(['status' => 'paye', 'paid_at' => $paidAt]);
        return $run->fresh();
    }

    /** Simulation individuelle sans enregistrement. */
    public function simulate(Employee $employee, ?Collection $variables = null): array
    {
        $this->loadSettings($employee->company_id);
        return $this->calculateItem($employee, null, $variables ?? collect(), ['brut'=>0,'cnss'=>0,'iuts'=>0,'net'=>0]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calcul cœur
    // ─────────────────────────────────────────────────────────────────────────

    private function calculateItem(
        Employee $employee,
        ?PayrollRun $run,
        Collection $variables,
        array $ytd
    ): array {
        $contract   = $employee->activeContract;
        $baseSalary = $contract ? (int) $contract->base_salary : 0;

        // ─── Jours travaillés (depuis les variables si absence) ───────────────
        $absenceDays = 0;
        $absenceVars = $variables->filter(fn($v) => in_array($v->type, ['absence_injust','absence_maladie','absence_cp']));
        foreach ($absenceVars as $v) {
            $absenceDays += $v->qty;
        }
        // Utiliser les paramètres de la DB (avec fallback aux constantes)
        $workDays  = $this->settings?->work_days_month ?? self::WORK_DAYS_MONTH;
        $workHours = $this->settings?->work_hours_day  ?? self::WORK_HOURS_DAY;

        $workedDays = max(0, $workDays - $absenceDays);
        $totalDays  = $workDays;

        // ─── Salaire de base (proratisé si absence) ───────────────────────────
        $proratedBase = $absenceDays > 0
            ? (int) round($baseSalary * $workedDays / $totalDays)
            : $baseSalary;

        // ─── Déduction pour absences ──────────────────────────────────────────
        $dailyRate    = $totalDays > 0 ? $baseSalary / $totalDays : 0;
        $absenceAmount= (int) round($dailyRate * $absenceDays);

        // ─── Taux horaire pour HS ─────────────────────────────────────────────
        $hourlyRate = $totalDays > 0 && $workHours > 0
            ? $baseSalary / ($totalDays * $workHours)
            : 0;

        // ─── Heures supplémentaires ───────────────────────────────────────────
        $hs25Hours = $hs25Amount = 0;
        $hs50Hours = $hs50Amount = 0;
        $hsNuitHours = $hsNuitAmount = 0;

        $hsRate25   = 1 + (($this->settings?->hs_rate_25   ?? 25.0)  / 100);
        $hsRate50   = 1 + (($this->settings?->hs_rate_50   ?? 50.0)  / 100);
        $hsRateNuit = 1 + (($this->settings?->hs_rate_nuit ?? 75.0)  / 100);

        foreach ($variables->filter(fn($v) => $v->type === 'hs_25') as $v) {
            $hs25Hours  += $v->qty;
            $hs25Amount += $v->amount ?: (int) round($hourlyRate * $v->qty * $hsRate25);
        }
        foreach ($variables->filter(fn($v) => $v->type === 'hs_50') as $v) {
            $hs50Hours  += $v->qty;
            $hs50Amount += $v->amount ?: (int) round($hourlyRate * $v->qty * $hsRate50);
        }
        foreach ($variables->filter(fn($v) => $v->type === 'hs_nuit') as $v) {
            $hsNuitHours  += $v->qty;
            $hsNuitAmount += $v->amount ?: (int) round($hourlyRate * $v->qty * $hsRateNuit);
        }

        // ─── Primes et indemnités FIXES ───────────────────────────────────────
        $activeAllowances = $employee->allowances->filter(fn($a) => $a->is_active);

        $taxableAllowances    = (int) $activeAllowances->filter(fn($a) =>  ($a->type?->is_taxable ?? true))->sum('amount');
        $nonTaxableAllowances = (int) $activeAllowances->filter(fn($a) => !($a->type?->is_taxable ?? true))->sum('amount');

        // ─── Primes/gains ponctuels (variables) ──────────────────────────────
        $primesExcept  = 0;
        $autresGains   = 0;

        foreach ($variables->filter(fn($v) => $v->is_gain && !in_array($v->type,['hs_25','hs_50','hs_nuit'])) as $v) {
            if ($v->type === 'prime_exceptionnelle') {
                $primesExcept += (int) abs($v->amount);
            } elseif ($v->is_taxable) {
                $taxableAllowances += (int) abs($v->amount);
            } else {
                $autresGains += (int) abs($v->amount);
            }
        }

        // ─── Retenues ponctuelles ─────────────────────────────────────────────
        $avancesDeductions = 0;
        $autresRetenues    = 0;

        foreach ($variables->filter(fn($v) => !$v->is_gain && !in_array($v->type,['absence_injust','absence_maladie','absence_cp'])) as $v) {
            if ($v->type === 'avance_deduction') {
                $avancesDeductions += (int) abs($v->amount);
            } else {
                $autresRetenues += (int) abs($v->amount);
            }
        }

        // ─── Salaire BRUT ─────────────────────────────────────────────────────
        // = base proratisé + HS + primes taxables + primes exceptionnelles
        $salaireBrut = $proratedBase
            + $hs25Amount + $hs50Amount + $hsNuitAmount
            + $taxableAllowances
            + $primesExcept;

        // ─── CNSS ─────────────────────────────────────────────────────────────
        $cnssPlafond  = $this->settings?->cnss_ceiling       ?? self::CNSS_CEILING;
        $cnssEmpRate  = $this->settings?->cnss_employee_rate ?? self::CNSS_EMPLOYEE_RATE;
        $cnssPatRate  = $this->settings?->cnss_employer_rate ?? self::CNSS_EMPLOYER_RATE;

        $cnssBase     = min($salaireBrut, $cnssPlafond);
        $cnssEmployee = (int) round($cnssBase * $cnssEmpRate / 100);
        $cnssEmployer = (int) round($cnssBase * $cnssPatRate / 100);

        // ─── IUTS ─────────────────────────────────────────────────────────────
        $salaireImposable = max(0, $salaireBrut - $cnssEmployee);
        $nbParts          = $this->settings
            ? $this->settings->computeNbParts($employee->family_status ?? 'celibataire', $employee->nb_children ?? 0)
            : $employee->nb_parts;
        $iuts             = $this->settings
            ? $this->settings->computeIuts($salaireImposable, $nbParts)
            : $this->computeIuts($salaireImposable, $nbParts);

        // ─── Net à payer ───────────────────────────────────────────────────────
        $salaireNet = $salaireBrut
            + $nonTaxableAllowances
            + $autresGains
            - $cnssEmployee
            - $iuts
            - $avancesDeductions
            - $autresRetenues;

        $coutEmployeur = $salaireBrut + $cnssEmployer;

        // ─── Cumuls YTD ───────────────────────────────────────────────────────
        $cumulBrutYtd = $ytd['brut'] + $salaireBrut;
        $cumulCnssYtd = $ytd['cnss'] + $cnssEmployee;
        $cumulIutsYtd = $ytd['iuts'] + $iuts;
        $cumulNetYtd  = $ytd['net']  + max(0, $salaireNet);

        return [
            // ─ Snapshot
            'employee_id'                  => $employee->id,
            'employee_name'                => $employee->full_name,
            'employee_matricule'           => $employee->matricule,
            'job_title'                    => $employee->job_title,
            'department_name'              => $employee->department?->name,
            // ─ Base
            'base_salary'                  => $proratedBase,
            'worked_days'                  => $workedDays,
            'total_days'                   => $totalDays,
            // ─ Primes fixes
            'total_allowances_taxable'     => $taxableAllowances,
            'total_allowances_non_taxable' => $nonTaxableAllowances,
            // ─ Heures supplémentaires
            'hs_25_hours'                  => $hs25Hours,
            'hs_25_amount'                 => $hs25Amount,
            'hs_50_hours'                  => $hs50Hours,
            'hs_50_amount'                 => $hs50Amount,
            'hs_nuit_hours'                => $hsNuitHours,
            'hs_nuit_amount'               => $hsNuitAmount,
            // ─ Absences
            'absence_days'                 => $absenceDays,
            'absence_amount'               => $absenceAmount,
            // ─ Ponctuel
            'primes_exceptionnelles'       => $primesExcept,
            'autres_gains'                 => $autresGains,
            'avances_deductions'           => $avancesDeductions,
            'autres_retenues'              => $autresRetenues,
            // ─ Cotisations
            'salaire_brut'                 => $salaireBrut,
            'cnss_base'                    => $cnssBase,
            'cnss_employee'                => $cnssEmployee,
            'cnss_employer'                => $cnssEmployer,
            'salaire_imposable'            => $salaireImposable,
            'nb_parts'                     => $nbParts,
            'iuts_amount'                  => $iuts,
            'salaire_net'                  => max(0, $salaireNet),
            'cout_employeur'               => $coutEmployeur,
            // ─ YTD
            'cumul_brut_ytd'               => $cumulBrutYtd,
            'cumul_cnss_ytd'               => $cumulCnssYtd,
            'cumul_iuts_ytd'               => $cumulIutsYtd,
            'cumul_net_ytd'                => $cumulNetYtd,
        ];
    }

    /**
     * Calcule l'IUTS par la méthode du quotient familial.
     */
    private function computeIuts(int $imposable, float $parts): int
    {
        if ($imposable <= 0 || $parts <= 0) return 0;

        $quotient = $imposable / $parts;
        $tax      = 0.0;
        $prev     = 0;

        foreach (self::IUTS_BRACKETS as [$limit, $rate]) {
            if ($quotient <= $prev) break;
            $tranche = min($quotient, $limit) - $prev;
            $tax    += $tranche * $rate / 100;
            $prev    = $limit;
            if ($quotient <= $limit) break;
        }

        return (int) round($tax * $parts);
    }

    /**
     * Cumuls YTD : somme des bulletins validés/payés du même exercice
     * (même année civile), antérieurs au mois en cours.
     */
    private function getYtdCumuls(PayrollRun $run): array
    {
        $rows = DB::table('payroll_items')
            ->join('payroll_runs', 'payroll_items.payroll_run_id', '=', 'payroll_runs.id')
            ->where('payroll_runs.company_id', $run->company_id)
            ->where('payroll_runs.period_year', $run->period_year)
            ->where('payroll_runs.period_month', '<', $run->period_month)
            ->whereIn('payroll_runs.status', ['valide', 'paye'])
            ->select(
                'payroll_items.employee_id',
                DB::raw('SUM(salaire_brut)   as brut'),
                DB::raw('SUM(cnss_employee)  as cnss'),
                DB::raw('SUM(iuts_amount)    as iuts'),
                DB::raw('SUM(salaire_net)    as net')
            )
            ->groupBy('payroll_items.employee_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->employee_id] = [
                'brut' => (int) $row->brut,
                'cnss' => (int) $row->cnss,
                'iuts' => (int) $row->iuts,
                'net'  => (int) $row->net,
            ];
        }
        return $result;
    }

    /**
     * Marque les avances approuvées comme récupérées dans ce bulletin.
     */
    private function recoverAdvances(PayrollRun $run): void
    {
        // Les avances récupérées sont saisies manuellement via PayrollVariable (avance_deduction).
        // On marque ici les SalaryAdvance correspondantes comme remboursées.
        $empIds = $run->items()->pluck('employee_id');

        SalaryAdvance::where('company_id', $run->company_id)
            ->whereIn('employee_id', $empIds)
            ->where('status', 'approuve')
            ->whereNull('recovered_in_run_id')
            ->whereHas('employee', function ($q) use ($run) {
                // Vérifie que la variable avance_deduction existe pour cet employé dans ce run
                $q->whereHas('payrollVariables', fn($pv) =>
                    $pv->where('payroll_run_id', $run->id)->where('type', 'avance_deduction')
                );
            })
            ->update([
                'status'               => 'rembourse',
                'recovered_in_run_id'  => $run->id,
                'recovered_at'         => now()->toDateString(),
            ]);
    }
}
