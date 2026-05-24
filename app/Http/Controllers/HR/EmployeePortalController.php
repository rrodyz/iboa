<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * [RH-PRO] Portail self-service employé.
 *
 * L'employé ne voit QUE ses propres données.
 * Accès conditionné à : Auth::user()->employee()->exists().
 */
class EmployeePortalController extends Controller
{
    // ─── Résoudre l'employé connecté ──────────────────────────────────────────

    private function myEmployee(): ?Employee
    {
        return Employee::where('user_id', Auth::id())->with([
            'company', 'department', 'activeContract',
        ])->first();
    }

    // ─── Tableau de bord personnel ────────────────────────────────────────────

    public function dashboard()
    {
        $employee = $this->myEmployee();
        if (! $employee) {
            return view('rh.portail.no-account');
        }

        // Dernier bulletin
        $lastBulletin = PayrollItem::where('employee_id', $employee->id)
            ->with('payrollRun')
            ->whereHas('payrollRun', fn($q) => $q->whereIn('status', ['valide', 'paye']))
            ->orderByDesc('created_at')
            ->first();

        // Soldes congés
        $leaveBalances = $employee->leaveBalances()->with('leaveType')->get();

        // Demandes récentes
        $recentLeaves = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Prêt actif
        $activeLoan = $employee->activeLoans()->first();

        return view('rh.portail.dashboard', compact(
            'employee', 'lastBulletin', 'leaveBalances', 'recentLeaves', 'activeLoan'
        ));
    }

    // ─── Bulletins de paie ────────────────────────────────────────────────────

    public function bulletins()
    {
        $employee = $this->myEmployee();
        if (! $employee) return view('rh.portail.no-account');

        $bulletins = PayrollItem::where('employee_id', $employee->id)
            ->with('payrollRun')
            ->whereHas('payrollRun', fn($q) => $q->whereIn('status', ['valide', 'paye']))
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('rh.portail.bulletins', compact('employee', 'bulletins'));
    }

    /** Télécharger son propre bulletin PDF */
    public function bulletinPdf(PayrollItem $item)
    {
        $employee = $this->myEmployee();
        abort_if(! $employee || $item->employee_id !== $employee->id, 403);

        $run = $item->payrollRun;
        abort_if(! in_array($run->status, ['valide', 'paye']), 403);

        $item->load(['employee.department', 'employee.activeContract', 'payrollRun.company']);
        $company = $run->company;

        $pdf = Pdf::loadView('rh.pdf.bulletin', compact('item', 'run', 'company'))
                  ->setPaper('a4');

        $filename = "bulletin-{$run->period_year}-{$run->period_month}-{$employee->matricule}.pdf";
        return $pdf->download($filename);
    }

    // ─── Congés ───────────────────────────────────────────────────────────────

    public function conges()
    {
        $employee = $this->myEmployee();
        if (! $employee) return view('rh.portail.no-account');

        $leaveBalances = $employee->leaveBalances()->with('leaveType')->get();
        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->with('leaveType')
            ->orderByDesc('created_at')
            ->paginate(15);
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();

        return view('rh.portail.conges', compact('employee', 'leaveBalances', 'leaveRequests', 'leaveTypes'));
    }

    /** Soumettre une demande de congé */
    public function storeConge(Request $request)
    {
        $employee = $this->myEmployee();
        abort_if(! $employee, 403);

        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date'    => ['required', 'date', 'after_or_equal:today'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'reason'        => ['nullable', 'string', 'max:500'],
        ]);

        LeaveRequest::create(array_merge($validated, [
            'employee_id' => $employee->id,
            'company_id'  => $employee->company_id,
            'status'      => 'en_attente',
            'created_by'  => Auth::id(),
        ]));

        return back()->with('success', 'Demande de congé soumise avec succès.');
    }

    // ─── Documents personnels ─────────────────────────────────────────────────

    public function documents()
    {
        $employee = $this->myEmployee();
        if (! $employee) return view('rh.portail.no-account');

        $documents = $employee->documents()->orderByDesc('created_at')->get();

        return view('rh.portail.documents', compact('employee', 'documents'));
    }
}
