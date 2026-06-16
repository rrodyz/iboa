<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only('index');
        $this->middleware('permission:production.create')->except('index');
    }

    public function index(): View
    {
        $centers = WorkCenter::with(['machine', 'createdBy'])->orderBy('name')->paginate(25);

        return view('production.work-centers.index', compact('centers'));
    }

    public function create(): View
    {
        return view('production.work-centers.form', ['center' => new WorkCenter(), 'machines' => $this->machines()]);
    }

    public function store(Request $request): RedirectResponse
    {
        WorkCenter::create($this->validateData($request) + ['company_id' => currentCompany()->id]);

        return redirect()->route('production.work-centers.index')->with('success', 'Centre de travail créé.');
    }

    public function edit(WorkCenter $workCenter): View
    {
        return view('production.work-centers.form', ['center' => $workCenter, 'machines' => $this->machines()]);
    }

    public function update(Request $request, WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->update($this->validateData($request));

        return redirect()->route('production.work-centers.index')->with('success', 'Centre de travail mis à jour.');
    }

    public function destroy(WorkCenter $workCenter): RedirectResponse
    {
        $workCenter->delete();

        return back()->with('success', 'Centre de travail supprimé.');
    }

    private function machines()
    {
        return ProductionMachine::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'machine_id'             => ['nullable', 'integer', 'exists:production_machines,id'],
            'code'                   => ['required', 'string', 'max:30'],
            'name'                   => ['required', 'string', 'max:120'],
            'capacity_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'cost_per_hour'          => ['nullable', 'numeric', 'min:0'],
            'efficiency_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'              => ['boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
