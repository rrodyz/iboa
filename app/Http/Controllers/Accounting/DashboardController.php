<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * [COMPTA-PRO-01] Tableau de bord du module comptabilité.
 *
 * Affiche les KPIs critiques pour qu'un comptable / DAF voie en un coup d'œil :
 *   - le résultat instantané de l'exercice en cours
 *   - les créances clients / dettes fournisseurs ouvertes
 *   - la trésorerie nette (banques + caisses)
 *   - l'activité du mois (brouillons à valider, top comptes mouvementés)
 *   - les exercices fiscaux et leur statut
 *
 * Volontairement implémenté en lecture seule : aucune écriture, aucune mutation.
 */
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounting.view');
    }

    public function index(Request $request): View
    {
        $company    = Company::firstOrFail();
        $fiscalYear = FiscalYear::where('is_current', true)->first();

        $kpis        = $this->buildKpis($company, $fiscalYear);
        $monthly     = $this->buildMonthlyActivity($company);
        $topAccounts = $this->buildTopAccountsThisMonth($company);
        $drafts      = JournalEntry::with('journalType')
            ->where('company_id', $company->id)
            ->where('status', 'brouillon')
            ->orderByDesc('entry_date')
            ->limit(5)
            ->get();

        return view('comptabilite.dashboard', compact(
            'company', 'fiscalYear', 'kpis', 'monthly', 'topAccounts', 'drafts'
        ));
    }

    private function buildKpis(Company $company, ?FiscalYear $fy): array
    {
        // Périmètre = exercice courant si défini, sinon toutes les écritures validées
        $entryIds = $fy
            ? JournalEntry::where('company_id', $company->id)
                ->where('fiscal_year_id', $fy->id)
                ->where('status', '!=', 'brouillon')
                ->whereNull('deleted_at')
                ->pluck('id')
            : JournalEntry::where('company_id', $company->id)
                ->where('status', '!=', 'brouillon')
                ->whereNull('deleted_at')
                ->pluck('id');

        // Charges (classe 6) — débit - crédit
        $charges = (int) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('l.journal_entry_id', $entryIds)
            ->where('a.code', 'like', '6%')
            ->selectRaw('COALESCE(SUM(l.debit) - SUM(l.credit), 0) AS net')
            ->value('net');

        // Produits (classe 7) — crédit - débit
        $produits = (int) DB::table('journal_entry_lines as l')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('l.journal_entry_id', $entryIds)
            ->where('a.code', 'like', '7%')
            ->selectRaw('COALESCE(SUM(l.credit) - SUM(l.debit), 0) AS net')
            ->value('net');

        $resultat = $produits - $charges;

        // Créances clients (classe 41) — débit - crédit
        $creances = (int) Account::where('company_id', $company->id)
            ->where('code', 'like', '41%')
            ->selectRaw('COALESCE(SUM(debit_balance) - SUM(credit_balance), 0) AS net')
            ->value('net');

        // Dettes fournisseurs (classe 40) — crédit - débit
        $dettes = (int) Account::where('company_id', $company->id)
            ->where('code', 'like', '40%')
            ->selectRaw('COALESCE(SUM(credit_balance) - SUM(debit_balance), 0) AS net')
            ->value('net');

        // Trésorerie nette (classes 52 banque + 53 instr. + 57 caisse)
        $treso = (int) Account::where('company_id', $company->id)
            ->where(function ($q) {
                $q->where('code', 'like', '52%')
                  ->orWhere('code', 'like', '53%')
                  ->orWhere('code', 'like', '57%');
            })
            ->selectRaw('COALESCE(SUM(debit_balance) - SUM(credit_balance), 0) AS net')
            ->value('net');

        return [
            'charges'   => $charges,
            'produits'  => $produits,
            'resultat'  => $resultat,
            'creances'  => max(0, $creances),
            'dettes'    => max(0, $dettes),
            'tresorerie'=> $treso,
        ];
    }

    private function buildMonthlyActivity(Company $company): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth   = now()->endOfMonth();

        $brouillons = JournalEntry::where('company_id', $company->id)
            ->where('status', 'brouillon')->whereNull('deleted_at')->count();

        $valideesMonth = JournalEntry::where('company_id', $company->id)
            ->where('status', '!=', 'brouillon')
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->whereNull('deleted_at')->count();

        $totalMonth = (int) JournalEntry::where('company_id', $company->id)
            ->where('status', '!=', 'brouillon')
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->whereNull('deleted_at')
            ->sum('total_debit');

        return [
            'brouillons'     => $brouillons,
            'validees_mois'  => $valideesMonth,
            'volume_mois'    => $totalMonth,
        ];
    }

    private function buildTopAccountsThisMonth(Company $company): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth   = now()->endOfMonth();

        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.company_id', $company->id)
            ->where('e.status', '!=', 'brouillon')
            ->whereBetween('e.entry_date', [$startOfMonth, $endOfMonth])
            ->whereNull('e.deleted_at')
            ->select(
                'a.code', 'a.name',
                DB::raw('SUM(l.debit) AS sd'),
                DB::raw('SUM(l.credit) AS sc'),
                DB::raw('SUM(l.debit + l.credit) AS volume')
            )
            ->groupBy('a.code', 'a.name')
            ->orderByDesc('volume')
            ->limit(8)
            ->get();

        return $rows->toArray();
    }
}
