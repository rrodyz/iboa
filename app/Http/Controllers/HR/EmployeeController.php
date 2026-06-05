<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeContract;
use App\Models\PayrollAllowanceType;
use App\Models\PayrollSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $company = currentCompany();

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

        // ── Indicateurs globaux — 1 seule requête avec agrégation conditionnelle ──
        $counts = Employee::where('company_id', $company->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'actif'     THEN 1 ELSE 0 END) as actif,
                SUM(CASE WHEN status = 'suspendu'  THEN 1 ELSE 0 END) as suspendu,
                SUM(CASE WHEN status IN ('licencie','demissionne') THEN 1 ELSE 0 END) as quitte
            ")
            ->first();

        $summary = [
            'total'    => (int) $counts->total,
            'actif'    => (int) $counts->actif,
            'suspendu' => (int) $counts->suspendu,
            'quitte'   => (int) $counts->quitte,
        ];

        return view('rh.employes.index', compact('employees', 'filters', 'departments', 'summary'));
    }

    public function create()
    {
        $company     = currentCompany();
        $departments = Department::where('company_id', $company->id)->active()->orderBy('name')->get();
        $nextMatricule = $this->nextMatricule($company->id);
        $payroll     = PayrollSetting::forCompany($company->id);

        $allowanceTypes = PayrollAllowanceType::active()->orderBy('name')->get(['id','name','code','is_taxable']);
        return view('rh.employes.create', compact('departments', 'nextMatricule', 'payroll', 'allowanceTypes'));
    }

    public function store(Request $request)
    {
        $company = currentCompany();

        // Validation des primes (facultatif à la création)
        $request->validate([
            'allowances'           => ['nullable', 'array'],
            'allowances.*.type_id' => ['required_with:allowances.*', 'exists:payroll_allowance_types,id'],
            'allowances.*.amount'  => ['required_with:allowances.*', 'integer', 'min:0'],
        ]);

        $data = $request->validate([
            'last_name'               => ['required', 'string', 'max:100'],
            'first_name'              => ['required', 'string', 'max:100'],
            'gender'                  => ['required', 'in:M,F'],
            'birth_date'              => ['nullable', 'date'],
            'birth_place'             => ['nullable', 'string', 'max:100'],
            'nationality'             => ['nullable', 'string', 'max:60'],
            'cin_number'              => ['nullable', 'string', 'max:30'],
            'cnss_number'             => ['nullable', 'string', 'max:30'],
            'email'                   => ['nullable', 'email', 'max:150'],
            'phone'                   => ['nullable', 'string', 'max:20'],
            'address'                 => ['nullable', 'string'],
            'city'                    => ['nullable', 'string', 'max:80'],
            'family_status'           => ['required', 'in:celibataire,marie,veuf,divorce'],
            'nb_children'             => ['required', 'integer', 'min:0', 'max:20'],
            'education_level'         => ['nullable', 'string', 'max:50'],
            'emergency_contact_name'  => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            // Affectation
            'department_id'           => ['nullable', 'exists:departments,id'],
            'job_title'               => ['nullable', 'string', 'max:100'],
            'fonction'                => ['nullable', 'string', 'max:100'],
            'category'                => ['required', 'in:cadre,agent_maitrise,employe,ouvrier'],
            'hiring_date'             => ['nullable', 'date'],
            // Banque
            'payment_mode'            => ['nullable', 'in:virement,especes,cheque,mobile'],
            'bank_name'               => ['nullable', 'string', 'max:100'],
            'bank_account'            => ['nullable', 'string', 'max:50'],
            'bank_code'               => ['nullable', 'string', 'max:5'],
            'bank_branch'             => ['nullable', 'string', 'max:5'],
            'bank_account_number'     => ['nullable', 'string', 'max:11'],
            'bank_rib_key'            => ['nullable', 'string', 'max:2'],
            // Contrat initial
            'contract_type'           => ['required', 'in:CDI,CDD,stage,consultant'],
            'contract_start'          => ['required', 'date'],
            'contract_end'            => ['nullable', 'date', 'after:contract_start'],
            'base_salary'             => ['required', 'integer', 'min:0'],
        ]);

        $employee = DB::transaction(function () use ($data, $company, $request) {
            $employee = Employee::create([
                'company_id'              => $company->id,
                'matricule'               => $this->nextMatricule($company->id),
                'last_name'               => $data['last_name'],
                'first_name'              => $data['first_name'],
                'gender'                  => $data['gender'],
                'birth_date'              => $data['birth_date'] ?? null,
                'birth_place'             => $data['birth_place'] ?? null,
                'nationality'             => $data['nationality'] ?? 'Burkinabè',
                'cin_number'              => $data['cin_number'] ?? null,
                'cnss_number'             => $data['cnss_number'] ?? null,
                'email'                   => $data['email'] ?? null,
                'phone'                   => $data['phone'] ?? null,
                'address'                 => $data['address'] ?? null,
                'city'                    => $data['city'] ?? null,
                'family_status'           => $data['family_status'],
                'nb_children'             => $data['nb_children'],
                'education_level'         => $data['education_level'] ?? null,
                'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'department_id'           => $data['department_id'] ?? null,
                'job_title'               => $data['job_title'] ?? null,
                'fonction'                => $data['fonction'] ?? null,
                'category'                => $data['category'],
                'hiring_date'             => $data['hiring_date'] ?? $data['contract_start'],
                'status'                  => 'actif',
                'payment_mode'            => $data['payment_mode'] ?? 'virement',
                'bank_name'               => $data['bank_name'] ?? null,
                'bank_account'            => $data['bank_account'] ?? null,
                'bank_code'               => $data['bank_code'] ?? null,
                'bank_branch'             => $data['bank_branch'] ?? null,
                'bank_account_number'     => $data['bank_account_number'] ?? null,
                'bank_rib_key'            => $data['bank_rib_key'] ?? null,
                'created_by'              => Auth::id(),
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

            // Primes & indemnités initiales
            foreach ($request->input('allowances', []) as $row) {
                if (empty($row['type_id']) || ($row['amount'] ?? 0) <= 0) continue;
                $employee->allowances()->create([
                    'payroll_allowance_type_id' => $row['type_id'],
                    'amount'     => (int) $row['amount'],
                    'start_date' => $data['contract_start'],
                    'is_active'  => true,
                ]);
            }

            return $employee;
        });

        return redirect()
            ->route('rh.employes.show', $employee)
            ->with('success', "Employé {$employee->full_name} (matricule {$employee->matricule}) créé.");
    }

    public function show(Employee $employe)
    {
        $employe->load([
            'department',
            'contracts'  => fn($q) => $q->orderByDesc('start_date'),
            'allowances.type',
            'documents'  => fn($q) => $q->orderByDesc('created_at'),
        ]);
        $allowanceTypes = PayrollAllowanceType::active()->orderBy('name')->get();
        $payroll        = PayrollSetting::forCompany(currentCompany()->id);

        return view('rh.employes.show', compact('employe', 'allowanceTypes', 'payroll'));
    }

    public function edit(Employee $employe)
    {
        $company     = currentCompany();
        $departments = Department::where('company_id', $company->id)->active()->orderBy('name')->get();
        $users       = User::where('company_id', $company->id)->orderBy('name')->get(['id', 'name', 'email']);
        $employe->load(['activeContract', 'department']);

        return view('rh.employes.edit', compact('employe', 'departments', 'users'));
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
            'user_id'            => ['nullable', 'exists:users,id'],
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

    // ─── Contrats (liste globale) ─────────────────────────────────────────────

    public function contracts(Request $request)
    {
        $company = currentCompany();

        $query = \App\Models\EmployeeContract::with('employee.department')
            ->whereHas('employee', fn($q) => $q->where('company_id', $company->id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type,   fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->whereHas('employee', fn($eq) =>
                $eq->where('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name',  'like', "%{$s}%")
                   ->orWhere('matricule',  'like', "%{$s}%")
            ));

        $contracts = (clone $query)
            ->orderByRaw("FIELD(status,'actif','termine','resilie') ASC")
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        // Stats globales (non filtrées)
        $base   = \App\Models\EmployeeContract::whereHas('employee', fn($q) => $q->where('company_id', $company->id));
        $stats  = [
            'total'    => $base->count(),
            'actifs'   => (clone $base)->where('status', 'actif')->count(),
            'termines' => (clone $base)->where('status', 'termine')->count(),
            'resilies' => (clone $base)->where('status', 'resilie')->count(),
        ];

        $statusOptions = ['actif' => 'Actif', 'termine' => 'Terminé', 'resilie' => 'Résilié'];
        $typeOptions   = ['CDI' => 'CDI', 'CDD' => 'CDD', 'stage' => 'Stage', 'consultant' => 'Consultant'];

        // Liste des employés actifs pour le modal d'ajout
        $employees = \App\Models\Employee::where('company_id', $company->id)
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'matricule']);

        return view('rh.contrats.index', compact('contracts', 'company', 'statusOptions', 'typeOptions', 'stats', 'employees'));
    }

    public function storeContractDirect(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type'        => ['required', 'in:CDI,CDD,stage,consultant'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after:start_date'],
            'base_salary' => ['required', 'integer', 'min:0'],
            'notes'       => ['nullable', 'string'],
        ]);

        $employe = \App\Models\Employee::findOrFail($data['employee_id']);

        // Clôturer l'ancien contrat actif
        $employe->contracts()->where('status', 'actif')->update(['status' => 'termine']);
        $employe->contracts()->create(array_merge($data, ['status' => 'actif']));

        return redirect()->route('rh.contrats.index')->with('success', 'Contrat ajouté avec succès.');
    }

    /**
     * Générer le PDF du contrat de travail (CDI/CDD — conforme Code Travail BF).
     */
    public function contractPdf(\App\Models\EmployeeContract $contract)
    {
        $employee = $contract->employee()->with('department')->firstOrFail();
        $company  = currentCompany();

        // Sécurité : contrat appartient bien à la société courante
        abort_if($employee->company_id !== $company->id, 403);

        $pdf = Pdf::loadView('rh.contrats.pdf', [
            'contract' => $contract,
            'employee' => $employee,
            'company'  => $company,
        ])->setPaper('a4', 'portrait');

        $filename = 'Contrat_'.$contract->type.'_'
            .str_replace(' ', '_', $employee->full_name).'_'
            .$contract->start_date->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    public function terminateContract(\App\Models\EmployeeContract $contract)
    {
        abort_if($contract->status !== 'actif', 422, 'Ce contrat n\'est pas actif.');
        $contract->update(['status' => 'termine']);
        return back()->with('success', 'Contrat terminé.');
    }

    public function resilierContract(\App\Models\EmployeeContract $contract)
    {
        abort_if($contract->status !== 'actif', 422, 'Ce contrat n\'est pas actif.');
        $contract->update(['status' => 'resilie']);
        return back()->with('success', 'Contrat résilié.');
    }

    public function destroyContract(\App\Models\EmployeeContract $contract)
    {
        // Seuls les contrats terminés ou résiliés peuvent être supprimés
        abort_if($contract->status === 'actif', 403, 'Un contrat actif ne peut pas être supprimé.');
        $contract->delete();
        return back()->with('success', 'Contrat supprimé.');
    }

    public function exportContracts(Request $request)
    {
        $company = currentCompany();

        $contracts = \App\Models\EmployeeContract::with('employee')
            ->whereHas('employee', fn($q) => $q->where('company_id', $company->id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type,   fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->whereHas('employee', fn($eq) =>
                $eq->where('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name',  'like', "%{$s}%")
                   ->orWhere('matricule',  'like', "%{$s}%")
            ))
            ->orderByRaw("FIELD(status,'actif','termine','resilie') ASC")
            ->orderByDesc('start_date')
            ->get();

        $filename = 'contrats_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($contracts) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($out, ['Matricule', 'Nom', 'Prénom', 'Type', 'Début', 'Fin', 'Salaire base (FCFA)', 'Statut', 'Notes'], ';');

            foreach ($contracts as $c) {
                fputcsv($out, [
                    $c->employee?->matricule ?? '',
                    $c->employee?->last_name ?? '',
                    $c->employee?->first_name ?? '',
                    $c->type,
                    $c->start_date?->format('d/m/Y') ?? '',
                    $c->end_date?->format('d/m/Y') ?? 'Indéterminée',
                    $c->base_salary,
                    match($c->status) { 'actif' => 'Actif', 'termine' => 'Terminé', 'resilie' => 'Résilié', default => $c->status },
                    $c->notes ?? '',
                ], ';');
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Départements ─────────────────────────────────────────────────────────

    public function departments(Request $request)
    {
        $company = currentCompany();
        $departments = Department::where('company_id', $company->id)->orderBy('name')->paginate(20);
        return view('rh.departments.index', compact('departments'));
    }

    public function storeDepartment(Request $request)
    {
        $company = currentCompany();
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
