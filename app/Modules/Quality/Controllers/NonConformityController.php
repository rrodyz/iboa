<?php

namespace App\Modules\Quality\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NonConformityController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view')->only(['index']);
        $this->middleware('permission:production.update')->except(['index']);
    }

    public function index(Request $request): View
    {
        $items = NonConformity::with(['responsible', 'inspection'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('severity'), fn ($q, $v) => $q->where('severity', $v))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'ouvertes'  => NonConformity::whereIn('status', ['ouverte', 'en_cours'])->count(),
            'critiques' => NonConformity::where('severity', 'critique')->whereIn('status', ['ouverte', 'en_cours'])->count(),
            'cloturees' => NonConformity::where('status', 'cloturee')->count(),
        ];

        return view('qualite.non-conformities.index', compact('items', 'stats'));
    }

    public function create(Request $request): View
    {
        $nc = new NonConformity(['severity' => 'mineure', 'status' => 'ouverte']);
        if ($i = $request->input('quality_inspection_id')) {
            $nc->quality_inspection_id = $i;
        }

        return view('qualite.non-conformities.form', $this->formData($nc));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['company_id'] = currentCompany()->id;
        $data['reference']  = $this->nextRef();
        NonConformity::create($data);

        return redirect()->route('qualite.non-conformities.index')->with('success', 'Non-conformité créée : ' . $data['reference']);
    }

    public function edit(NonConformity $nonConformity): View
    {
        return view('qualite.non-conformities.form', $this->formData($nonConformity));
    }

    public function update(Request $request, NonConformity $nonConformity): RedirectResponse
    {
        $data = $this->validateData($request);
        if ($data['status'] === 'cloturee' && ! $nonConformity->closed_at) {
            $data['closed_at'] = now();
        }
        $nonConformity->update($data);

        return redirect()->route('qualite.non-conformities.index')->with('success', 'Non-conformité mise à jour.');
    }

    public function destroy(NonConformity $nonConformity): RedirectResponse
    {
        $nonConformity->delete();

        return back()->with('success', 'Non-conformité supprimée.');
    }

    private function nextRef(): string
    {
        return 'NC-' . str_pad((string) (NonConformity::withoutGlobalScopes()->where('company_id', currentCompany()->id)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function formData(NonConformity $nc): array
    {
        return [
            'nc'          => $nc,
            'inspections' => QualityInspection::orderByDesc('id')->limit(100)->get(['id', 'reference']),
            'employees'   => Employee::orderBy('last_name')->get(),
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'quality_inspection_id' => ['nullable', 'integer', 'exists:quality_inspections,id'],
            'title'                 => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'severity'              => ['required', 'in:mineure,majeure,critique'],
            'status'                => ['required', 'in:ouverte,en_cours,cloturee'],
            'corrective_action'     => ['nullable', 'string', 'max:2000'],
            'responsible_id'        => ['nullable', 'integer', 'exists:employees,id'],
            'due_date'              => ['nullable', 'date'],
        ]);
    }
}
