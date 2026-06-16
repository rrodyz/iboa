<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoutingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only('index');
        $this->middleware('permission:production.create')->except('index');
    }

    public function index(): View
    {
        $routings = Routing::with('billOfMaterial')->withCount('operations')->orderBy('name')->paginate(25);

        return view('production.routings.index', compact('routings'));
    }

    public function create(): View
    {
        return view('production.routings.form', $this->formData(new Routing()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request) {
            $routing = Routing::create($data + ['company_id' => currentCompany()->id]);
            $this->syncOperations($routing, $request);
        });

        return redirect()->route('production.routings.index')->with('success', 'Gamme créée.');
    }

    public function edit(Routing $routing): View
    {
        $routing->load('operations');

        return view('production.routings.form', $this->formData($routing));
    }

    public function update(Request $request, Routing $routing): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($routing, $data, $request) {
            $routing->update($data);
            $routing->operations()->delete();
            $this->syncOperations($routing, $request);
        });

        return redirect()->route('production.routings.index')->with('success', 'Gamme mise à jour.');
    }

    public function destroy(Routing $routing): RedirectResponse
    {
        $routing->delete();

        return back()->with('success', 'Gamme supprimée.');
    }

    private function syncOperations(Routing $routing, Request $request): void
    {
        foreach ((array) $request->input('operations', []) as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $routing->operations()->create([
                'work_center_id'       => $row['work_center_id'] ?? null,
                'sequence'             => $row['sequence'] ?? (($i + 1) * 10),
                'name'                 => $row['name'],
                'setup_minutes'        => $row['setup_minutes'] ?? 0,
                'run_minutes_per_unit' => $row['run_minutes_per_unit'] ?? 0,
            ]);
        }
    }

    private function formData(Routing $routing): array
    {
        return [
            'routing'  => $routing,
            'boms'     => BillOfMaterial::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'centers'  => WorkCenter::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'bill_of_material_id'            => ['nullable', 'integer', 'exists:bills_of_materials,id'],
            'code'                           => ['required', 'string', 'max:30'],
            'name'                           => ['required', 'string', 'max:150'],
            'is_active'                      => ['boolean'],
            'operations'                     => ['nullable', 'array'],
            'operations.*.name'              => ['nullable', 'string', 'max:150'],
            'operations.*.work_center_id'    => ['nullable', 'integer', 'exists:work_centers,id'],
            'operations.*.sequence'          => ['nullable', 'integer', 'min:0'],
            'operations.*.setup_minutes'     => ['nullable', 'numeric', 'min:0'],
            'operations.*.run_minutes_per_unit' => ['nullable', 'numeric', 'min:0'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
