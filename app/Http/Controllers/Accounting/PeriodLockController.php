<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriodLock;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * [COMPTA-PRO-05] Gestion des verrouillages mensuels.
 *
 * Vue calendaire 12 mois × N exercices : un clic pour verrouiller/déverrouiller
 * un mois. Le verrouillage empêche toute modification d'écriture sur la période.
 */
class PeriodLockController extends Controller
{
    public function __construct()
    {
        // La gestion des locks est réservée aux validateurs comptables
        $this->middleware('can:accounting.validate');
    }

    public function index(Request $request): View
    {
        $company  = Company::firstOrFail();
        $fyId     = $request->integer('fiscal_year_id')
            ?: optional(FiscalYear::where('is_current', true)->first())->id
            ?: optional(FiscalYear::orderByDesc('starts_at')->first())->id;

        $fiscalYear  = $fyId ? FiscalYear::find($fyId) : null;
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();

        $months = [];
        if ($fiscalYear) {
            $start = $fiscalYear->starts_at->copy()->startOfMonth();
            $end   = $fiscalYear->ends_at->copy()->endOfMonth();
            $cursor = $start->copy();
            while ($cursor <= $end) {
                $year  = (int) $cursor->format('Y');
                $month = (int) $cursor->format('n');
                $lock  = AccountingPeriodLock::findFor($company->id, $year, $month);

                // Stats : compte d'écritures + montant total ce mois (validées)
                $stats = DB::table('journal_entries')
                    ->where('company_id', $company->id)
                    ->whereYear('entry_date', $year)
                    ->whereMonth('entry_date', $month)
                    ->whereNull('deleted_at')
                    ->selectRaw('
                        COUNT(*) as total_count,
                        SUM(CASE WHEN status = "brouillon" THEN 1 ELSE 0 END) as draft_count,
                        SUM(CASE WHEN status != "brouillon" THEN total_debit ELSE 0 END) as total_volume
                    ')
                    ->first();

                $months[] = [
                    'year'         => $year,
                    'month'        => $month,
                    'label'        => $cursor->translatedFormat('F Y'),
                    'lock'         => $lock,
                    'total_count'  => (int) ($stats->total_count ?? 0),
                    'draft_count'  => (int) ($stats->draft_count ?? 0),
                    'total_volume' => (int) ($stats->total_volume ?? 0),
                ];
                $cursor->addMonth();
            }
        }

        return view('comptabilite.period-locks', compact(
            'company', 'fiscalYear', 'fiscalYears', 'months'
        ));
    }

    public function lock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year'   => ['required', 'integer', 'min:2000', 'max:2100'],
            'month'  => ['required', 'integer', 'min:1', 'max:12'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $company = Company::firstOrFail();

        // Sanity : pas de brouillons restants dans le mois — sinon ils seraient
        // figés inutilement. On bloque tant qu'ils ne sont pas validés ou supprimés.
        $drafts = JournalEntry::where('company_id', $company->id)
            ->whereYear('entry_date', $data['year'])
            ->whereMonth('entry_date', $data['month'])
            ->where('status', 'brouillon')
            ->whereNull('deleted_at')
            ->count();

        if ($drafts > 0) {
            return back()->with('error', sprintf(
                "Impossible de verrouiller : %d écriture(s) en brouillon subsistent sur ce mois. "
                . "Validez-les ou supprimez-les avant de verrouiller la période.",
                $drafts
            ));
        }

        AccountingPeriodLock::create([
            'company_id' => $company->id,
            'year'       => $data['year'],
            'month'      => $data['month'],
            'locked_at'  => now(),
            'locked_by'  => Auth::id(),
            'reason'     => $data['reason'] ?? null,
        ]);

        $monthsFr = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
        return back()->with('success', sprintf(
            "Période %s %d verrouillée. Plus aucune écriture ne peut être créée, modifiée ou validée sur ce mois.",
            $monthsFr[$data['month']], $data['year']
        ));
    }

    public function unlock(AccountingPeriodLock $lock): RedirectResponse
    {
        $label = $lock->label();
        $lock->delete();
        return back()->with('success', "Période $label déverrouillée. Les écritures de ce mois sont à nouveau modifiables.");
    }
}
