<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeContract;
use App\Models\PayrollAllowanceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    // ─── Employés ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $filters = $request->only(['department_id', 'status', 'category', 'search']);
        $company = Company::firstOrFail();

        $query = Employee::with(['department', 'activeContract'])
            ->where('company_id', $company->id)
            ->orderBy('last_name');

        if ($filters['department_id'] ?? null) {
            $query->where('department_id', $filters['department_id']);
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['category'] ?? null) {
            $query->where('category', $filters['category']);
        }
        if ($filters['search'] ?? null) {
            $search = '%' . $filters['search'] . '%';
            $query->where(fn($q) => $q
                ->where('last_name', 'like', $search)
                ->orWhere('first_name', 'like', $search)
                ->orWhere('matricule', 'like', $search)
                ->orWhere('cnss_number', 'like', $search)
            );
        }

        $employees   = $query->paginate(20)->withQueryString();
        $departments = Department::where('company_id', $company->id)->active()->orderBy('name')->get();

        return view('rh.employes.index', compact('employees', 'filters', 'departments'));
    }

    public function create()
    {
        $company     = Company::firstOrFail();
        $departments = Department::where('company_id', $company->id)->active()->orderBy('name')->get();
        $nextMatricule = $this->nextMatricule($company->id);

        return view('rh.employes.create', compact('departments', 'nextMatricule'));
    }

    public function store(Request $request)
    {
        $company = Company::firstOrFail();

        $data = $request->validate([
            'last_name'          => ['required', 'string', 'max:100'],
            'first_name'         => ['required', 'string', 'max:100'],
            'gender'             => ['required', 'in:M,F'],
            'birth_date'         => ['nullable', 'date'],
            'birth_place'        => ['nullable', 'string', 'max:100'],
            'nationality'        => ['nullable', 'string', 'max:60'],
            'cin_number'         => ['nullable', 'string', 'max:30'],
            'cnss_number'        => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:150'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'address'            => ['nullable', 'string'],
            'city'               => ['nullable', 'string', 'max:80'],
            'department_id'      => ['nullable', 'exists:departments,id'],
            'job_title'          => ['nullable', 'string', 'max:100'],
            'category'           => ['required', 'in:cadre,agent_maitrise,employe,ouvrier'],
            'hiring_date'        => ['nullable', 'date'],
            'family_status'      => ['required', 'in:celibataire,marie,veuf,divorce'],
            'nb_children'        => ['required', 'integer', 'min:0', 'max:20'],
            // Banque
            'payment_mode'       => ['nullable', 'in:virement,especes,cheque,mobile'],
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'bank_account'       => ['nullable', 'string', 'max:50'],
            'bank_code'          => ['nullable', 'string', 'max:5'],
            'bank_branch'        => ['nullable', 'string', 'max:5'],
            'bank_account_number'=> ['nullable', 'string', 'max:11'],
            'bank_rib_key'       => ['nullable', 'string', 'max:2'],
            // Contrat initial
            'contract_type'      => ['required', 'in:CDI,CDD,stage,consultant'],
            'contract_start'     => ['required', 'date'],
            'contract_end'       => ['nullable', 'date', 'after:contract_start'],
            'base_salary'        => ['required', 'integer', 'min:0'],
        ]);

        $employee = DB::transaction(function () use ($data, $company) {
            $employee = Employee::create([
                'company_id'         => $company->id,
                'matricule'          => $this->nextMatricule($company->id),
                'last_name'          => $data['last_name'],
                'first_name'         => $data['first_name'],
                'gender'             => $data['gender'],
                'birth_date'         => $data['birth_date'] ?? null,
                'birth_place'        => $data['birth_place'] ?? null,
                'nationality'        => $data['nationality'] ?? 'Burkinabè',
                'cin_number'         => $data['cin_number'] ?? null,
                'cnss_number'        => $data['cnss_number'] ?? null,
                'email'              => $data['email'] ?? null,
                'phone'              => $data['phone'] ?? null,
                'address'            => $data['address'] ?? null,
                'city'               => $data['city'] ?? null,
                'department_id'      => $data['department_id'] ?? null,
                'job_title'          => $data['job_title'] ?? null,
                'category'           => $data['category'],
                'hiring_date'        => $data['hiring_date'] ?? $data['contract_start'],
                'status'             => 'actif',
                'family_status'      => $data['family_status'],
                'nb_children'        => $data['nb_children'],
                'payment_mode'       => $data['payment_mode'] ?? 'virement',
                'bank_name'          => $data['bank_name'] ?? null,
                'bank_account'       => $data['bank_account'] ?? null,
                'bank_code'          => $data['bank_code'] ?? null,
                'bank_branch'        => $data['bank_branch'] ?? null,
                'bank_account_number'=> $data['bank_account_number'] ?? null,
                'bank_rib_key'       => $data['bank_rib_key'] ?? null,
                'created_by'         => Auth::id(),
            ]);

            // Contrat initial
            EmployeeContract::create([
                'employee_id' => $employee->id,
                'type'        => $data['contract_type'],
                'start_date'  => $data['contract_start'],
                'end_date'    => $data['contract_end'] ?? null,
                'base_salary' => $data['base_salary'],
                'status'      => 'actif',
            ]);

            return $employee;
        });

        return redirect()
            ->route('rh.employes.show', $employee)
            ->with('success', "Employé {$employee->full_name} (matricule {$employee->matricule}) créé.");
    }

    public function show(Employee $employe)
    {
        $employe->load(['department', 'contracts' => fn($q) => $q->orderByDesc('start_date'), 'allowances.type']);
        $allowanceTypes = PayrollAllowanceType::active()->orderBy('name')->get();

        return view('rh.employes.show', compact('employe', 'allowanceTypes'));
    }

    public function edit(Employee $employe)
    {
        $company     = Company::firstOrFail();
        $departments = Department::where('company_id', $company->id)->active()->orderBy('name')->get();
        $employe->load(['activeContract', 'department']);

        return view('rh.employes.edit', compact('employe', 'departments'));
    }

    public function update(Request $request, Employee $employe)
    {
        $data = $request->validate([
            'last_name'          => ['required', 'string', 'max:100'],
            'first_name'         => ['required', 'string', 'max:100'],
            'gender'             => ['required', 'in:M,F'],
            'birth_date'         => ['nullable', 'date'],
            'birth_place'        => ['nullable', 'string', 'max:100'],
            'nationality'        => ['nullable', 'string', 'max:60'],
            'cin_number'         => ['nullable', 'string', 'max:30'],
            'cnss_number'        => ['nullable', 'string', 'max:30'],
            'email'              => ['nullable', 'email', 'max:150'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'address'            => ['nullable', 'string'],
            'city'               => ['nullable', 'string', 'max:80'],
            'department_id'      => ['nullable', 'exists:departments,id'],
            'job_title'          => ['nullable', 'string', 'max:100'],
            'category'           => ['required', 'in:cadre,agent_maitrise,employe,ouvrier'],
            'hiring_date'        => ['nullable', 'date'],
            'leave_date'         => ['nullable', 'date'],
            'status'             => ['required', 'in:actif,suspendu,licencie,demissionne'],
            'family_status'      => ['required', 'in:celibataire,marie,veuf,divorce'],
            'nb_children'        => ['required', 'integer', 'min:0', 'max:20'],
            'payment_mode'       => ['nullable', 'in:virement,especes,cheque,mobile'],
            'bank_name'          => ['nullable', 'string', 'max:100'],
            'bank_account'       => ['nullable', 'string', 'max:50'],
            'bank_code'          => ['nullable', 'string', 'max:5'],
            'bank_branch'        => ['nullable', 'string', 'max:5'],
            'bank_account_number'=> ['nullable', 'string', 'max:11'],
            'bank_rib_key'       => ['nullable', 'string', 'max:2'],
        ]);

        $employe->update($data);

        return redirect()
            ->route('rh.employes.show', $employe)
            ->with('success', 'Fiche employé mise à jour.');
    }

    public function destroy(Employee $employe)
    {
        if ($employe->payrollItems()->exists()) {
            return back()->with('error', 'Impossible de supprimer : cet employé a des bulletins de paie.');
        }
        $employe->delete();
        return redirect()->route('rh.employes.index')->with('success', 'Employé archivé.');
    }

    // ─── Contrats ─────────────────────────────────────────────────────────────

    public function storeContract(Request $request, Employee $employe)
    {
        $data = $request->validate([
            'type'        => ['required', 'in:CDI,CDD,stage,consultant'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after:start_date'],
            'base_salary' => ['required', 'integer', 'min:0'],
            'notes'       => ['nullable', 'string'],
        ]);

        // Clôturer l'ancien contrat actif
        $employe->contracts()->where('status', 'actif')->update(['status' => 'termine']);

        $employe->contracts()->create(array_merge($data, ['status' => 'actif']));

        return back()->with('success', 'Nouveau contrat enregistré.');
    }

    // ─── Primes / indemnités ──────────────────────────────────────────────────

    public function storeAllowance(Request $request, Employee $employe)
    {
        $data = $request->validate([
            'payroll_allowance_type_id' => ['required', 'exists:payroll_allowance_types,id'],
            'amount'                    => ['required', 'integer', 'min:0'],
            'start_date'                => ['required', 'date'],
            'end_date'                  => ['nullable', 'date', 'after:start_date'],
        ]);

        $employe->allowances()->create(array_merge($data, ['is_active' => true]));

        return back()->with('success', 'Prime / indemnité ajoutée.');
    }

    public function destroyAllowance(Employee $employe, EmployeeAllowance $allowance)
    {
        $allowance->delete();
        return back()->with('success', 'Prime supprimée.');
    }

    // ─── Départements ─────────────────────────────────────────────────────────

    public function departments(Request $request)
    {
        $company = Company::firstOrFail();
        $departments = Department::where('company_id', $company->id)->orderBy('name')->paginate(20);
        return view('rh.departments.index', compact('departments'));
    }

    public function storeDepartment(Request $request)
    {
        $company = Company::firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);
        Department::create(array_merge($data, ['company_id' => $company->id]));
        return back()->with('success', 'Département créé.');
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function nextMatricule(int $companyId): string
    {
        $prefix = 'EMP-' . now()->format('Y');
        $last = Employee::where('company_id', $companyId)
            ->where('matricule', 'like', $prefix . '-%')
            ->withTrashed()
            ->orderByDesc('id')
            ->value('matricule');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;
        return sprintf('%s-%04d', $prefix, $seq);
    }
}
