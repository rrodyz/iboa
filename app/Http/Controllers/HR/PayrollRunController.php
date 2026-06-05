<?php

namespace App\Http\Controllers\HR;

use App\Exports\HR\CnssExport;
use App\Exports\HR\IutsExport;
use App\Exports\HR\LivreDepaieExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StorePayrollRunRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\PayrollVariable;
use App\Services\PayrollAccountingService;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PayrollRunController extends Controller
{
    public function __construct(private PayrollService $service) {}

    public function index(Request $request)
    {
        $company = currentCompany();
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

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $summaryQuery = PayrollRun::where('company_id', $company->id)
            ->when($filters['year']   ?? null, fn($q) => $q->where('period_year', $filters['year']))
            ->when($filters['status'] ?? null, fn($q) => $q->where('status', $filters['status']));

        $summary = [
            'total_brut'          => (int) (clone $summaryQuery)->sum('total_brut'),
            'total_net'           => (int) (clone $summaryQuery)->sum('total_net'),
            'total_cnss_employee' => (int) (clone $summaryQuery)->sum('total_cnss_employee'),
            'total_iuts'          => (int) (clone $summaryQuery)->sum('total_iuts'),
        ];

        return view('rh.paie.index', compact('runs', 'filters', 'years', 'summary'));
    }

    public function create()
    {
        $company  = currentCompany();
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

    public function store(StorePayrollRunRequest $request)
    {
        $data = $request->validated();

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
        try {
            $run->load('company');
            $settings = currentCompany()?->documentSetting;
            $payroll  = PayrollSetting::forCompany($run->company_id ?? currentCompany()->id);

            // [P2] Soldes de congés de l'employé pour l'année du bulletin
            $leaveBalances = LeaveBalance::where('employee_id', $item->employee_id)
                ->where('year', $run->period_year)
                ->with(['leaveType' => fn($q) => $q->where('is_active', true)])
                ->get()
                ->filter(fn($b) => $b->leaveType !== null);

            $pdf = Pdf::loadView('rh.pdf.bulletin', compact('run', 'item', 'settings', 'payroll', 'leaveBalances'))
                ->setPaper('a4', 'portrait');

            $filename = "Bulletin_{$run->period_year}_{$run->period_month}_{$item->employee_matricule}.pdf";
            return $pdf->stream($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF bulletin error', ['run' => $run->id, 'item' => $item->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le bulletin PDF : ' . $e->getMessage());
        }
    }

    /**
     * PDF : récapitulatif du mois (tous les employés).
     */
    public function recapPdf(PayrollRun $run)
    {
        try {
            $run->load('items');
            $settings = currentCompany()?->documentSetting;

            $pdf = Pdf::loadView('rh.pdf.recap', compact('run', 'settings'))
                ->setPaper('a4', 'landscape');

            $filename = "Recap_Paie_{$run->period_year}_{$run->period_month}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF recap paie error', ['run' => $run->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le récapitulatif PDF : ' . $e->getMessage());
        }
    }

    /**
     * PDF : bordereau CNSS.
     */
    public function cnssPdf(PayrollRun $run)
    {
        try {
            $run->load('items');
            $settings = currentCompany()?->documentSetting;
            $payroll  = PayrollSetting::forCompany($run->company_id ?? currentCompany()->id);

            $pdf = Pdf::loadView('rh.pdf.cnss', compact('run', 'settings', 'payroll'))
                ->setPaper('a4', 'portrait');

            $filename = "Bordereau_CNSS_{$run->period_year}_{$run->period_month}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF CNSS error', ['run' => $run->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le bordereau CNSS : ' . $e->getMessage());
        }
    }

    /**
     * PDF : état IUTS.
     */
    public function iutsPdf(PayrollRun $run)
    {
        try {
            $run->load('items');
            $settings = currentCompany()?->documentSetting;
            $payroll  = PayrollSetting::forCompany($run->company_id ?? currentCompany()->id);

            $pdf = Pdf::loadView('rh.pdf.iuts', compact('run', 'settings', 'payroll'))
                ->setPaper('a4', 'landscape');

            $filename = "Etat_IUTS_{$run->period_year}_{$run->period_month}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF IUTS error', ['run' => $run->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer l\'état IUTS : ' . $e->getMessage());
        }
    }

    /**
     * PDF : livre de paie (récap annuel multi-mois).
     */
    public function livrePaiePdf(Request $request)
    {
        $company = currentCompany();
        $year    = $request->integer('year', now()->year);
        $month   = $request->integer('month', 0); // 0 = tous les mois de l'année

        $query = PayrollRun::with('items')
            ->where('company_id', $company->id)
            ->where('period_year', $year)
            ->whereIn('status', ['valide', 'paye', 'calcule'])
            ->orderBy('period_month');

        if ($month > 0) {
            $query->where('period_month', $month);
        }

        $runs = $query->get();

        $settings = currentCompany()?->documentSetting;
        $payroll  = PayrollSetting::forCompany($company->id);

        // Titre et nom de fichier adaptés selon le filtre
        $monthLabel = $month > 0
            ? strtoupper(\Carbon\Carbon::create($year, $month)->translatedFormat('F Y'))
            : (string) $year;

        $filename = $month > 0
            ? "Livre_Paie_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT) . ".pdf"
            : "Livre_Paie_{$year}.pdf";

        $pdf = Pdf::loadView('rh.pdf.livre-paie', compact('runs', 'year', 'month', 'monthLabel', 'settings', 'company', 'payroll'))
            ->setPaper('a4', 'landscape');

        return $pdf->download($filename);
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
     * PDF : état des avances récupérées sur ce bulletin.
     */
    public function avancesPdf(PayrollRun $run)
    {
        $company  = currentCompany();
        $settings = $company->documentSetting;

        $avances = \App\Models\SalaryAdvance::with('employee')
            ->where('recovered_in_run_id', $run->id)
            ->orderBy('employee_id')
            ->get();

        $pdf = Pdf::loadView('rh.pdf.avances', compact('run', 'avances', 'settings', 'company'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("Etat_Avances_{$run->period_year}_{$run->period_month}.pdf");
    }

    /**
     * PDF : état des remboursements de prêts sur ce bulletin.
     */
    public function pretsPdf(PayrollRun $run)
    {
        $company  = currentCompany();
        $settings = $company->documentSetting;

        $payments = \App\Models\EmployeeLoanPayment::with(['loan.employee'])
            ->where('payroll_run_id', $run->id)
            ->orderBy('employee_loan_id')
            ->get();

        $pdf = Pdf::loadView('rh.pdf.prets', compact('run', 'payments', 'settings', 'company'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("Etat_Prets_{$run->period_year}_{$run->period_month}.pdf");
    }

    /**
     * Index global des variables mensuelles (toutes les runs).
     */
    public function variablesIndex(Request $request)
    {
        $company = currentCompany();

        $runs = PayrollRun::where('company_id', $company->id)
            ->withCount('items')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(12)
            ->withQueryString();

        return view('rh.variables.index', compact('runs', 'company'));
    }

    /**
     * États de paie — page dédiée avec exports par bulletin.
     */
    public function etats(Request $request)
    {
        $company = currentCompany();

        $runs = PayrollRun::where('company_id', $company->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(15)
            ->withQueryString();

        return view('rh.etats.index', compact('runs', 'company'));
    }

    /**
     * Comptabilisation paie — liste des bulletins avec statut comptable.
     */
    public function comptabilisation(Request $request)
    {
        $company = currentCompany();

        $runs = PayrollRun::with(['createdBy', 'validatedBy'])
            ->where('company_id', $company->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(15)
            ->withQueryString();

        return view('rh.comptabilisation.index', compact('runs', 'company'));
    }

    /**
     * Comptabilise manuellement un bulletin déjà validé.
     */
    public function journalize(PayrollRun $run)
    {
        try {
            $entry = app(PayrollAccountingService::class)->generateForRun($run);
            return redirect()
                ->route('rh.paie.show', $run)
                ->with('success', "Écriture comptable #{$entry->id} générée pour {$run->period_label}.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
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
