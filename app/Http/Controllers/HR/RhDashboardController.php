<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use Illuminate\Support\Facades\DB;

class RhDashboardController extends Controller
{
    public function index()
    {
        $company = currentCompany();

        // ─── Effectifs ─────────────────────────────────────────────────────────
        $totalActif    = Employee::where('company_id', $company->id)->where('status', 'actif')->count();
        $totalSuspendu = Employee::where('company_id', $company->id)->where('status', 'suspendu')->count();
        $totalSorties  = Employee::where('company_id', $company->id)->whereIn('status', ['licencie','demissionne'])->count();

        // ─── Masse salariale (dernier bulletin validé ou payé) ─────────────────
        $lastRun = PayrollRun::where('company_id', $company->id)
            ->whereIn('status', ['valide', 'paye', 'calcule'])
            ->orderByDesc('period_year')->orderByDesc('period_month')
            ->first();

        // ─── Évolution masse salariale (12 derniers mois) ──────────────────────
        $evolution = PayrollRun::where('company_id', $company->id)
            ->whereIn('status', ['valide', 'paye'])
            ->orderByDesc('period_year')->orderByDesc('period_month')
            ->limit(12)
            ->get(['period_month', 'period_year', 'total_brut', 'total_net', 'employee_count'])
            ->reverse()->values();

        // ─── Répartition par département (dernier run) ─────────────────────────
        $byDept = [];
        if ($lastRun) {
            $byDept = DB::table('payroll_items')
                ->where('payroll_run_id', $lastRun->id)
                ->select('department_name',
                    DB::raw('COUNT(*) as nb'),
                    DB::raw('SUM(salaire_brut) as brut'),
                    DB::raw('SUM(salaire_net)  as net')
                )
                ->groupBy('department_name')
                ->orderByDesc('brut')
                ->get();
        }

        // ─── Congés en attente ─────────────────────────────────────────────────
        $empIds      = Employee::where('company_id', $company->id)->pluck('id');
        $pendingLeaves = LeaveRequest::with(['employee','leaveType'])
            ->whereIn('employee_id', $empIds)
            ->where('status', 'en_attente')
            ->orderBy('start_date')
            ->take(10)
            ->get();

        // ─── Avances en attente ────────────────────────────────────────────────
        $pendingAdvances = SalaryAdvance::with('employee')
            ->where('company_id', $company->id)
            ->where('status', 'en_attente')
            ->orderByDesc('advance_date')
            ->take(10)
            ->get();

        // ─── Répartition par catégorie ─────────────────────────────────────────
        $byCategory = Employee::where('company_id', $company->id)
            ->where('status', 'actif')
            ->select('category', DB::raw('COUNT(*) as nb'))
            ->groupBy('category')
            ->pluck('nb', 'category');

        return view('rh.dashboard', compact(
            'totalActif', 'totalSuspendu', 'totalSorties',
            'lastRun', 'evolution', 'byDept',
            'pendingLeaves', 'pendingAdvances', 'byCategory'
        ));
    }
}
