<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollNumbering;
use App\Models\PayrollNumberingSequence;
use App\Services\BulletinNumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayrollNumberingController extends Controller
{
    public function index(): View
    {
        $company    = Company::firstOrFail();
        $numberings = PayrollNumbering::where('company_id', $company->id)
            ->withCount('sequences')
            ->with(['sequences' => fn ($q) => $q->orderByDesc('period_key')])
            ->orderByDesc('is_default')
            ->orderBy('libelle')
            ->get();

        return view('rh.parametrage.numerotation.index', [
            'numberings' => $numberings,
            'company'    => $company,
        ]);
    }

    public function create(): View
    {
        $company   = Company::firstOrFail();
        $numbering = new PayrollNumbering([
            'prefix'       => 'BUL',
            'separator'    => '-',
            'year_format'  => 'YYYY',
            'month_format' => 'MM',
            'seq_length'   => 4,
            'reset_on'     => 'year',
        ]);

        return view('rh.parametrage.numerotation.create', [
            'numbering' => $numbering,
            'company'   => $company,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = Company::firstOrFail();
        $data    = $this->validated($request);

        $data['company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        if ($data['is_default'] ?? false) {
            PayrollNumbering::where('company_id', $company->id)->update(['is_default' => false]);
        }

        $numbering = PayrollNumbering::create($data);

        return redirect()->route('rh.numerotation.index')
                         ->with('success', "Règle « {$numbering->libelle} » créée — format : {$numbering->preview(now(), 1)}");
    }

    public function edit(PayrollNumbering $numerotation): View
    {
        $this->authorizeCompany($numerotation);
        return view('rh.parametrage.numerotation.edit', [
            'numbering' => $numerotation,
            'company'   => Company::firstOrFail(),
        ]);
    }

    public function update(Request $request, PayrollNumbering $numerotation): RedirectResponse
    {
        $this->authorizeCompany($numerotation);
        $data = $this->validated($request);
        $data['updated_by'] = Auth::id();

        if ($data['is_default'] ?? false) {
            PayrollNumbering::where('company_id', $numerotation->company_id)
                            ->where('id', '!=', $numerotation->id)
                            ->update(['is_default' => false]);
        }

        $numerotation->update($data);

        return redirect()->route('rh.numerotation.index')
                         ->with('success', "Règle « {$numerotation->libelle} » mise à jour.");
    }

    public function destroy(PayrollNumbering $numerotation): RedirectResponse
    {
        $this->authorizeCompany($numerotation);

        if ($numerotation->is_default) {
            return back()->with('error', 'Impossible de supprimer la règle par défaut.');
        }

        $libelle = $numerotation->libelle;
        $numerotation->delete(); // cascade → sequences supprimées

        return redirect()->route('rh.numerotation.index')
                         ->with('success', "Règle « {$libelle} » supprimée.");
    }

    /**
     * Aperçu AJAX : retourne le prochain numéro qui sera généré.
     */
    public function preview(Request $request, BulletinNumberingService $service): JsonResponse
    {
        $request->validate([
            'numbering_id' => ['required', 'exists:payroll_numberings,id'],
            'period'       => ['required', 'date'],
        ]);

        $numbering = PayrollNumbering::findOrFail($request->numbering_id);
        $this->authorizeCompany($numbering);

        $period    = \Carbon\Carbon::parse($request->period);
        $next      = $service->peek($numbering, $period);
        $example   = $numbering->preview($period, 1) . ' … ' . $numbering->preview($period, 999);

        return response()->json([
            'next'         => $next,
            'example'      => $example,
            'format_parts' => [
                'prefix'    => $numbering->prefix,
                'separator' => $numbering->separator,
                'year'      => $numbering->year_format !== 'none' ? $period->format($numbering->year_format === 'YY' ? 'y' : 'Y') : null,
                'month'     => $numbering->month_format !== 'none' ? $period->format($numbering->month_format === 'M' ? 'n' : 'm') : null,
                'seq'       => str_pad(($numbering->sequences()->where('period_key', $numbering->periodKey($period))->first()?->last_seq ?? 0) + 1, $numbering->seq_length, '0', STR_PAD_LEFT),
            ],
        ]);
    }

    /**
     * Réinitialise la séquence d'une période (action dangereuse — confirmation requise).
     */
    public function resetSequence(Request $request, PayrollNumbering $numerotation): RedirectResponse
    {
        $this->authorizeCompany($numerotation);
        $request->validate(['period_key' => ['required', 'string', 'max:20']]);

        PayrollNumberingSequence::where('numbering_id', $numerotation->id)
                                ->where('period_key', $request->period_key)
                                ->update(['last_seq' => 0]);

        return back()->with('success', "Séquence réinitialisée pour la période « {$request->period_key} ».");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code'         => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_\-]+$/'],
            'libelle'      => ['required', 'string', 'max:150'],
            'prefix'       => ['required', 'string', 'max:20'],
            'separator'    => ['required', 'string', 'max:5'],
            'year_format'  => ['required', 'in:YYYY,YY,none'],
            'month_format' => ['required', 'in:MM,M,none'],
            'seq_length'   => ['required', 'integer', 'min:2', 'max:8'],
            'reset_on'     => ['required', 'in:year,month,never'],
            'is_active'    => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_default'] = $request->boolean('is_default');
        return $data;
    }

    private function authorizeCompany(PayrollNumbering $numbering): void
    {
        abort_if($numbering->company_id !== Company::firstOrFail()->id, 403);
    }
}
