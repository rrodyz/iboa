<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    // ── Types de congés ────────────────────────────────────────────────────────

    public function indexTypes()
    {
        $company = Company::firstOrFail();
        $types   = LeaveType::where('company_id', $company->id)->orderBy('name')->get();
        return view('rh.conges.types', compact('types'));
    }

    public function storeType(Request $request)
    {
        $company = Company::firstOrFail();
        $data    = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:20'],
            'days_per_year'     => ['required', 'numeric', 'min:0', 'max:365'],
            'is_paid'           => ['boolean'],
            'deduct_from_salary'=> ['boolean'],
            'color'             => ['nullable', 'string', 'max:20'],
        ]);

        LeaveType::create(array_merge($data, [
            'company_id'        => $company->id,
            'is_paid'           => $request->boolean('is_paid', true),
            'deduct_from_salary'=> $request->boolean('deduct_from_salary', false),
        ]));

        return back()->with('success', 'Type de congé créé.');
    }

    // ── Demandes de congés ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $company = Company::firstOrFail();
        $filters = $request->only(['employee_id', 'status', 'year']);

        $empIds = Employee::where('company_id', $company->id)->pluck('id');

        $query = LeaveRequest::with(['employee', 'leaveType', 'approvedBy'])
            ->whereIn('employee_id', $empIds)
            ->orderByDesc('start_date');

        if ($filters['employee_id'] ?? null) $query->where('employee_id', $filters['employee_id']);
        if ($filters['status'] ?? null)      $query->where('status', $filters['status']);
        if ($filters['year'] ?? null)        $query->whereYear('start_date', $filters['year']);

        $requests  = $query->paginate(20)->withQueryString();
        $employees = Employee::where('company_id', $company->id)->active()->orderBy('last_name')->get();
        $types     = LeaveType::where('company_id', $company->id)->where('is_active', true)->get();

        return view('rh.conges.index', compact('requests', 'employees', 'types', 'filters'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'gte:start_date'],
            'reason'        => ['nullable', 'string'],
        ]);

        // Calcul jours ouvrés (hors samedi/dimanche)
        $start = \Carbon\Carbon::parse($data['start_date']);
        $end   = \Carbon\Carbon::parse($data['end_date']);
        $days  = 0;
        $curr  = $start->copy();
        while ($curr->lte($end)) {
            if (!$curr->isWeekend()) $days++;
            $curr->addDay();
        }

        LeaveRequest::create(array_merge($data, [
            'days'       => $days,
            'status'     => 'en_attente',
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', "Demande de congé créée ({$days} jour(s) ouvré(s)).");
    }

    public function approve(LeaveRequest $leave)
    {
        if ($leave->status !== 'en_attente') {
            return back()->with('error', 'Cette demande ne peut plus être approuvée.');
        }

        DB::transaction(function () use ($leave) {
            $leave->update([
                'status'      => 'approuve',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Mettre à jour le solde
            $year = $leave->start_date->year;
            LeaveBalance::firstOrCreate(
                ['employee_id'    => $leave->employee_id,
                 'leave_type_id'  => $leave->leave_type_id,
                 'year'           => $year],
                ['entitled_days'  => $leave->leaveType->days_per_year]
            )->increment('taken_days', $leave->days);
        });

        return back()->with('success', 'Congé approuvé et solde mis à jour.');
    }

    public function refuse(LeaveRequest $leave, Request $request)
    {
        $request->validate(['notes' => ['nullable', 'string']]);
        if ($leave->status !== 'en_attente') {
            return back()->with('error', 'Cette demande ne peut plus être modifiée.');
        }
        $leave->update([
            'status'      => 'refuse',
            'notes'       => $request->notes,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return back()->with('success', 'Demande refusée.');
    }

    // ── Soldes ────────────────────────────────────────────────────────────────

    public function balances(Request $request)
    {
        $company   = Company::firstOrFail();
        $year      = $request->integer('year', now()->year);
        $employees = Employee::with(['leaveBalances' => fn($q) => $q->where('year', $year)->with('leaveType')])
            ->where('company_id', $company->id)
            ->active()
            ->orderBy('last_name')
            ->get();

        $types = LeaveType::where('company_id', $company->id)->where('is_active', true)->get();

        return view('rh.conges.balances', compact('employees', 'types', 'year'));
    }
}
