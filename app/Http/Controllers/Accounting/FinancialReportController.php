<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\JournalType;
use App\Services\JournalEntryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // BILAN (Balance sheet) — SYSCOHADA classes 1–5
    // ACTIF  : classes 2 (immos), 3 (stocks), 4 débiteurs (client 411), 5 (trésorerie)
    // PASSIF : classes 1 (capitaux), 4 créanciers (fournisseurs 401), 5 dettes
    // ─────────────────────────────────────────────────────────────────────────
    public function bilan(Request $request): View
    {
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $selectedFy  = $this->resolveSelectedFiscalYear($request, $fiscalYears);
        $compare     = $request->boolean('compare');

        if ($selectedFy) {
            $accounts = $this->loadAccountsWithMovements(
                ['1%', '2%', '3%', '4%', '5%'],
                null,
                $selectedFy->ends_at->toDateString()
            );
        } else {
            $accounts = $this->loadCumulativeAccounts(['1%', '2%', '3%', '4%', '5%']);
        }

        $accounts->each(fn ($a) => $a->net = $a->debit_balance - $a->credit_balance);

        // [BILAN-FIX] Résultat net calculé dynamiquement depuis classes 6 & 7
        $netResult = $this->computeNetResult($selectedFy);

        [$actif, $passif, $totalActif, $totalPassif] = $this->buildBilanSections($accounts, $netResult);

        // [COMPTA-PRO-04] Comparatif N vs N-1
        $prevFy = null; $prevTotals = null;
        if ($compare && $selectedFy) {
            $prevFy = $this->resolvePreviousFiscalYear($selectedFy);
            if ($prevFy) {
                $prevAccounts = $this->loadAccountsWithMovements(
                    ['1%', '2%', '3%', '4%', '5%'],
                    null,
                    $prevFy->ends_at->toDateString()
                );
                $prevAccounts->each(fn ($a) => $a->net = $a->debit_balance - $a->credit_balance);
                $prevNetResult = $this->computeNetResult($prevFy);
                $prevTotals = $this->sectionTotalsForBilan($prevAccounts, $prevNetResult);
            }
        }

        return view('comptabilite.rapports.bilan', compact(
            'actif', 'passif', 'totalActif', 'totalPassif', 'netResult',
            'fiscalYears', 'selectedFy',
            'compare', 'prevFy', 'prevTotals'
        ));
    }

    /** GET comptabilite/bilan/pdf */
    public function bilanPdf(Request $request)
    {
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $selectedFy  = $this->resolveSelectedFiscalYear($request, $fiscalYears);

        if ($selectedFy) {
            $accounts = $this->loadAccountsWithMovements(
                ['1%', '2%', '3%', '4%', '5%'],
                null,
                $selectedFy->ends_at->toDateString()
            );
        } else {
            $accounts = $this->loadCumulativeAccounts(['1%', '2%', '3%', '4%', '5%']);
        }

        $accounts->each(fn ($a) => $a->net = $a->debit_balance - $a->credit_balance);
        $netResult = $this->computeNetResult($selectedFy);
        [$actif, $passif, $totalActif, $totalPassif] = $this->buildBilanSections($accounts, $netResult);

        $company   = currentCompany();
        $printedAt = now()->format('d/m/Y à H:i');

        $pdf = Pdf::loadView(
            'comptabilite.rapports.pdf.bilan',
            compact('actif', 'passif', 'totalActif', 'totalPassif', 'netResult', 'company', 'printedAt', 'selectedFy')
        )->setPaper('a4', 'portrait');

        $suffix   = $selectedFy ? '_' . $selectedFy->label : '';
        $filename = 'Bilan' . $suffix . '_' . now()->format('Y-m-d') . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPTE DE RÉSULTAT — classes 6 (charges) and 7 (produits)
    // ─────────────────────────────────────────────────────────────────────────
    public function compteDeResultat(Request $request): View
    {
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $selectedFy  = $this->resolveSelectedFiscalYear($request, $fiscalYears);
        $compare     = $request->boolean('compare');

        if ($selectedFy) {
            $accounts = $this->loadAccountsWithMovements(
                ['6%', '7%'],
                $selectedFy->starts_at->toDateString(),
                $selectedFy->ends_at->toDateString()
            );
        } else {
            $accounts = $this->loadCumulativeAccounts(['6%', '7%']);
        }

        $accounts->each(fn ($a) => $a->net = abs($a->debit_balance - $a->credit_balance));

        [$charges, $produits, $totalCharges, $totalProduits, $resultat] = $this->buildCdrSections($accounts);

        // [COMPTA-PRO-04] Comparatif N vs N-1
        $prevFy = null; $prevTotals = null;
        if ($compare && $selectedFy) {
            $prevFy = $this->resolvePreviousFiscalYear($selectedFy);
            if ($prevFy) {
                $prevAccounts = $this->loadAccountsWithMovements(
                    ['6%', '7%'],
                    $prevFy->starts_at->toDateString(),
                    $prevFy->ends_at->toDateString()
                );
                $prevAccounts->each(fn ($a) => $a->net = abs($a->debit_balance - $a->credit_balance));
                $prevTotals = $this->sectionTotalsForCdr($prevAccounts);
            }
        }

        return view('comptabilite.rapports.compte-de-resultat', compact(
            'charges', 'produits', 'totalCharges', 'totalProduits', 'resultat',
            'fiscalYears', 'selectedFy',
            'compare', 'prevFy', 'prevTotals'
        ));
    }

    /** GET comptabilite/compte-de-resultat/pdf */
    public function compteDeResultatPdf(Request $request)
    {
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $selectedFy  = $this->resolveSelectedFiscalYear($request, $fiscalYears);

        if ($selectedFy) {
            $accounts = $this->loadAccountsWithMovements(
                ['6%', '7%'],
                $selectedFy->starts_at->toDateString(),
                $selectedFy->ends_at->toDateString()
            );
        } else {
            $accounts = $this->loadCumulativeAccounts(['6%', '7%']);
        }

        $accounts->each(fn ($a) => $a->net = abs($a->debit_balance - $a->credit_balance));
        [$charges, $produits, $totalCharges, $totalProduits, $resultat] = $this->buildCdrSections($accounts);

        $company   = currentCompany();
        $printedAt = now()->format('d/m/Y à H:i');

        $pdf = Pdf::loadView(
            'comptabilite.rapports.pdf.compte-de-resultat',
            compact('charges', 'produits', 'totalCharges', 'totalProduits', 'resultat', 'company', 'printedAt', 'selectedFy')
        )->setPaper('a4', 'portrait');

        $suffix   = $selectedFy ? '_' . $selectedFy->label : '';
        $filename = 'Compte_de_Resultat' . $suffix . '_' . now()->format('Y-m-d') . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AFFECTATION DU RÉSULTAT — OD vers compte 13
    // ─────────────────────────────────────────────────────────────────────────

    /** GET comptabilite/affectation-resultat */
    public function affectationResultat(Request $request): View
    {
        $companyId   = Auth::user()->company_id;
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        $selectedFy  = $this->resolveSelectedFiscalYear($request, $fiscalYears);

        $netResult = $this->computeNetResult($selectedFy);

        // Comptes disponibles pour l'affectation (classe 1 : réserves, RAN, dividendes 4612)
        $comptes = Account::where('company_id', $companyId)
            ->where('is_detail', true)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', 'like', '1%')
                  ->orWhere('code', 'like', '4612%');
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Compte 13 — Résultat net (destination de clôture des 6/7)
        $compte13 = Account::where('company_id', $companyId)->where('code', '13')->first();

        return view('comptabilite.rapports.affectation-resultat', compact(
            'netResult', 'selectedFy', 'fiscalYears', 'comptes', 'compte13'
        ));
    }

    /** POST comptabilite/affectation-resultat */
    public function storeAffectation(Request $request)
    {
        $request->validate([
            'fiscal_year_id'    => ['required', 'exists:fiscal_years,id'],
            'date_affectation'  => ['required', 'date'],
            'lignes'            => ['required', 'array', 'min:1'],
            'lignes.*.account_id' => ['required', 'exists:accounts,id'],
            'lignes.*.montant'    => ['required', 'numeric', 'min:1'],
            'lignes.*.label'      => ['required', 'string', 'max:255'],
        ]);

        $companyId = Auth::user()->company_id;
        $fy        = FiscalYear::findOrFail($request->fiscal_year_id);
        $netResult = $this->computeNetResult($fy);

        // Vérifier que la somme des affectations = résultat net
        $totalAffecte = collect($request->lignes)->sum(fn($l) => (int) $l['montant']);
        if (abs($totalAffecte) !== abs($netResult)) {
            return back()->withInput()->with('error',
                "Le total des affectations ({$totalAffecte} FCFA) ne correspond pas au résultat net (" .
                abs($netResult) . " FCFA). Corrigez avant de valider."
            );
        }

        // Compte 13 — Résultat net de l'exercice
        $compte13 = Account::where('company_id', $companyId)->where('code', '13')->first();
        if (!$compte13) {
            return back()->with('error', 'Compte 13 (Résultat net) introuvable dans le plan comptable.');
        }

        DB::transaction(function () use ($request, $fy, $netResult, $compte13, $companyId) {
            // Générer le numéro d'écriture
            $lastNum = JournalEntry::where('company_id', $companyId)
                ->where('number', 'like', 'OD-%')
                ->orderByDesc('number')
                ->value('number');
            $seq = $lastNum ? (int) explode('-', $lastNum)[2] + 1 : 1;
            $number = 'OD-' . now()->year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $journalType = JournalType::where('company_id', $companyId)
                ->where('type', 'operations_diverses')
                ->first();

            $entry = JournalEntry::create([
                'company_id'      => $companyId,
                'journal_type_id' => $journalType->id,
                'fiscal_year_id'  => $fy->id,
                'number'          => $number,
                'entry_date'      => $request->date_affectation,
                'reference'       => 'AFFECT-' . $fy->label,
                'description'     => 'Affectation du résultat — ' . $fy->label,
                'status'          => 'brouillon',
                'total_debit'     => abs($netResult),
                'total_credit'    => abs($netResult),
                'created_by'      => Auth::id(),
            ]);

            $sort = 0;

            if ($netResult < 0) {
                // PERTE : Dr 12 (Report à nouveau) / Cr 13 (Résultat net)
                // On laisse l'utilisateur décider où affecter la perte
                // Ligne 1 : Débit = comptes d'affectation
                foreach ($request->lignes as $ligne) {
                    JournalEntryLine::create([
                        'journal_entry_id'   => $entry->id,
                        'account_id'         => $ligne['account_id'],
                        'label'              => $ligne['label'],
                        'debit'              => (int) $ligne['montant'],
                        'credit'             => 0,
                        'sort_order'         => $sort++,
                    ]);
                }
                // Ligne contrepartie : Crédit = compte 13
                JournalEntryLine::create([
                    'journal_entry_id'   => $entry->id,
                    'account_id'         => $compte13->id,
                    'label'              => 'Résultat net — perte exercice ' . $fy->label,
                    'debit'              => 0,
                    'credit'             => abs($netResult),
                    'sort_order'         => $sort++,
                ]);
            } else {
                // BÉNÉFICE : Dr 13 (Résultat net) / Cr comptes d'affectation
                JournalEntryLine::create([
                    'journal_entry_id'   => $entry->id,
                    'account_id'         => $compte13->id,
                    'label'              => 'Résultat net — bénéfice exercice ' . $fy->label,
                    'debit'              => abs($netResult),
                    'credit'             => 0,
                    'sort_order'         => $sort++,
                ]);
                foreach ($request->lignes as $ligne) {
                    JournalEntryLine::create([
                        'journal_entry_id'   => $entry->id,
                        'account_id'         => $ligne['account_id'],
                        'label'              => $ligne['label'],
                        'debit'              => 0,
                        'credit'             => (int) $ligne['montant'],
                        'sort_order'         => $sort++,
                    ]);
                }
            }
        });

        return redirect()->route('comptabilite.bilan', ['fiscal_year_id' => $request->fiscal_year_id])
            ->with('success', '✅ OD d\'affectation du résultat créée en brouillon. Validez-la dans le journal pour l\'enregistrer définitivement.');
    }

    /**
     * Situation comptable — real-time financial snapshot.
     */
    public function situationComptable(): View
    {
        $company = currentCompany();

        // Cash (class 5)
        $cashAccounts = Account::where('is_detail', true)
            ->where('is_active', true)
            ->where('code', 'like', '5%')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'debit_balance', 'credit_balance']);
        $cashAccounts->each(fn($a) => $a->net = $a->debit_balance - $a->credit_balance);
        $totalTresorerie = $cashAccounts->sum('net');

        // Receivables (411)
        $totalClients = (int) Account::where('is_detail', true)
            ->where('code', 'like', '41%')
            ->selectRaw('SUM(debit_balance) - SUM(credit_balance) as net')
            ->value('net');

        // Payables (401)
        $totalFournisseurs = (int) Account::where('is_detail', true)
            ->where('code', 'like', '40%')
            ->selectRaw('SUM(credit_balance) - SUM(debit_balance) as net')
            ->value('net');

        // Fixed assets (class 2)
        $totalImmobilisations = (int) Account::where('is_detail', true)
            ->where('code', 'like', '2%')
            ->selectRaw('SUM(debit_balance) - SUM(credit_balance) as net')
            ->value('net');

        // Stocks (class 3)
        $totalStocks = (int) Account::where('is_detail', true)
            ->where('code', 'like', '3%')
            ->selectRaw('SUM(debit_balance) - SUM(credit_balance) as net')
            ->value('net');

        // Equity (class 1)
        $totalCapitaux = (int) Account::where('is_detail', true)
            ->where('code', 'like', '1%')
            ->selectRaw('SUM(credit_balance) - SUM(debit_balance) as net')
            ->value('net');

        // YTD charges (class 6)
        $totalCharges = (int) Account::where('is_detail', true)
            ->where('code', 'like', '6%')
            ->selectRaw('SUM(debit_balance) - SUM(credit_balance) as net')
            ->value('net');

        // YTD produits (class 7)
        $totalProduits = (int) Account::where('is_detail', true)
            ->where('code', 'like', '7%')
            ->selectRaw('SUM(credit_balance) - SUM(debit_balance) as net')
            ->value('net');

        $resultat = $totalProduits - $totalCharges;

        // Recent entries (last 10 validated)
        $recentEntries = \App\Models\JournalEntry::with(['journalType'])
            ->where('status', 'valide')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'number', 'entry_date', 'description', 'total_debit', 'journal_type_id', 'reference']);

        // Brouillons pending validation
        $brouillonCount = \App\Models\JournalEntry::where('status', 'brouillon')->count();

        return view('comptabilite.rapports.situation-comptable', compact(
            'company', 'cashAccounts', 'totalTresorerie',
            'totalClients', 'totalFournisseurs',
            'totalImmobilisations', 'totalStocks', 'totalCapitaux',
            'totalCharges', 'totalProduits', 'resultat',
            'recentEntries', 'brouillonCount'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private — fiscal year resolver
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the selected fiscal year from the request.
     * Falls back to the current fiscal year if none is specified.
     */
    /**
     * [COMPTA-PRO-04] Trouve l'exercice immédiatement antérieur à celui passé,
     * pour le comparatif N vs N-1. On retient le FY dont ends_at est juste avant
     * starts_at de l'exercice courant.
     */
    private function resolvePreviousFiscalYear(FiscalYear $fy): ?FiscalYear
    {
        return FiscalYear::where('ends_at', '<', $fy->starts_at)
            ->orderByDesc('ends_at')
            ->first();
    }

    /**
     * [COMPTA-PRO-04] Calcule les totaux par section du bilan pour un set de comptes.
     * Renvoie un tableau plat label => montant (pour comparaison avec l'année N).
     */
    private function sectionTotalsForBilan(Collection $accounts, int $netResult = 0): array
    {
        [$actif, $passif, $totalActif, $totalPassif] = $this->buildBilanSections($accounts, $netResult);
        $totals = ['__totalActif' => $totalActif, '__totalPassif' => $totalPassif];
        foreach ($actif as $label => $coll) {
            $totals['ACTIF::' . $label] = (int) $coll->sum('net');
        }
        foreach ($passif as $label => $coll) {
            $totals['PASSIF::' . $label] = (int) $coll->sum(fn($a) => abs($a->net));
        }
        return $totals;
    }

    /**
     * Calcule le résultat net de l'exercice (Produits – Charges, classes 6 & 7).
     * Si aucun exercice sélectionné, utilise les soldes cumulés.
     *
     * @return int  Positif = bénéfice, négatif = perte
     */
    private function computeNetResult(?FiscalYear $fy): int
    {
        if ($fy) {
            $accounts67 = $this->loadAccountsWithMovements(
                ['6%', '7%'],
                $fy->starts_at->toDateString(),
                $fy->ends_at->toDateString()
            );
        } else {
            $accounts67 = $this->loadCumulativeAccounts(['6%', '7%']);
        }

        $totalCharges  = $accounts67->filter(fn($a) => str_starts_with($a->code, '6'))
                                    ->sum(fn($a) => $a->debit_balance - $a->credit_balance);
        $totalProduits = $accounts67->filter(fn($a) => str_starts_with($a->code, '7'))
                                    ->sum(fn($a) => $a->credit_balance - $a->debit_balance);

        return (int) ($totalProduits - $totalCharges);
    }

    /**
     * [COMPTA-PRO-04] Idem pour le compte de résultat.
     */
    private function sectionTotalsForCdr(Collection $accounts): array
    {
        [$charges, $produits, $totalCharges, $totalProduits, $resultat] = $this->buildCdrSections($accounts);
        $totals = [
            '__totalCharges'  => $totalCharges,
            '__totalProduits' => $totalProduits,
            '__resultat'      => $resultat,
        ];
        foreach ($charges as $label => $coll) {
            $totals['CHARGES::' . $label] = (int) $coll->sum('net');
        }
        foreach ($produits as $label => $coll) {
            $totals['PRODUITS::' . $label] = (int) $coll->sum('net');
        }
        return $totals;
    }

    private function resolveSelectedFiscalYear(Request $request, Collection $fiscalYears): ?FiscalYear
    {
        if ($request->filled('fiscal_year_id')) {
            return $fiscalYears->firstWhere('id', $request->integer('fiscal_year_id'));
        }

        // Default: auto-select the current fiscal year if one is set
        return $fiscalYears->firstWhere('is_current', true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private — account loaders
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load accounts with cumulative balances (the stored running totals).
     * Used when no period filter is selected.
     */
    private function loadCumulativeAccounts(array $prefixes): Collection
    {
        $query = Account::where('is_detail', true)->where('is_active', true);

        $query->where(function ($q) use ($prefixes) {
            foreach ($prefixes as $p) {
                $q->orWhere('code', 'like', $p);
            }
        });

        return $query->orderBy('code')->get(['id', 'code', 'name', 'debit_balance', 'credit_balance']);
    }

    /**
     * Load accounts and compute balances from journal entry line movements
     * within an optional date window.
     *
     * For the Bilan: pass $dateFrom = null to aggregate from the beginning.
     * For the CDR:   pass both $dateFrom and $dateTo.
     */
    private function loadAccountsWithMovements(array $prefixes, ?string $dateFrom, string $dateTo): Collection
    {
        // Aggregate validated JEL movements for accounts in the given classes
        $movements = JournalEntryLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->whereHas('account', function ($q) use ($prefixes) {
                $q->where(function ($sub) use ($prefixes) {
                    foreach ($prefixes as $p) {
                        $sub->orWhere('code', 'like', $p);
                    }
                });
            })
            ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                $q->where('status', 'valide');
                if ($dateFrom !== null) {
                    $q->where('entry_date', '>=', $dateFrom);
                }
                $q->where('entry_date', '<=', $dateTo);
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // Load account metadata (code / name)
        $accounts = Account::where('is_detail', true)
            ->where('is_active', true)
            ->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $p) {
                    $q->orWhere('code', 'like', $p);
                }
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Overlay period movements onto account objects
        $accounts->each(function ($a) use ($movements) {
            $mv = $movements->get($a->id);
            $a->debit_balance  = $mv ? (int) $mv->total_debit  : 0;
            $a->credit_balance = $mv ? (int) $mv->total_credit : 0;
        });

        return $accounts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private — section builders (shared between HTML and PDF, cumul or period)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build actif / passif sections from an $accounts collection that already
     * has debit_balance, credit_balance and net set.
     *
     * @param int $netResult Résultat net calculé depuis classes 6/7 (positif = bénéfice, négatif = perte)
     */
    private function buildBilanSections(Collection $accounts, int $netResult = 0): array
    {
        // ACTIF sections
        $actif = [
            'Immobilisations'         => $accounts->filter(fn ($a) => str_starts_with($a->code, '2')),
            'Stocks'                  => $accounts->filter(fn ($a) => str_starts_with($a->code, '3')),
            'Créances clients'        => $accounts->filter(fn ($a) =>
                str_starts_with($a->code, '411') || str_starts_with($a->code, '416') || str_starts_with($a->code, '418')
            ),
            'Autres créances'         => $accounts->filter(fn ($a) =>
                str_starts_with($a->code, '4')
                && ! str_starts_with($a->code, '40')
                && ! str_starts_with($a->code, '41')
                && ! str_starts_with($a->code, '44')
                && ! str_starts_with($a->code, '45')
                && $a->net > 0
            ),
            'Trésorerie active'       => $accounts->filter(fn ($a) => str_starts_with($a->code, '5') && $a->net >= 0),
        ];

        // [BILAN-FIX] Injecter le résultat net comme compte virtuel dans Capitaux propres
        // Il n'est PAS dans la DB (comptes 6/7 ne font pas partie des classes 1-5 du bilan),
        // mais il doit figurer en capitaux propres conformément à la norme SYSCOHADA.
        $resultatVirtual = (object)[
            'id'             => 0,
            'code'           => '13',
            'name'           => $netResult >= 0
                                    ? 'Résultat net de l\'exercice — Bénéfice'
                                    : 'Résultat net de l\'exercice — Perte',
            'debit_balance'  => $netResult < 0 ? abs($netResult) : 0,
            'credit_balance' => $netResult >= 0 ? $netResult : 0,
            'net'            => $netResult,   // signé : positif = bénéfice, négatif = perte
            '_virtual'       => true,         // flag pour la vue
            '_is_loss'       => $netResult < 0,
        ];

        $capitauxPropres = $accounts->filter(fn ($a) => str_starts_with($a->code, '1'));
        if ($netResult !== 0) {
            $capitauxPropres = $capitauxPropres->push($resultatVirtual);
        }

        // PASSIF sections
        $passif = [
            'Capitaux propres'        => $capitauxPropres,
            'Dettes fournisseurs'     => $accounts->filter(fn ($a) => str_starts_with($a->code, '401') || str_starts_with($a->code, '408')),
            'Dettes fiscales & soc.'  => $accounts->filter(fn ($a) => str_starts_with($a->code, '44') || str_starts_with($a->code, '45')),
            'Autres dettes'           => $accounts->filter(fn ($a) =>
                str_starts_with($a->code, '4')
                && ! str_starts_with($a->code, '40')
                && ! str_starts_with($a->code, '41')
                && ! str_starts_with($a->code, '44')
                && ! str_starts_with($a->code, '45')
                && $a->net <= 0
            ),
            'Trésorerie passive'      => $accounts->filter(fn ($a) => str_starts_with($a->code, '5') && $a->net < 0),
        ];

        $totalActif = $accounts->filter(fn ($a) =>
            str_starts_with($a->code, '2')
            || str_starts_with($a->code, '3')
            || (str_starts_with($a->code, '4') && $a->net > 0)
            || (str_starts_with($a->code, '5') && $a->net >= 0)
        )->sum('net');

        // [BILAN-FIX] totalPassif inclut le résultat net (algébrique)
        // Bénéfice (+) augmente le passif, Perte (-) le réduit → bilan toujours équilibré
        $totalPassif = $accounts->filter(fn ($a) =>
            str_starts_with($a->code, '1')
            || (str_starts_with($a->code, '4') && $a->net <= 0)
            || (str_starts_with($a->code, '5') && $a->net < 0)
        )->sum(fn ($a) => abs($a->net)) + $netResult;

        return [$actif, $passif, $totalActif, $totalPassif];
    }

    /**
     * Build charges / produits sections from an $accounts collection that
     * already has net set to abs(debit - credit).
     */
    private function buildCdrSections(Collection $accounts): array
    {
        $charges = [
            'Achats de marchandises'   => $accounts->filter(fn ($a) => str_starts_with($a->code, '60')),
            'Autres achats'            => $accounts->filter(fn ($a) => str_starts_with($a->code, '61') || str_starts_with($a->code, '62')),
            'Impôts et taxes'          => $accounts->filter(fn ($a) => str_starts_with($a->code, '63')),
            'Charges de personnel'     => $accounts->filter(fn ($a) => str_starts_with($a->code, '64') || str_starts_with($a->code, '66')),
            'Dotations amortissements' => $accounts->filter(fn ($a) => str_starts_with($a->code, '68')),
            'Autres charges'           => $accounts->filter(fn ($a) =>
                str_starts_with($a->code, '65') || str_starts_with($a->code, '67') || str_starts_with($a->code, '69')
            ),
        ];

        $produits = [
            'Ventes de marchandises' => $accounts->filter(fn ($a) => str_starts_with($a->code, '70')),
            'Prestations de services'=> $accounts->filter(fn ($a) => str_starts_with($a->code, '71') || str_starts_with($a->code, '72')),
            'Autres produits'        => $accounts->filter(fn ($a) =>
                str_starts_with($a->code, '73') || str_starts_with($a->code, '74') || str_starts_with($a->code, '75')
            ),
            'Produits financiers'    => $accounts->filter(fn ($a) => str_starts_with($a->code, '77')),
            'Produits exceptionnels' => $accounts->filter(fn ($a) => str_starts_with($a->code, '78') || str_starts_with($a->code, '79')),
        ];

        $totalCharges  = $accounts->filter(fn ($a) => str_starts_with($a->code, '6'))->sum('net');
        $totalProduits = $accounts->filter(fn ($a) => str_starts_with($a->code, '7'))->sum('net');
        $resultat      = $totalProduits - $totalCharges;

        return [$charges, $produits, $totalCharges, $totalProduits, $resultat];
    }
}
