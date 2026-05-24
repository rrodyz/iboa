<?php

namespace App\Http\Controllers\HR;

use App\Exports\HR\CnssExport;
use App\Exports\HR\IutsExport;
use App\Exports\HR\LivreDepaieExport;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollVariable;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollRunController extends Controller
{
    public function __construct(private PayrollService $service) {}

    public function index(Request $request)
    {
        $company = Company::firstOrFail();
        $filters = $request->only(['year', 'status']);

        $query = PayrollRun::with(['createdBy', 'validatedBy'])
            ->where('company_id', $company->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');

        if ($filters['year'] ?? null) {
            $query->where('period_year', $filters['year']);
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        $runs  = $query->paginate(24)->withQueryString();
        $years = PayrollRun::where('company_id', $company->id)
            ->distinct()->orderByDesc('period_year')->pluck('period_year');

        return view('rh.paie.index', compact('runs', 'filters', 'years'));
    }

    public function create()
    {
        $company  = Company::firstOrFail();
        $existing = PayrollRun::where('company_id', $company->id)
            ->orderByDesc('period_year')->orderByDesc('period_month')
            ->first();

        // Suggère le mois suivant
        $suggestMonth = $existing
            ? ($existing->period_month % 12) + 1
            : now()->month;
        $suggestYear = $existing
            ? ($existing->period_month === 12 ? $existing->period_year + 1 : $existing->period_year)
            : now()->year;

        $activeCount = Employee::where('company_id', $company->id)
            ->where('status', 'actif')
            ->whereHas('activeContract')
            ->count();

        return view('rh.paie.create', compact('suggestMonth', 'suggestYear', 'activeCount'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year'  => ['required', 'integer', 'min:2020', 'max:2100'],
            'notes'        => ['nullable', 'string'],
        ]);

        try {
            $run = $this->service->createRun($data);
            return redirect()
                ->route('rh.paie.show', $run)
                ->with('success', "Bulletin de paie {$run->period_label} créé. Cliquez sur « Calculer » pour lancer le calcul.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(PayrollRun $run)
    {
        $run->load(['items' => fn($q) => $q->orderBy('employee_name'), 'validatedBy', 'createdBy']);
        return view('rh.paie.show', compact('run'));
    }

    /**
     * Lance le calcul de la paie.
     */
    public function calculate(PayrollRun $run)
    {
        try {
            $this->service->calculate($run);
            return redirect()
                ->route('rh.paie.show', $run)
                ->with('success', "Paie {$run->period_label} calculée pour {$run->employee_count} employé(s). Vérifiez et validez.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Valide le bulletin (irréversible sans admin).
     */
    public function approuver(PayrollRun $run)
    {
        try {
            $this->service->validate($run);
            return redirect()
                ->route('rh.paie.show', $run)
                ->with('success', "Bulletin {$run->period_label} validé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Marque comme payé.
     */
    public function markPaid(Request $request, PayrollRun $run)
    {
        $data = $request->validate(['paid_at' => ['required', 'date']]);
        try {
            $this->service->markPaid($run, $data['paid_at']);
            return redirect()
                ->route('rh.paie.show', $run)
                ->with('success', "Bulletin {$run->period_label} marqué comme payé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * PDF : bulletin individuel.
     */
    public function bulletinPdf(PayrollRun $run, \App\Models\PayrollItem $item)
    {
        $run->load('company');
        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('rh.pdf.bulletin', compact('run', 'item', 'settings'))
            ->setPaper('a4', 'portrait');

        $filename = "Bulletin_{$run->period_year}_{$run->period_month}_{$item->employee_matricule}.pdf";
        return $pdf->stream($filename);
    }

    /**
     * PDF : récapitulatif du mois (tous les employés).
     */
    public function recapPdf(PayrollRun $run)
    {
        $run->load('items');
        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('rh.pdf.recap', compact('run', 'settings'))
            ->setPaper('a4', 'landscape');

        $filename = "Recap_Paie_{$run->period_year}_{$run->period_month}.pdf";
        return $pdf->download($filename);
    }

    /**
     * PDF : bordereau CNSS.
     */
    public function cnssPdf(PayrollRun $run)
    {
        $run->load('items');
        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('rh.pdf.cnss', compact('run', 'settings'))
            ->setPaper('a4', 'portrait');

        $filename = "Bordereau_CNSS_{$run->period_year}_{$run->period_month}.pdf";
        return $pdf->download($filename);
    }

    /**
     * PDF : état IUTS.
     */
    public function iutsPdf(PayrollRun $run)
    {
        $run->load('items');
        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('rh.pdf.iuts', compact('run', 'settings'))
            ->setPaper('a4', 'landscape');

        $filename = "Etat_IUTS_{$run->period_year}_{$run->period_month}.pdf";
        return $pdf->download($filename);
    }

    /**
     * PDF : livre de paie (récap annuel multi-mois).
     */
    public function livrePaiePdf(Request $request)
    {
        $company = Company::firstOrFail();
        $year    = $request->integer('year', now()->year);

        $runs = PayrollRun::with('items')
            ->where('company_id', $company->id)
            ->where('period_year', $year)
            ->whereIn('status', ['valide', 'paye'])
            ->orderBy('period_month')
            ->get();

        $settings = Company::first()?->documentSetting;

        $pdf = Pdf::loadView('rh.pdf.livre-paie', compact('runs', 'year', 'settings', 'company'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("Livre_Paie_{$year}.pdf");
    }

    /**
     * Export CSV : ordre de virement groupé.
     */
    public function virementCsv(PayrollRun $run)
    {
        $run->load(['items.employee']);

        $filename = "Virement_Paie_{$run->period_year}_{$run->period_month}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($run) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Matricule', 'Nom Prénom', 'Banque', 'Code Banque', 'Code Guichet',
                'Numéro Compte', 'Clé RIB', 'Mode Paiement', 'Net à Payer (FCFA)',
            ], ';');

            foreach ($run->items as $item) {
                $emp = $item->employee;
                fputcsv($handle, [
                    $item->employee_matricule,
                    $item->employee_name,
                    $emp?->bank_name ?? '',
                    $emp?->bank_code ?? '',
                    $emp?->bank_branch ?? '',
                    $emp?->bank_account_number ?? $emp?->bank_account ?? '',
                    $emp?->bank_rib_key ?? '',
                    $emp?->payment_mode ?? 'virement',
                    $item->salaire_net,
                ], ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Exports Excel ────────────────────────────────────────────────────────

    public function livreDepaieXlsx(PayrollRun $run)
    {
        $run->load(['items.employee', 'company']);
        $filename = "livre-paie-{$run->period_year}-{$run->period_month}.xlsx";
        return Excel::download(new LivreDepaieExport($run), $filename);
    }

    public function cnssXlsx(PayrollRun $run)
    {
        $run->load(['items.employee', 'company']);
        $filename = "cnss-{$run->period_year}-{$run->period_month}.xlsx";
        return Excel::download(new CnssExport($run), $filename);
    }

    public function iutsXlsx(PayrollRun $run)
    {
        $run->load(['items', 'company']);
        $filename = "iuts-{$run->period_year}-{$run->period_month}.xlsx";
        return Excel::download(new IutsExport($run), $filename);
    }

    /**
     * Données variables d'un run (JSON — utilisé par la page show).
     */
    public function variables(PayrollRun $run)
    {
        $variables = PayrollVariable::with('employee')
            ->where('payroll_run_id', $run->id)
            ->orderBy('employee_id')
            ->get();

        return response()->json($variables);
    }
}
