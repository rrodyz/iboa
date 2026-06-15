<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * [TRESO] État de trésorerie — flux par compte sur une période.
 * Solde ouverture + entrées − sorties = solde clôture, par compte et global.
 * + évolution mensuelle des flux nets.
 */
class TresorerieReportController extends Controller
{
    /**
     * Journal de trésorerie : tous les mouvements de caisse/banque, chronologique.
     */
    public function journal(Request $request): View
    {
        $from      = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to        = $request->input('to',   now()->format('Y-m-d'));
        $accountId = $request->input('cash_account_id');

        $accounts   = CashAccount::orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
        $accountIds = $accounts->pluck('id');

        $query = DB::table('cash_transactions as ct')
            ->join('cash_accounts as ca', 'ca.id', '=', 'ct.cash_account_id')
            ->whereIn('ct.cash_account_id', $accountIds)
            ->whereBetween('ct.transaction_date', [$from, $to])
            ->when($accountId, fn ($q) => $q->where('ct.cash_account_id', $accountId))
            ->select('ct.id', 'ct.transaction_date', 'ct.type', 'ct.amount', 'ct.label',
                     'ct.balance_after', 'ca.name as account_name', 'ca.type as account_type')
            ->orderBy('ct.transaction_date')
            ->orderBy('ct.id');

        $movements = $query->paginate(50)->withQueryString();

        $totals = DB::table('cash_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->whereBetween('transaction_date', [$from, $to])
            ->when($accountId, fn ($q) => $q->where('cash_account_id', $accountId))
            ->selectRaw("SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) AS entrees,
                         SUM(CASE WHEN type='debit'  THEN amount ELSE 0 END) AS sorties")
            ->first();

        return view('tresorerie.journal.index', compact('movements', 'totals', 'accounts', 'from', 'to', 'accountId'));
    }

    /**
     * Alertes trésorerie : soldes faibles, échéances proches, impayés.
     */
    public function alertes(): View
    {
        $today = now()->startOfDay();
        $soon  = $today->copy()->addDays(7);

        $lowBalance = CashAccount::where('is_active', true)
            ->whereColumn('current_balance', '<', 'min_balance')
            ->where('min_balance', '>', 0)
            ->orderBy('current_balance')
            ->get();

        // Échéances clients proches (factures non soldées dues sous 7j)
        $clientsDue = DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->where('invoices.company_id', currentCompany()->id)
            ->whereIn('invoices.status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->whereBetween('invoices.due_at', [$today->toDateString(), $soon->toDateString()])
            ->select('invoices.number', 'invoices.due_at', 'invoices.remaining_amount', 'clients.name as tiers')
            ->orderBy('invoices.due_at')->limit(20)->get();

        // Échéances fournisseurs proches
        $suppliersDue = DB::table('supplier_invoices')->join('suppliers', 'suppliers.id', '=', 'supplier_invoices.supplier_id')
            ->where('supplier_invoices.company_id', currentCompany()->id)
            ->whereIn('supplier_invoices.status', ['recue', 'validee', 'partiellement_payee'])
            ->whereRaw('(supplier_invoices.total_ttc - supplier_invoices.paid_amount) > 0')
            ->whereBetween('supplier_invoices.due_at', [$today->toDateString(), $soon->toDateString()])
            ->selectRaw('supplier_invoices.number, supplier_invoices.due_at, (supplier_invoices.total_ttc - supplier_invoices.paid_amount) as remaining, suppliers.name as tiers')
            ->orderBy('supplier_invoices.due_at')->limit(20)->get();

        // Impayés clients (en retard)
        $impayes = DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->where('invoices.company_id', currentCompany()->id)
            ->whereIn('invoices.status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->whereDate('invoices.due_at', '<', $today->toDateString())
            ->where('invoices.remaining_amount', '>', 0)
            ->select('invoices.number', 'invoices.due_at', 'invoices.remaining_amount', 'clients.name as tiers')
            ->orderBy('invoices.due_at')->limit(20)->get();

        return view('tresorerie.alertes.index', compact('lowBalance', 'clientsDue', 'suppliersDue', 'impayes'));
    }

    public function etat(Request $request): View
    {
        $from = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        ['rows' => $rows, 'totals' => $totals, 'monthly' => $monthly] = $this->buildEtat($from, $to);

        return view('tresorerie.etat.index', compact('rows', 'totals', 'monthly', 'from', 'to'));
    }

    public function etatPdf(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        ['rows' => $rows, 'totals' => $totals] = $this->buildEtat($from, $to);
        $company = currentCompany();

        $pdf = Pdf::loadView('tresorerie.etat.pdf', compact('rows', 'totals', 'from', 'to', 'company'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('etat-tresorerie-' . now()->format('Ymd') . '.pdf');
    }

    /**
     * Calcule ouverture / entrées / sorties / clôture par compte + global + mensuel.
     */
    private function buildEtat(string $from, string $to): array
    {
        $accounts = CashAccount::orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
        $accountIds = $accounts->pluck('id');

        // Ouverture = Σ(credit − debit) strictement avant `from`
        $openings = DB::table('cash_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->whereDate('transaction_date', '<', $from)
            ->selectRaw('cash_account_id, SUM(CASE WHEN type = "credit" THEN amount ELSE -amount END) AS solde')
            ->groupBy('cash_account_id')
            ->pluck('solde', 'cash_account_id');

        // Mouvements de la période
        $movements = DB::table('cash_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('cash_account_id,
                SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) AS entrees,
                SUM(CASE WHEN type = "debit"  THEN amount ELSE 0 END) AS sorties')
            ->groupBy('cash_account_id')
            ->get()->keyBy('cash_account_id');

        $rows = $accounts->map(function ($a) use ($openings, $movements) {
            $ouverture = (int) ($openings[$a->id] ?? 0);
            $mv        = $movements[$a->id] ?? null;
            $entrees   = (int) ($mv->entrees ?? 0);
            $sorties   = (int) ($mv->sorties ?? 0);
            $a->ouverture = $ouverture;
            $a->entrees   = $entrees;
            $a->sorties   = $sorties;
            $a->cloture   = $ouverture + $entrees - $sorties;
            return $a;
        })->filter(fn ($a) => $a->ouverture != 0 || $a->entrees != 0 || $a->sorties != 0)->values();

        $totals = [
            'ouverture' => $rows->sum('ouverture'),
            'entrees'   => $rows->sum('entrees'),
            'sorties'   => $rows->sum('sorties'),
            'cloture'   => $rows->sum('cloture'),
        ];

        // Évolution mensuelle des flux nets (entrées − sorties) sur la période
        $ymExpr = DB::getDriverName() === 'sqlite'
            ? "substr(transaction_date, 1, 7)"
            : "DATE_FORMAT(transaction_date, '%Y-%m')";

        $monthly = DB::table('cash_transactions')
            ->whereIn('cash_account_id', $accountIds)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw("{$ymExpr} AS mois,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS entrees,
                SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) AS sorties")
            ->groupByRaw($ymExpr)
            ->orderByRaw($ymExpr)
            ->get();

        return ['rows' => $rows, 'totals' => $totals, 'monthly' => $monthly];
    }
}
