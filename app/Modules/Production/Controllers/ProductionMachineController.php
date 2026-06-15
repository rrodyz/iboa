<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionMachineController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index']);
        $this->middleware('permission:production.create')->except(['index']);
    }

    public function index(Request $request): View
    {
        $machines = ProductionMachine::with('createdBy')
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('production.machines.index', compact('machines'));
    }

    public function create(): View { return view('production.machines.form', ['machine' => new ProductionMachine()]); }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        ProductionMachine::create($data);
        return redirect()->route('production.machines.index')->with('success', 'Machine créée.');
    }

    public function edit(ProductionMachine $machine): View { return view('production.machines.form', compact('machine')); }

    public function update(Request $request, ProductionMachine $machine): RedirectResponse
    {
        $machine->update($this->validateData($request, $machine->id));
        return redirect()->route('production.machines.index')->with('success', 'Machine mise à jour.');
    }

    public function destroy(ProductionMachine $machine): RedirectResponse
    {
        $machine->delete();
        return back()->with('success', 'Machine supprimée.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code'        => ['required', 'string', 'max:30'],
            'name'        => ['required', 'string', 'max:120'],
            'type'        => ['required', 'in:decoupe,profilage,mixte'],
            'hourly_cost' => ['nullable', 'integer', 'min:0'],
            'status'      => ['required', 'in:active,maintenance,arret'],
            'is_active'   => ['boolean'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
