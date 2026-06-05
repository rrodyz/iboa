<?php

namespace App\Http\Controllers;

use App\Exports\Reports\CaReportExport;
use App\Exports\Reports\MarginsReportExport;
use App\Exports\Reports\SalesPerformanceExport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private array $validStatuses = ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'];

    /**
     * Returns [sortExpr, labelExpr] for a given column and groupBy strategy.
     * Works on both MySQL (production) and SQLite (tests).
     */
    private function dateGroupExprs(string $col, string $groupBy, string $driver): array
    {
        if ($groupBy === 'day') {
            // DATE() is supported by both MySQL and SQLite
            return ["date({$col})", "date({$col})"];
        }

        if ($groupBy === 'week') {
            if ($driver === 'sqlite') {
                // SQLite: group by ISO year-week, e.g. '2024-W20'
                $sort  = "strftime('%Y', {$col}) || '-W' || strftime('%W', {$col})";
                $label = $sort;
            } else {
                // MySQL: YEARWEEK returns integer like 202420; label as 'Sem. WW YYYY'
                $sort  = "YEARWEEK({$col}, 1)";
                $label = "CONCAT('Sem. ', LPAD(WEEK({$col}, 1), 2, '0'), ' ', YEAR({$col}))";
            }
            return [$sort, $label];
        }

        // Default: month grouping — substr(col, 1, 7) gives 'YYYY-MM' on both DBs
        $sort  = "substr({$col}, 1, 7)";
        $label = $driver === 'sqlite'
            ? "substr({$col}, 1, 7)"
            : "DATE_FORMAT({$col}, '%m/%Y')";

        return [$sort, $label];
    }

    // ── Hub ──────────────────────────────────────────────────────────────────
    public function index()
    {
        return view('reports.index');
    }

    // ── Rapport CA ───────────────────────────────────────────────────────────
    public function ca(Request $request)
    {
        $from      = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to        = $request->input('to',   now()->format('Y-m-d'));
        $clientId  = $request->input('client_id');
        $groupBy   = in_array($request->input('group_by'), ['day', 'week', 'month'])
                        ? $request->input('group_by')
                        : 'month';

        $query = Invoice::whereIn('status', $this->validStatuses)
            ->whereBetween('issued_at', [$from, $to . ' 23:59:59']);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        // Totaux globaux
        $totals = (clone $query)->selectRaw('
            COUNT(*) as nb_factures,
            SUM(subtotal_ht) as total_ht,
            SUM(total_tax) as total_tva,
            SUM(total_ttc) as total_ttc,
            SUM(paid_amount) as total_encaisse,
            SUM(remaining_amount) as total_reste
        ')->first();

        // Série temporelle — expressions cross-DB (MySQL + SQLite)
        $driver = \DB::getDriverName();
        [$sortExpr, $labelExpr] = $this->dateGroupExprs('issued_at', $groupBy, $driver);

        $serie = (clone $query)
            ->selectRaw("{$labelExpr} as label,
                         {$sortExpr} as sort_key,
                         COUNT(*) as nb,
                         SUM(subtotal_ht) as ht,
                         SUM(total_ttc) as ttc,
                         SUM(paid_amount) as encaisse")
            ->groupByRaw($sortExpr !== $labelExpr ? "{$sortExpr}, {$labelExpr}" : $sortExpr)
            ->orderByRaw($sortExpr)
            ->get();

        // Top 10 clients sur la période
        $topClients = (clone $query)
            ->select('client_id', DB::raw('SUM(total_ttc) as ca, COUNT(*) as nb'))
            ->groupBy('client_id')
            ->with('client:id,name')
            ->orderByDesc('ca')
            ->limit(10)
            ->get();

        // Listes filtres
        $clients = Client::orderBy('name')->get(['id', 'name']);

        if ($request->input('export') === 'excel') {
            return Excel::download(
                new CaReportExport($serie, $totals, $from, $to),
                'rapport-ca-' . now()->format('Ymd') . '.xlsx'
            );
        }

        return view('reports.ca', compact(
            'from', 'to', 'clientId', 'groupBy',
            'totals', 'serie', 'topClients', 'clients'
        ));
    }

    // ── Rapport Marges ───────────────────────────────────────────────────────
    public function margins(Request $request)
    {
        $from     = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $familyId = $request->input('family_id');
        $sortBy   = $request->input('sort_by', 'marge_brute'); // marge_brute | taux_marge | ca_ht | qty

        $query = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            // [SECU/MULTI-TENANT] invoice_items n'a pas de company_id et le scope global
            // d'Invoice ne s'applique pas à une table jointe → filtrer explicitement.
            ->where('invoices.company_id', currentCompany()->id)
            ->whereIn('invoices.status', $this->validStatuses)
            ->whereBetween('invoices.issued_at', [$from, $to . ' 23:59:59'])
            ->whereNotNull('invoice_items.product_id')
            // [MARGES-CMP] Coût par priorité :
            //   1. invoice_items.unit_cost — coût figé à la validation (historique exact)
            //   2. products.weighted_avg_cost — CMP courant
            //   3. products.purchase_price — dernier prix d'achat (repli ultime)
            ->select(
                'products.id',
                'products.name',
                'products.reference',
                'products.purchase_price',
                'products.weighted_avg_cost',
                DB::raw('SUM(invoice_items.quantity) as qty_vendue'),
                DB::raw('SUM(invoice_items.line_total_ht) as ca_ht'),
                DB::raw('SUM(invoice_items.quantity * COALESCE(NULLIF(invoice_items.unit_cost,0), NULLIF(products.weighted_avg_cost,0), products.purchase_price)) as cout_achats'),
                DB::raw('SUM(invoice_items.line_total_ht - (invoice_items.quantity * COALESCE(NULLIF(invoice_items.unit_cost,0), NULLIF(products.weighted_avg_cost,0), products.purchase_price))) as marge_brute')
            )
            ->groupBy('products.id', 'products.name', 'products.reference', 'products.purchase_price', 'products.weighted_avg_cost');

        if ($familyId) {
            $query->where('products.family_id', $familyId);
        }

        $allowedSorts = ['marge_brute', 'ca_ht', 'qty_vendue', 'cout_achats'];
        $sortColumn   = in_array($sortBy, $allowedSorts) ? $sortBy : 'marge_brute';
        $products     = $query->orderByDesc($sortColumn)->get();

        // Totaux
        $totalCaHt      = $products->sum('ca_ht');
        $totalCout      = $products->sum('cout_achats');
        $totalMarge     = $products->sum('marge_brute');
        $tauxMoyen      = $totalCaHt > 0 ? round(($totalMarge / $totalCaHt) * 100, 1) : 0;

        // Ajouter taux de marge par produit
        $products = $products->map(function ($p) {
            $p->taux_marge = $p->ca_ht > 0 ? round(($p->marge_brute / $p->ca_ht) * 100, 1) : 0;
            return $p;
        });

        // Top 10 pour le graphique
        $top10 = $products->sortByDesc('marge_brute')->take(10);

        $families = \App\Models\ProductFamily::whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        if ($request->input('export') === 'excel') {
            return Excel::download(
                new MarginsReportExport($products, $from, $to),
                'rapport-marges-' . now()->format('Ymd') . '.xlsx'
            );
        }

        return view('reports.margins', compact(
            'from', 'to', 'familyId', 'sortBy',
            'products', 'totalCaHt', 'totalCout', 'totalMarge', 'tauxMoyen',
            'top10', 'families'
        ));
    }

    // ── Rapport Achats ────────────────────────────────────────────────────────
    public function achats(Request $request)
    {
        $from       = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to         = $request->input('to',   now()->format('Y-m-d'));
        $supplierId = $request->input('supplier_id');
        $groupBy    = in_array($request->input('group_by'), ['day', 'week', 'month'])
                        ? $request->input('group_by')
                        : 'month';

        $validStatuses = ['recue', 'validee', 'partiellement_payee', 'payee', 'en_litige'];

        $query = SupplierInvoice::whereIn('status', $validStatuses)
            ->whereBetween('received_at', [$from, $to . ' 23:59:59']);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Global totals
        $totals = (clone $query)->selectRaw('
            COUNT(*) as nb_factures,
            SUM(subtotal_ht) as total_ht,
            SUM(total_tax) as total_tva,
            SUM(total_ttc) as total_ttc,
            SUM(paid_amount) as total_paye,
            SUM(remaining_amount) as total_reste
        ')->first();

        // Time series — expressions cross-DB (MySQL + SQLite)
        $driver = \DB::getDriverName();
        [$sortExpr, $labelExpr] = $this->dateGroupExprs('received_at', $groupBy, $driver);

        $serie = (clone $query)
            ->selectRaw("{$labelExpr} as label,
                         {$sortExpr} as sort_key,
                         COUNT(*) as nb,
                         SUM(subtotal_ht) as ht,
                         SUM(total_ttc) as ttc,
                         SUM(paid_amount) as paye")
            ->groupByRaw($sortExpr !== $labelExpr ? "{$sortExpr}, {$labelExpr}" : $sortExpr)
            ->orderByRaw($sortExpr)
            ->get();

        // Top 10 suppliers
        $topSuppliers = (clone $query)
            ->select('supplier_id', DB::raw('SUM(total_ttc) as total, COUNT(*) as nb'))
            ->groupBy('supplier_id')
            ->with('supplier:id,name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('reports.achats', compact(
            'from', 'to', 'supplierId', 'groupBy',
            'totals', 'serie', 'topSuppliers', 'suppliers'
        ));
    }

    // ── Âge des créances ─────────────────────────────────────────────────────
    public function agingReceivables(Request $request)
    {
        $asOf      = $request->input('as_of', now()->format('Y-m-d'));
        $clientId  = $request->input('client_id');
        $asOfDate  = Carbon::parse($asOf);

        $query = Invoice::whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->where('remaining_amount', '>', 0)
            ->where('issued_at', '<=', $asOfDate)
            ->with('client:id,name');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $invoices = $query->orderBy('due_at')->get();

        // Bucket each invoice: current, 1-30, 31-60, 61-90, +90
        $buckets = ['current' => [], '1_30' => [], '31_60' => [], '61_90' => [], 'over_90' => []];
        foreach ($invoices as $inv) {
            $daysOverdue = $inv->due_at ? (int) $asOfDate->diffInDays($inv->due_at, false) * -1 : 0;
            if ($daysOverdue <= 0)       $buckets['current'][]  = $inv;
            elseif ($daysOverdue <= 30)  $buckets['1_30'][]     = $inv;
            elseif ($daysOverdue <= 60)  $buckets['31_60'][]    = $inv;
            elseif ($daysOverdue <= 90)  $buckets['61_90'][]    = $inv;
            else                         $buckets['over_90'][]  = $inv;
        }

        // Aggregate by client
        $byClient = $invoices->groupBy('client_id')->map(function ($clientInvoices) use ($asOfDate) {
            $row = [
                'client'   => $clientInvoices->first()->client,
                'current'  => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0,
                'total'    => 0,
            ];
            foreach ($clientInvoices as $inv) {
                $daysOverdue = $inv->due_at ? (int) $asOfDate->diffInDays($inv->due_at, false) * -1 : 0;
                $amount = (int) $inv->remaining_amount;
                $row['total'] += $amount;
                if ($daysOverdue <= 0)       $row['current']  += $amount;
                elseif ($daysOverdue <= 30)  $row['1_30']     += $amount;
                elseif ($daysOverdue <= 60)  $row['31_60']    += $amount;
                elseif ($daysOverdue <= 90)  $row['61_90']    += $amount;
                else                         $row['over_90']  += $amount;
            }
            return $row;
        })->sortByDesc('total')->values();

        $totals = [
            'current'  => $invoices->filter(fn($i) => $i->due_at && (int)($asOfDate->diffInDays($i->due_at, false)*-1) <= 0)->sum('remaining_amount'),
            '1_30'     => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0,
            'total'    => $invoices->sum('remaining_amount'),
        ];
        foreach ($invoices as $inv) {
            $d = $inv->due_at ? (int) $asOfDate->diffInDays($inv->due_at, false) * -1 : 0;
            if ($d > 0  && $d <= 30) $totals['1_30']    += $inv->remaining_amount;
            if ($d > 30 && $d <= 60) $totals['31_60']   += $inv->remaining_amount;
            if ($d > 60 && $d <= 90) $totals['61_90']   += $inv->remaining_amount;
            if ($d > 90)             $totals['over_90'] += $inv->remaining_amount;
        }
        // Fix current total
        $totals['current'] = $totals['total'] - $totals['1_30'] - $totals['31_60'] - $totals['61_90'] - $totals['over_90'];

        $clients = Client::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('reports.aging-receivables', compact(
            'asOf', 'clientId', 'byClient', 'totals', 'clients', 'invoices'
        ));
    }

    // ── Performance commerciale ───────────────────────────────────────────────
    public function salesPerformance(Request $request)
    {
        $from   = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to     = $request->input('to',   now()->format('Y-m-d'));
        $userId = $request->input('user_id');

        $query = Invoice::whereIn('status', $this->validStatuses)
            ->whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->whereNotNull('created_by');

        if ($userId) {
            $query->where('created_by', $userId);
        }

        // Performance par commercial
        $perUser = (clone $query)
            ->select(
                'created_by',
                DB::raw('COUNT(*) as nb_factures'),
                DB::raw('COUNT(DISTINCT client_id) as nb_clients'),
                DB::raw('SUM(total_ttc) as ca_total'),
                DB::raw('SUM(paid_amount) as encaisse'),
                DB::raw('SUM(remaining_amount) as reste'),
                DB::raw('AVG(total_ttc) as panier_moyen')
            )
            ->groupBy('created_by')
            ->with('creator:id,name')
            ->orderByDesc('ca_total')
            ->get();

        // Totaux
        $grandTotal = (clone $query)->selectRaw('
            COUNT(*) as nb_factures,
            SUM(total_ttc) as ca_total,
            SUM(paid_amount) as encaisse
        ')->first();

        // Évolution mensuelle par commercial — cross-DB
        $ymExpr = \DB::getDriverName() === 'sqlite'
            ? "substr(issued_at, 1, 7)"
            : "DATE_FORMAT(issued_at, '%Y-%m')";

        $monthlyQuery = Invoice::whereIn('status', $this->validStatuses)
            ->whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->whereNotNull('created_by')
            ->selectRaw("{$ymExpr} as month, created_by, SUM(total_ttc) as ca")
            ->groupByRaw("{$ymExpr}, created_by")
            ->orderByRaw("{$ymExpr}")
            ->with('creator:id,name')
            ->get();

        // Construire les labels mois à partir des clés
        $months = $monthlyQuery->pluck('month')->unique()->values()->map(
            fn($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->locale('fr')->isoFormat('MM/YYYY')
        );
        $commerciaux = $perUser->pluck('creator.name', 'created_by');

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->input('export') === 'excel') {
            return Excel::download(
                new SalesPerformanceExport($perUser, $from, $to),
                'performance-commerciale-' . now()->format('Ymd') . '.xlsx'
            );
        }

        return view('reports.sales-performance', compact(
            'from', 'to', 'userId',
            'perUser', 'grandTotal',
            'monthlyQuery', 'months', 'commerciaux',
            'users'
        ));
    }

    // ── PDF — Rapport CA ─────────────────────────────────────────────────────
    public function caPdf(Request $request): mixed
    {
        $from     = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $clientId = $request->input('client_id');
        $groupBy  = in_array($request->input('group_by'), ['day', 'week', 'month'])
                        ? $request->input('group_by')
                        : 'month';

        $query = Invoice::whereIn('status', $this->validStatuses)
            ->whereBetween('issued_at', [$from, $to . ' 23:59:59']);
        if ($clientId) { $query->where('client_id', $clientId); }

        $totals = (clone $query)->selectRaw('
            COUNT(*) as nb_factures,
            SUM(subtotal_ht) as total_ht,
            SUM(total_ttc) as total_ttc,
            SUM(paid_amount) as total_encaisse
        ')->first();

        $driver = DB::getDriverName();
        [$sortExpr, $labelExpr] = $this->dateGroupExprs('issued_at', $groupBy, $driver);
        $serie = (clone $query)
            ->selectRaw("{$labelExpr} as label, {$sortExpr} as sort_key, COUNT(*) as nb, SUM(subtotal_ht) as ht, SUM(total_ttc) as ttc, SUM(paid_amount) as encaisse")
            ->groupByRaw($sortExpr !== $labelExpr ? "{$sortExpr}, {$labelExpr}" : $sortExpr)->orderByRaw($sortExpr)->get();

        $company = currentCompany();
        $pdf = Pdf::loadView('reports.pdf.ca', compact('company', 'serie', 'totals', 'from', 'to'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('rapport-ca_' . now()->format('Ymd_His') . '.pdf');
    }

    // ── PDF — Rapport Marges ─────────────────────────────────────────────────
    public function marginsPdf(Request $request): mixed
    {
        $from     = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $familyId = $request->input('family_id');
        $sortBy   = $request->input('sort_by', 'marge_brute');

        $query = InvoiceItem::query()
            ->join('invoices',  'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products',  'invoice_items.product_id', '=', 'products.id')
            ->whereIn('invoices.status', $this->validStatuses)
            ->whereBetween('invoices.issued_at', [$from, $to . ' 23:59:59'])
            ->whereNotNull('invoice_items.product_id')
            ->select(
                'products.id', 'products.name', 'products.reference', 'products.purchase_price',
                DB::raw('SUM(invoice_items.quantity) as qty_vendue'),
                DB::raw('SUM(invoice_items.line_total_ht) as ca_ht'),
                DB::raw('SUM(invoice_items.quantity * products.purchase_price) as cout_achats'),
                DB::raw('SUM(invoice_items.line_total_ht - (invoice_items.quantity * products.purchase_price)) as marge_brute')
            )
            ->groupBy('products.id', 'products.name', 'products.reference', 'products.purchase_price');

        if ($familyId) { $query->where('products.family_id', $familyId); }
        $allowedSorts = ['marge_brute', 'ca_ht', 'qty_vendue', 'cout_achats'];
        $sortColumn   = in_array($sortBy, $allowedSorts) ? $sortBy : 'marge_brute';

        $products = $query->orderByDesc($sortColumn)->get()->map(function ($p) {
            $p->taux_marge = $p->ca_ht > 0 ? round(($p->marge_brute / $p->ca_ht) * 100, 1) : 0;
            return $p;
        });

        $totalCaHt  = $products->sum('ca_ht');
        $totalCout  = $products->sum('cout_achats');
        $totalMarge = $products->sum('marge_brute');
        $tauxMoyen  = $totalCaHt > 0 ? round(($totalMarge / $totalCaHt) * 100, 1) : 0;

        $company = currentCompany();
        $pdf = Pdf::loadView('reports.pdf.margins', compact('company', 'products', 'totalCaHt', 'totalCout', 'totalMarge', 'tauxMoyen', 'from', 'to'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('rapport-marges_' . now()->format('Ymd_His') . '.pdf');
    }

    // ── PDF — Performance commerciale ────────────────────────────────────────
    public function salesPerformancePdf(Request $request): mixed
    {
        $from   = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to     = $request->input('to',   now()->format('Y-m-d'));
        $userId = $request->input('user_id');

        $query = Invoice::whereIn('status', $this->validStatuses)
            ->whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->whereNotNull('created_by');
        if ($userId) { $query->where('created_by', $userId); }

        $perUser = (clone $query)
            ->select(
                'created_by',
                DB::raw('COUNT(*) as nb_factures'),
                DB::raw('COUNT(DISTINCT client_id) as nb_clients'),
                DB::raw('SUM(total_ttc) as ca_total'),
                DB::raw('SUM(paid_amount) as encaisse'),
                DB::raw('SUM(remaining_amount) as reste'),
                DB::raw('AVG(total_ttc) as panier_moyen')
            )
            ->groupBy('created_by')
            ->with('creator:id,name')
            ->orderByDesc('ca_total')
            ->get();

        $grandTotal = (clone $query)->selectRaw('
            COUNT(*) as nb_factures,
            SUM(total_ttc) as ca_total,
            SUM(paid_amount) as encaisse
        ')->first();

        $company = currentCompany();
        $pdf = Pdf::loadView('reports.pdf.sales-performance', compact('company', 'perUser', 'grandTotal', 'from', 'to'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('performance-commerciale_' . now()->format('Ymd_His') . '.pdf');
    }
}
