<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Reception;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QualityInspectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        $this->middleware('permission:production.update')->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $inspections = QualityInspection::with(['reception', 'product', 'controller'])
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'total'        => QualityInspection::count(),
            'non_conforme' => QualityInspection::where('status', 'non_conforme')->count(),
            'rejected'     => (float) QualityInspection::sum('quantity_rejected'),
        ];

        return view('qualite.inspections.index', compact('inspections', 'stats'));
    }

    public function create(Request $request): View
    {
        $inspection = new QualityInspection(['type' => $request->input('type', 'reception'), 'inspected_at' => now()]);
        if ($r = $request->input('reception_id')) {
            $inspection->reception_id = $r;
        }

        return view('qualite.inspections.form', $this->formData($inspection));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        $data['reference']  = $this->nextRef();
        QualityInspection::create($data);

        return redirect()->route('qualite.inspections.index')->with('success', 'Contrôle qualité enregistré : ' . $data['reference']);
    }

    public function edit(QualityInspection $inspection): View
    {
        return view('qualite.inspections.form', $this->formData($inspection));
    }

    public function update(Request $request, QualityInspection $inspection): RedirectResponse
    {
        $inspection->update($this->validateData($request));

        return redirect()->route('qualite.inspections.index')->with('success', 'Contrôle mis à jour.');
    }

    public function destroy(QualityInspection $inspection): RedirectResponse
    {
        $inspection->delete();

        return back()->with('success', 'Contrôle supprimé.');
    }

    private function nextRef(): string
    {
        return 'CQ-' . str_pad((string) (QualityInspection::withoutGlobalScopes()->where('company_id', currentCompany()->id)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function formData(QualityInspection $inspection): array
    {
        return [
            'inspection' => $inspection,
            'receptions' => Reception::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'products'   => Product::orderBy('name')->get(['id', 'name']),
            'employees'  => Employee::orderBy('last_name')->get(),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'type'               => ['required', 'in:reception,en_cours,produit_fini'],
            'reception_id'       => ['nullable', 'integer', 'exists:receptions,id'],
            'production_order_id'=> ['nullable', 'integer', 'exists:production_orders,id'],
            'product_id'         => ['nullable', 'integer', 'exists:products,id'],
            'controller_id'      => ['nullable', 'integer', 'exists:employees,id'],
            'inspected_at'       => ['nullable', 'date'],
            'status'             => ['required', 'in:conforme,non_conforme,partiel'],
            'quantity_checked'   => ['nullable', 'numeric', 'min:0'],
            'quantity_rejected'  => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
