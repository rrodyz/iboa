<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillOfMaterialController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        $this->middleware('permission:production.create')->except(['index', 'show']);
    }

    public function index(): View
    {
        $boms = BillOfMaterial::with('product')->withCount('lines')->orderBy('name')->paginate(25);

        return view('production.bom.index', compact('boms'));
    }

    public function create(): View
    {
        return view('production.bom.form', $this->formData(new BillOfMaterial()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($data, $request) {
            $bom = BillOfMaterial::create($data + ['company_id' => currentCompany()->id]);
            $this->syncLines($bom, $request);
        });

        return redirect()->route('production.bom.index')->with('success', 'Nomenclature créée.');
    }

    public function show(BillOfMaterial $bom): View
    {
        $bom->load(['product', 'lines.product', 'lines.unit']);
        $explosion = app(\App\Modules\Production\Services\BomExplosionService::class)->explode($bom, 1);

        return view('production.bom.show', compact('bom', 'explosion'));
    }

    public function edit(BillOfMaterial $bom): View
    {
        $bom->load('lines');

        return view('production.bom.form', $this->formData($bom));
    }

    public function update(Request $request, BillOfMaterial $bom): RedirectResponse
    {
        $data = $this->validateData($request);
        DB::transaction(function () use ($bom, $data, $request) {
            $bom->update($data);
            $bom->lines()->delete();
            $this->syncLines($bom, $request);
        });

        return redirect()->route('production.bom.index')->with('success', 'Nomenclature mise à jour.');
    }

    public function destroy(BillOfMaterial $bom): RedirectResponse
    {
        $bom->delete();

        return back()->with('success', 'Nomenclature supprimée.');
    }

    private function syncLines(BillOfMaterial $bom, Request $request): void
    {
        foreach ((array) $request->input('lines', []) as $i => $row) {
            if (empty($row['label']) && empty($row['product_id'])) {
                continue;
            }
            $bom->lines()->create([
                'product_id'         => $row['product_id'] ?? null,
                'label'              => $row['label'] ?? null,
                'quantity_per_meter' => $row['quantity_per_meter'] ?? 0,
                'unit_id'            => $row['unit_id'] ?? null,
                'waste_rate'         => $row['waste_rate'] ?? 0,
                'sort_order'         => $i,
            ]);
        }
    }

    private function formData(BillOfMaterial $bom): array
    {
        return [
            'bom'      => $bom,
            'products' => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'units'    => Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'product_id'            => ['nullable', 'integer', 'exists:products,id'],
            'name'                  => ['required', 'string', 'max:150'],
            'sheet_type'            => ['nullable', 'string', 'max:60'],
            'thickness'             => ['nullable', 'numeric', 'min:0'],
            'coil_width'            => ['nullable', 'numeric', 'min:0'],
            'usable_width'          => ['nullable', 'numeric', 'min:0'],
            'standard_waste_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'consumption_per_meter' => ['nullable', 'numeric', 'min:0'],
            'machine_time_per_unit' => ['nullable', 'numeric', 'min:0'],
            'labor_per_unit'        => ['nullable', 'numeric', 'min:0'],
            'std_material_cost'     => ['nullable', 'integer', 'min:0'],
            'std_labor_cost'        => ['nullable', 'integer', 'min:0'],
            'std_machine_cost'      => ['nullable', 'integer', 'min:0'],
            'std_overhead_cost'     => ['nullable', 'integer', 'min:0'],
            'is_active'             => ['boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
