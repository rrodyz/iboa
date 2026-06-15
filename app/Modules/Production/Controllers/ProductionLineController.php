<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionLineController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index']);
        $this->middleware('permission:production.create')->except(['index']);
    }

    public function index(): View
    {
        $lines = ProductionLine::with(['machine', 'createdBy'])->orderBy('name')->paginate(25);
        return view('production.lines.index', compact('lines'));
    }

    public function create(): View
    {
        return view('production.lines.form', ['line' => new ProductionLine(), 'machines' => ProductionMachine::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        ProductionLine::create($data);
        return redirect()->route('production.lines.index')->with('success', 'Ligne créée.');
    }

    public function edit(ProductionLine $line): View
    {
        return view('production.lines.form', ['line' => $line, 'machines' => ProductionMachine::where('is_active', true)->orderBy('name')->get()]);
    }

    public function update(Request $request, ProductionLine $line): RedirectResponse
    {
        $line->update($this->validateData($request));
        return redirect()->route('production.lines.index')->with('success', 'Ligne mise à jour.');
    }

    public function destroy(ProductionLine $line): RedirectResponse
    {
        $line->delete();
        return back()->with('success', 'Ligne supprimée.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'machine_id' => ['nullable', 'integer', 'exists:production_machines,id'],
            'code'       => ['required', 'string', 'max:30'],
            'name'       => ['required', 'string', 'max:120'],
            'is_active'  => ['boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
