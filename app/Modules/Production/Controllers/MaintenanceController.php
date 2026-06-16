<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $service)
    {
        $this->middleware('permission:production.view')->only('index');
        $this->middleware('permission:production.update')->except('index');
    }

    public function index(): View
    {
        $maintenances = MachineMaintenance::with(['machine', 'operator'])
            ->orderByRaw("CASE status WHEN 'en_cours' THEN 0 WHEN 'planifie' THEN 1 ELSE 2 END")
            ->orderByDesc('id')->paginate(25);

        $due = $this->service->dueList();

        $machineKpis = ProductionMachine::where('is_active', true)->orderBy('name')->get()
            ->map(fn ($m) => ['machine' => $m] + $this->service->machineKpis($m, 30));

        return view('production.maintenance.index', compact('maintenances', 'due', 'machineKpis'));
    }

    public function create(Request $request): View
    {
        $m = new MachineMaintenance(['type' => $request->input('type', 'preventive'), 'machine_id' => $request->input('machine_id'), 'planned_at' => now()]);

        return view('production.maintenance.form', $this->formData($m));
    }

    public function store(Request $request): RedirectResponse
    {
        MachineMaintenance::create($this->validateData($request) + ['company_id' => currentCompany()->id]);

        return redirect()->route('production.maintenance.index')->with('success', 'Intervention enregistrée.');
    }

    public function edit(MachineMaintenance $maintenance): View
    {
        return view('production.maintenance.form', $this->formData($maintenance));
    }

    public function update(Request $request, MachineMaintenance $maintenance): RedirectResponse
    {
        $maintenance->update($this->validateData($request));

        return redirect()->route('production.maintenance.index')->with('success', 'Intervention mise à jour.');
    }

    public function destroy(MachineMaintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return back()->with('success', 'Intervention supprimée.');
    }

    public function start(MachineMaintenance $maintenance): RedirectResponse
    {
        try {
            $this->service->start($maintenance);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Intervention démarrée — machine en maintenance.');
    }

    public function finish(Request $request, MachineMaintenance $maintenance): RedirectResponse
    {
        $request->validate(['downtime_minutes' => ['nullable', 'numeric', 'min:0'], 'cost' => ['nullable', 'integer', 'min:0']]);
        $this->service->finish(
            $maintenance,
            $request->filled('downtime_minutes') ? (float) $request->downtime_minutes : null,
            $request->filled('cost') ? (int) $request->cost : null,
        );

        return back()->with('success', 'Intervention terminée — machine réactivée.');
    }

    private function formData(MachineMaintenance $m): array
    {
        return [
            'maintenance' => $m,
            'machines'    => ProductionMachine::orderBy('name')->get(['id', 'name']),
            'employees'   => Employee::orderBy('last_name')->get(),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'machine_id'       => ['required', 'integer', 'exists:production_machines,id'],
            'type'             => ['required', 'in:preventive,corrective'],
            'title'            => ['required', 'string', 'max:200'],
            'status'           => ['required', 'in:planifie,en_cours,termine'],
            'planned_at'       => ['nullable', 'date'],
            'downtime_minutes' => ['nullable', 'numeric', 'min:0'],
            'cost'             => ['nullable', 'integer', 'min:0'],
            'operator_id'      => ['nullable', 'integer', 'exists:employees,id'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
