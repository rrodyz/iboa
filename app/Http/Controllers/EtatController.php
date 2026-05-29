<?php

namespace App\Http\Controllers;

use App\Exports\Reports\GenericTableExport;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SupplierInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * États & rapports opérationnels :
 *   - Journal des ventes
 *   - État des stocks
 *   - Mouvements de stock
 *   - État des impayés clients
 *   - État de TVA
 *   - Liste des factures / devis / commandes
 */
class EtatController extends Controller
{
    // ── Statuts factures "émises" ─────────────────────────────────────────────
    private array $salesStatuses = [
        'validee', 'envoyee', 'partiellement_payee', 'payee', 'en_retard',
    ];

    // =========================================================================
    //  JOURNAL DES VENTES
    // =========================================================================

    public function journalVentes(Request $request): mixed
    {
        $from     = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $clientId = $request->input('client_id');

        $query = Invoice::whereIn('status', $this->salesStatuses)
            ->whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->with('client:id,name')
            ->orderBy('issued_at')
            ->orderBy('number');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $rows = $query->get(['id', 'number', 'issued_at', 'client_id', 'status',
                             'subtotal_ht', 'total_discount', 'total_tax', 'total_ttc']);

        $totals = [
            'ht'  => $rows->sum('subtotal_ht'),
            'rem' => $rows->sum('total_discount'),
            'tva' => $rows->sum('total_tax'),
            'ttc' => $rows->sum('total_ttc'),
        ];

        // Nom du client filtré (pour le titre PDF/Excel)
        $clientName = $clientId
            ? optional(Client::find($clientId))->name
            : null;

        $sheetTitle = $clientName
            ? 'Journal des ventes — ' . $clientName
            : 'Journal des ventes';

        if ($request->input('export') === 'excel') {
            $data = $rows->map(fn($r) => [
                $r->number,
                $r->issued_at?->format('d/m/Y') ?? '',
                $r->client?->name ?? '—',
                $this->statusLabel($r->status),
                number_format($r->subtotal_ht, 0, ',', ' '),
                number_format($r->total_discount, 0, ',', ' '),
                number_format($r->total_tax, 0, ',', ' '),
                number_format($r->total_ttc, 0, ',', ' '),
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle: $sheetTitle,
                headers:    ['N° Facture', 'Date', 'Client', 'Statut', 'HT', 'Remise', 'TVA', 'TTC'],
                data:       $data,
                from:       $from,
                to:         $to,
                numericColIdxs: [4, 5, 6, 7],
                colWidths:  ['A' => 14, 'B' => 12, 'C' => 28, 'D' => 14, 'E' => 14, 'F' => 12, 'G' => 12, 'H' => 14],
                totals:     ['TOTAL', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['rem'], 0, ',', ' '), number_format($totals['tva'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' ')],
            ), 'journal-ventes-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf($sheetTitle, $from, $to,
                headers: [
                    ['label' => 'N° Facture', 'align' => 'l'],
                    ['label' => 'Date',       'align' => 'c'],
                    ['label' => 'Client',     'align' => 'l'],
                    ['label' => 'Statut',     'align' => 'c'],
                    ['label' => 'HT',         'align' => 'r'],
                    ['label' => 'Remise',     'align' => 'r'],
                    ['label' => 'TVA',        'align' => 'r'],
                    ['label' => 'TTC',        'align' => 'r'],
                ],
                rows: $rows->map(fn($r) => [
                    $r->number,
                    $r->issued_at?->format('d/m/Y') ?? '',
                    $r->client?->name ?? '—',
                    '<span class="badge badge-' . $this->statusColor($r->status) . '">' . $this->statusLabel($r->status) . '</span>',
                    number_format($r->subtotal_ht, 0, ',', ' '),
                    number_format($r->total_discount, 0, ',', ' '),
                    number_format($r->total_tax, 0, ',', ' '),
                    number_format($r->total_ttc, 0, ',', ' '),
                ])->toArray(),
                kpis: [
                    'Nb factures' => $rows->count(),
                    'Total HT'    => number_format($totals['ht'],  0, ',', ' ') . ' F',
                    'Total TVA'   => number_format($totals['tva'], 0, ',', ' ') . ' F',
                    'Total TTC'   => number_format($totals['ttc'], 0, ',', ' ') . ' F',
                ],
                totalsRow: ['TOTAL', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['rem'], 0, ',', ' '), number_format($totals['tva'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' ')],
                filename: 'journal-ventes',
            );
        }

        $clients = Client::orderBy('name')->get(['id', 'name']);

        return view('reports.journal-ventes', compact('from', 'to', 'clientId', 'clientName', 'rows', 'totals', 'clients'));
    }

    // =========================================================================
    //  ÉTAT DES STOCKS
    // =========================================================================

    public function etatStocks(Request $request): mixed
    {
        $warehouseId = $request->input('warehouse_id');
        $familyId    = $request->input('family_id');
        $search      = $request->input('search');

        $query = DB::table('product_stocks as ps')
            ->join('products as p', 'p.id', '=', 'ps.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'ps.warehouse_id')
            ->leftJoin('product_families as pf', 'pf.id', '=', 'p.family_id')
            ->whereNull('p.deleted_at')
            ->where('p.is_active', true)
            ->select(
                'p.reference',
                'p.name as product_name',
                'pf.name as family_name',
                'w.name as warehouse_name',
                'ps.quantity',
                'ps.reserved_quantity',
                DB::raw('(ps.quantity - ps.reserved_quantity) as dispo'),
                'ps.avg_cost',
                DB::raw('(ps.quantity * ps.avg_cost) as valeur'),
                'ps.last_movement_at',
            );

        if ($warehouseId) {
            $query->where('ps.warehouse_id', $warehouseId);
        }
        if ($familyId) {
            $query->where('p.family_id', $familyId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'like', "%{$search}%")
                  ->orWhere('p.reference', 'like', "%{$search}%");
            });
        }

        $stocks = $query->orderBy('p.name')->get();

        $totals = [
            'qty'    => $stocks->sum('quantity'),
            'dispo'  => $stocks->sum('dispo'),
            'valeur' => $stocks->sum('valeur'),
        ];

        if ($request->input('export') === 'excel') {
            $data = $stocks->map(fn($s) => [
                $s->reference ?? '',
                $s->product_name,
                $s->family_name ?? '',
                $s->warehouse_name ?? '',
                $s->quantity,
                $s->reserved_quantity,
                $s->dispo,
                number_format((float) $s->avg_cost, 0, ',', ' '),
                number_format((float) $s->valeur, 0, ',', ' '),
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle:    'État des stocks',
                headers:       ['Référence', 'Produit', 'Famille', 'Dépôt', 'Qté totale', 'Réservé', 'Disponible', 'Coût moy.', 'Valeur stock'],
                data:          $data,
                numericColIdxs: [4, 5, 6, 7, 8],
                colWidths:     ['A' => 14, 'B' => 32, 'C' => 18, 'D' => 16, 'E' => 12, 'F' => 10, 'G' => 12, 'H' => 12, 'I' => 16],
                totals:        ['', 'TOTAL', '', '', $totals['qty'], '', $totals['dispo'], '', number_format((float) $totals['valeur'], 0, ',', ' ')],
            ), 'etat-stocks-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf('État des stocks', null, null,
                headers: [
                    ['label' => 'Référence',  'align' => 'l'],
                    ['label' => 'Produit',    'align' => 'l'],
                    ['label' => 'Famille',    'align' => 'l'],
                    ['label' => 'Dépôt',      'align' => 'l'],
                    ['label' => 'Qté',        'align' => 'r'],
                    ['label' => 'Réservé',    'align' => 'r'],
                    ['label' => 'Disponible', 'align' => 'r'],
                    ['label' => 'Coût moy.',  'align' => 'r'],
                    ['label' => 'Valeur',     'align' => 'r'],
                ],
                rows: $stocks->map(fn($s) => [
                    $s->reference ?? '',
                    $s->product_name,
                    $s->family_name ?? '',
                    $s->warehouse_name ?? '',
                    number_format((float) $s->quantity, 0, ',', ' '),
                    number_format((float) $s->reserved_quantity, 0, ',', ' '),
                    number_format((float) $s->dispo, 0, ',', ' '),
                    number_format((float) $s->avg_cost, 0, ',', ' '),
                    number_format((float) $s->valeur, 0, ',', ' '),
                ])->toArray(),
                kpis: [
                    'Nb articles'   => $stocks->count(),
                    'Qté totale'    => number_format((float) $totals['qty'], 0, ',', ' '),
                    'Valeur totale' => number_format((float) $totals['valeur'], 0, ',', ' ') . ' F',
                ],
                totalsRow: ['', 'TOTAL', '', '', number_format((float) $totals['qty'], 0, ',', ' '), '', number_format((float) $totals['dispo'], 0, ',', ' '), '', number_format((float) $totals['valeur'], 0, ',', ' ')],
                filename: 'etat-stocks',
                subtitle: 'Édité le ' . now()->format('d/m/Y H:i'),
            );
        }

        $warehouses = DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $families   = DB::table('product_families')->whereNull('parent_id')->orderBy('name')->get(['id', 'name']);

        return view('reports.etat-stocks', compact(
            'stocks', 'totals', 'warehouseId', 'familyId', 'search',
            'warehouses', 'families'
        ));
    }

    // =========================================================================
    //  MOUVEMENTS DE STOCK
    // =========================================================================

    public function mouvementsStock(Request $request): mixed
    {
        $from        = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to          = $request->input('to',   now()->format('Y-m-d'));
        $warehouseId = $request->input('warehouse_id');
        $type        = $request->input('type');  // entree | sortie | transfert | ajustement

        $query = DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sm.warehouse_id')
            ->whereNull('p.deleted_at')
            ->whereBetween('sm.occurred_at', [$from, $to . ' 23:59:59'])
            ->select(
                'sm.occurred_at',
                'p.reference',
                'p.name as product_name',
                'w.name as warehouse_name',
                'sm.type',
                'sm.quantity',
                'sm.unit_cost',
                'sm.total_cost',
                'sm.notes',
            );

        if ($warehouseId) {
            $query->where('sm.warehouse_id', $warehouseId);
        }
        if ($type) {
            $query->where('sm.type', $type);
        }

        $movements = $query->orderBy('sm.occurred_at')->orderBy('sm.id')->get();

        $totals = [
            'qty_entree'  => $movements->where('type', 'entree')->sum('quantity'),
            'qty_sortie'  => $movements->where('type', 'sortie')->sum('quantity'),
            'valeur_total' => $movements->sum('total_cost'),
        ];

        $typeLabels = [
            'entree'      => 'Entrée',
            'sortie'      => 'Sortie',
            'transfert'   => 'Transfert',
            'ajustement'  => 'Ajustement',
            'inventaire'  => 'Inventaire',
        ];

        if ($request->input('export') === 'excel') {
            $data = $movements->map(fn($m) => [
                Carbon::parse($m->occurred_at)->format('d/m/Y'),
                $m->reference ?? '',
                $m->product_name,
                $m->warehouse_name ?? '',
                $typeLabels[$m->type] ?? $m->type,
                $m->quantity,
                number_format((float) $m->unit_cost, 0, ',', ' '),
                number_format((float) $m->total_cost, 0, ',', ' '),
                $m->notes ?? '',
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle:    'Mouvements de stock',
                headers:       ['Date', 'Référence', 'Produit', 'Dépôt', 'Type', 'Qté', 'P.U.', 'Montant', 'Notes'],
                data:          $data,
                from:          $from,
                to:            $to,
                numericColIdxs: [5, 6, 7],
                colWidths:     ['A' => 12, 'B' => 14, 'C' => 28, 'D' => 16, 'E' => 12, 'F' => 8, 'G' => 12, 'H' => 14, 'I' => 24],
            ), 'mouvements-stock-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf('Mouvements de stock', $from, $to,
                headers: [
                    ['label' => 'Date',      'align' => 'c'],
                    ['label' => 'Référence', 'align' => 'l'],
                    ['label' => 'Produit',   'align' => 'l'],
                    ['label' => 'Dépôt',     'align' => 'l'],
                    ['label' => 'Type',      'align' => 'c'],
                    ['label' => 'Qté',       'align' => 'r'],
                    ['label' => 'P.U.',      'align' => 'r'],
                    ['label' => 'Montant',   'align' => 'r'],
                ],
                rows: $movements->map(fn($m) => [
                    Carbon::parse($m->occurred_at)->format('d/m/Y'),
                    $m->reference ?? '',
                    $m->product_name,
                    $m->warehouse_name ?? '',
                    $typeLabels[$m->type] ?? $m->type,
                    number_format((float) $m->quantity, 0, ',', ' '),
                    number_format((float) $m->unit_cost, 0, ',', ' '),
                    number_format((float) $m->total_cost, 0, ',', ' '),
                ])->toArray(),
                kpis: [
                    'Nb mouvements' => $movements->count(),
                    'Entrées (qté)' => number_format((float) $totals['qty_entree'], 0, ',', ' '),
                    'Sorties (qté)' => number_format((float) $totals['qty_sortie'], 0, ',', ' '),
                    'Valeur totale' => number_format((float) $totals['valeur_total'], 0, ',', ' ') . ' F',
                ],
                filename: 'mouvements-stock',
            );
        }

        $warehouses = DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('reports.mouvements-stock', compact(
            'from', 'to', 'movements', 'totals',
            'warehouseId', 'type', 'warehouses', 'typeLabels'
        ));
    }

    // =========================================================================
    //  ÉTAT DES IMPAYÉS CLIENTS
    // =========================================================================

    public function impayes(Request $request): mixed
    {
        $asOf     = $request->input('as_of', now()->format('Y-m-d'));
        $clientId = $request->input('client_id');

        $query = Invoice::whereIn('status', ['envoyee', 'partiellement_payee', 'en_retard', 'validee'])
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('due_at')
            ->with('client:id,name,phone')
            ->orderBy('due_at');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $rows = $query->get(['id', 'number', 'issued_at', 'due_at', 'client_id',
                             'total_ttc', 'paid_amount', 'remaining_amount', 'status']);

        // Enrichir avec jours de retard
        $asOfDate = Carbon::parse($asOf);
        $rows->each(function ($r) use ($asOfDate) {
            $r->jours_retard = max(0, (int) $r->due_at->diffInDays($asOfDate, false));
        });

        $totals = [
            'count'     => $rows->count(),
            'total_ttc' => $rows->sum('total_ttc'),
            'paid'      => $rows->sum('paid_amount'),
            'remaining' => $rows->sum('remaining_amount'),
        ];

        if ($request->input('export') === 'excel') {
            $data = $rows->map(fn($r) => [
                $r->number,
                $r->issued_at?->format('d/m/Y') ?? '',
                $r->due_at?->format('d/m/Y') ?? '',
                $r->client?->name ?? '—',
                $r->client?->phone ?? '',
                number_format($r->total_ttc, 0, ',', ' '),
                number_format($r->paid_amount, 0, ',', ' '),
                number_format($r->remaining_amount, 0, ',', ' '),
                $r->jours_retard > 0 ? $r->jours_retard . ' j' : 'À échoir',
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle:    'État des impayés',
                headers:       ['N° Facture', 'Date émission', 'Échéance', 'Client', 'Téléphone', 'Total TTC', 'Réglé', 'Restant', 'Retard'],
                data:          $data,
                numericColIdxs: [5, 6, 7],
                colWidths:     ['A' => 14, 'B' => 14, 'C' => 12, 'D' => 26, 'E' => 14, 'F' => 14, 'G' => 12, 'H' => 14, 'I' => 10],
                totals:        ['TOTAL', '', '', '', '', number_format($totals['total_ttc'], 0, ',', ' '), number_format($totals['paid'], 0, ',', ' '), number_format($totals['remaining'], 0, ',', ' '), ''],
            ), 'impayes-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf('État des impayés clients', null, null,
                headers: [
                    ['label' => 'N° Facture',   'align' => 'l'],
                    ['label' => 'Émission',      'align' => 'c'],
                    ['label' => 'Échéance',      'align' => 'c'],
                    ['label' => 'Client',        'align' => 'l'],
                    ['label' => 'Total TTC',     'align' => 'r'],
                    ['label' => 'Réglé',         'align' => 'r'],
                    ['label' => 'Restant dû',    'align' => 'r'],
                    ['label' => 'Retard',        'align' => 'c'],
                ],
                rows: $rows->map(fn($r) => [
                    $r->number,
                    $r->issued_at?->format('d/m/Y') ?? '',
                    $r->due_at?->format('d/m/Y') ?? '',
                    $r->client?->name ?? '—',
                    number_format($r->total_ttc, 0, ',', ' '),
                    number_format($r->paid_amount, 0, ',', ' '),
                    number_format($r->remaining_amount, 0, ',', ' '),
                    $r->jours_retard > 0
                        ? '<span class="badge badge-red">' . $r->jours_retard . ' j</span>'
                        : '<span class="badge badge-blue">À échoir</span>',
                ])->toArray(),
                kpis: [
                    'Nb factures'  => $totals['count'],
                    'Total TTC'    => number_format($totals['total_ttc'],  0, ',', ' ') . ' F',
                    'Déjà réglé'   => number_format($totals['paid'],       0, ',', ' ') . ' F',
                    'Restant dû'   => number_format($totals['remaining'],  0, ',', ' ') . ' F',
                ],
                totalsRow: ['TOTAL', '', '', '', number_format($totals['total_ttc'], 0, ',', ' '), number_format($totals['paid'], 0, ',', ' '), number_format($totals['remaining'], 0, ',', ' '), ''],
                subtitle:  'Au ' . Carbon::parse($asOf)->format('d/m/Y'),
                filename:  'impayes',
            );
        }

        $clients = Client::orderBy('name')->get(['id', 'name']);

        return view('reports.impayes', compact('asOf', 'clientId', 'rows', 'totals', 'clients'));
    }

    // =========================================================================
    //  ÉTAT DE TVA
    // =========================================================================

    public function etatTva(Request $request): mixed
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to',   now()->format('Y-m-d'));

        // TVA collectée — depuis les lignes de factures clients
        $tvaCollectee = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->whereIn('i.status', $this->salesStatuses)
            ->whereBetween('i.issued_at', [$from, $to . ' 23:59:59'])
            ->selectRaw('ii.tax_rate_value as taux, SUM(ii.line_total_ht) as base_ht, SUM(ii.line_tax) as montant_tva')
            ->groupBy('ii.tax_rate_value')
            ->orderBy('ii.tax_rate_value')
            ->get();

        // TVA déductible — depuis les factures fournisseurs
        $tvaDeductible = DB::table('supplier_invoices')
            ->whereIn('status', ['validee', 'partiellement_payee', 'payee'])
            ->whereBetween('received_at', [$from, $to . ' 23:59:59'])
            ->selectRaw('SUM(subtotal_ht) as base_ht, SUM(total_tax) as montant_tva')
            ->first();

        $totalCollectee  = $tvaCollectee->sum('montant_tva');
        $totalDeductible = $tvaDeductible?->montant_tva ?? 0;
        $solde           = $totalCollectee - $totalDeductible;

        if ($request->input('export') === 'excel') {
            $collecteeRows = $tvaCollectee->map(fn($r) => [
                'TVA Collectée',
                $r->taux . '%',
                number_format($r->base_ht, 0, ',', ' '),
                number_format($r->montant_tva, 0, ',', ' '),
            ])->toArray();

            $collecteeRows[] = [
                'TVA Déductible',
                '—',
                number_format($tvaDeductible?->base_ht ?? 0, 0, ',', ' '),
                number_format($totalDeductible, 0, ',', ' '),
            ];

            return Excel::download(new GenericTableExport(
                sheetTitle:    'État de TVA',
                headers:       ['Type', 'Taux', 'Base HT', 'Montant TVA'],
                data:          $collecteeRows,
                from:          $from,
                to:            $to,
                numericColIdxs: [2, 3],
                colWidths:     ['A' => 20, 'B' => 10, 'C' => 18, 'D' => 18],
                totals:        ['SOLDE TVA À PAYER', '', '', number_format($solde, 0, ',', ' ')],
            ), 'etat-tva-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            $pdfRows = $tvaCollectee->map(fn($r) => [
                'TVA Collectée',
                $r->taux . '%',
                number_format($r->base_ht, 0, ',', ' '),
                number_format($r->montant_tva, 0, ',', ' '),
            ])->toArray();

            $pdfRows[] = [
                '<strong>TVA Déductible</strong>',
                '—',
                number_format($tvaDeductible?->base_ht ?? 0, 0, ',', ' '),
                number_format($totalDeductible, 0, ',', ' '),
            ];

            return $this->exportPdf('État de TVA', $from, $to,
                headers: [
                    ['label' => 'Type',        'align' => 'l'],
                    ['label' => 'Taux',        'align' => 'c'],
                    ['label' => 'Base HT',     'align' => 'r'],
                    ['label' => 'Montant TVA', 'align' => 'r'],
                ],
                rows: $pdfRows,
                kpis: [
                    'TVA Collectée'  => number_format($totalCollectee,  0, ',', ' ') . ' F',
                    'TVA Déductible' => number_format($totalDeductible, 0, ',', ' ') . ' F',
                    'Solde à payer'  => number_format($solde,           0, ',', ' ') . ' F',
                ],
                totalsRow: ['SOLDE TVA À PAYER', '', '', number_format($solde, 0, ',', ' ')],
                filename: 'etat-tva',
            );
        }

        return view('reports.etat-tva', compact(
            'from', 'to',
            'tvaCollectee', 'tvaDeductible',
            'totalCollectee', 'totalDeductible', 'solde'
        ));
    }

    // =========================================================================
    //  LISTE DES FACTURES
    // =========================================================================

    public function listeFactures(Request $request): mixed
    {
        $from     = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $clientId = $request->input('client_id');
        $status   = $request->input('status');

        $query = Invoice::whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->with('client:id,name')
            ->orderByDesc('issued_at')
            ->orderByDesc('number');

        if ($clientId) $query->where('client_id', $clientId);
        if ($status)   $query->where('status', $status);

        $rows = $query->get(['id', 'number', 'issued_at', 'due_at', 'client_id', 'status',
                             'subtotal_ht', 'total_ttc', 'paid_amount', 'remaining_amount']);

        $totals = [
            'ht'        => $rows->sum('subtotal_ht'),
            'ttc'       => $rows->sum('total_ttc'),
            'paid'      => $rows->sum('paid_amount'),
            'remaining' => $rows->sum('remaining_amount'),
        ];

        if ($request->input('export') === 'excel') {
            $data = $rows->map(fn($r) => [
                $r->number,
                $r->issued_at?->format('d/m/Y') ?? '',
                $r->due_at?->format('d/m/Y') ?? '',
                $r->client?->name ?? '—',
                $this->statusLabel($r->status),
                number_format($r->subtotal_ht, 0, ',', ' '),
                number_format($r->total_ttc, 0, ',', ' '),
                number_format($r->paid_amount, 0, ',', ' '),
                number_format($r->remaining_amount, 0, ',', ' '),
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle:    'Liste des factures',
                headers:       ['N° Facture', 'Date', 'Échéance', 'Client', 'Statut', 'HT', 'TTC', 'Réglé', 'Restant'],
                data:          $data,
                from:          $from,
                to:            $to,
                numericColIdxs: [5, 6, 7, 8],
                colWidths:     ['A' => 14, 'B' => 12, 'C' => 12, 'D' => 26, 'E' => 14, 'F' => 14, 'G' => 14, 'H' => 12, 'I' => 12],
                totals:        ['TOTAL', '', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' '), number_format($totals['paid'], 0, ',', ' '), number_format($totals['remaining'], 0, ',', ' ')],
            ), 'liste-factures-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf('Liste des factures', $from, $to,
                headers: [
                    ['label' => 'N° Facture', 'align' => 'l'],
                    ['label' => 'Date',       'align' => 'c'],
                    ['label' => 'Échéance',   'align' => 'c'],
                    ['label' => 'Client',     'align' => 'l'],
                    ['label' => 'Statut',     'align' => 'c'],
                    ['label' => 'HT',         'align' => 'r'],
                    ['label' => 'TTC',        'align' => 'r'],
                    ['label' => 'Réglé',      'align' => 'r'],
                    ['label' => 'Restant',    'align' => 'r'],
                ],
                rows: $rows->map(fn($r) => [
                    $r->number,
                    $r->issued_at?->format('d/m/Y') ?? '',
                    $r->due_at?->format('d/m/Y') ?? '',
                    $r->client?->name ?? '—',
                    '<span class="badge badge-' . $this->statusColor($r->status) . '">' . $this->statusLabel($r->status) . '</span>',
                    number_format($r->subtotal_ht, 0, ',', ' '),
                    number_format($r->total_ttc, 0, ',', ' '),
                    number_format($r->paid_amount, 0, ',', ' '),
                    number_format($r->remaining_amount, 0, ',', ' '),
                ])->toArray(),
                kpis: [
                    'Nb factures' => $rows->count(),
                    'Total HT'    => number_format($totals['ht'],        0, ',', ' ') . ' F',
                    'Total TTC'   => number_format($totals['ttc'],       0, ',', ' ') . ' F',
                    'Restant dû'  => number_format($totals['remaining'], 0, ',', ' ') . ' F',
                ],
                totalsRow: ['TOTAL', '', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' '), number_format($totals['paid'], 0, ',', ' '), number_format($totals['remaining'], 0, ',', ' ')],
                filename: 'liste-factures',
            );
        }

        $clients  = Client::orderBy('name')->get(['id', 'name']);
        $statuses = [
            'brouillon'           => 'Brouillon',
            'validee'             => 'Validée',
            'envoyee'             => 'Envoyée',
            'partiellement_payee' => 'Part. payée',
            'payee'               => 'Payée',
            'en_retard'           => 'En retard',
            'annulee'             => 'Annulée',
        ];

        return view('reports.liste-factures', compact(
            'from', 'to', 'clientId', 'status', 'rows', 'totals', 'clients', 'statuses'
        ));
    }

    // =========================================================================
    //  LISTE DES DEVIS
    // =========================================================================

    public function listeDevis(Request $request): mixed
    {
        $from     = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $clientId = $request->input('client_id');
        $status   = $request->input('status');

        $query = Quote::whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->with('client:id,name')
            ->orderByDesc('issued_at')
            ->orderByDesc('number');

        if ($clientId) $query->where('client_id', $clientId);
        if ($status)   $query->where('status', $status);

        $rows = $query->get(['id', 'number', 'issued_at', 'expires_at', 'client_id', 'status',
                             'subtotal_ht', 'total_ttc']);

        $totals = [
            'count' => $rows->count(),
            'ht'    => $rows->sum('subtotal_ht'),
            'ttc'   => $rows->sum('total_ttc'),
        ];

        if ($request->input('export') === 'excel') {
            $data = $rows->map(fn($r) => [
                $r->number,
                $r->issued_at?->format('d/m/Y') ?? '',
                $r->expires_at?->format('d/m/Y') ?? '',
                $r->client?->name ?? '—',
                $this->quoteStatusLabel($r->status),
                number_format($r->subtotal_ht, 0, ',', ' '),
                number_format($r->total_ttc, 0, ',', ' '),
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle:    'Liste des devis',
                headers:       ['N° Devis', 'Date', 'Validité', 'Client', 'Statut', 'HT', 'TTC'],
                data:          $data,
                from:          $from,
                to:            $to,
                numericColIdxs: [5, 6],
                colWidths:     ['A' => 14, 'B' => 12, 'C' => 12, 'D' => 28, 'E' => 14, 'F' => 14, 'G' => 14],
                totals:        ['TOTAL (' . $totals['count'] . ' devis)', '', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' ')],
            ), 'liste-devis-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf('Liste des devis', $from, $to,
                headers: [
                    ['label' => 'N° Devis',  'align' => 'l'],
                    ['label' => 'Date',      'align' => 'c'],
                    ['label' => 'Validité',  'align' => 'c'],
                    ['label' => 'Client',    'align' => 'l'],
                    ['label' => 'Statut',    'align' => 'c'],
                    ['label' => 'HT',        'align' => 'r'],
                    ['label' => 'TTC',       'align' => 'r'],
                ],
                rows: $rows->map(fn($r) => [
                    $r->number,
                    $r->issued_at?->format('d/m/Y') ?? '',
                    $r->expires_at?->format('d/m/Y') ?? '',
                    $r->client?->name ?? '—',
                    '<span class="badge badge-blue">' . $this->quoteStatusLabel($r->status) . '</span>',
                    number_format($r->subtotal_ht, 0, ',', ' '),
                    number_format($r->total_ttc, 0, ',', ' '),
                ])->toArray(),
                kpis: [
                    'Nb devis'   => $totals['count'],
                    'Total HT'   => number_format($totals['ht'],  0, ',', ' ') . ' F',
                    'Total TTC'  => number_format($totals['ttc'], 0, ',', ' ') . ' F',
                ],
                totalsRow: ['TOTAL', '', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' ')],
                filename: 'liste-devis',
            );
        }

        $clients  = Client::orderBy('name')->get(['id', 'name']);
        $statuses = [
            'brouillon' => 'Brouillon',
            'valide'    => 'Validé',
            'converti'  => 'Converti',
            'expire'    => 'Expiré',
            'refuse'    => 'Refusé',
            'annule'    => 'Annulé',
        ];

        return view('reports.liste-devis', compact(
            'from', 'to', 'clientId', 'status', 'rows', 'totals', 'clients', 'statuses'
        ));
    }

    // =========================================================================
    //  LISTE DES COMMANDES
    // =========================================================================

    public function listeCommandes(Request $request): mixed
    {
        $from     = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to       = $request->input('to',   now()->format('Y-m-d'));
        $clientId = $request->input('client_id');
        $status   = $request->input('status');

        $query = Order::whereBetween('issued_at', [$from, $to . ' 23:59:59'])
            ->with('client:id,name')
            ->orderByDesc('issued_at')
            ->orderByDesc('number');

        if ($clientId) $query->where('client_id', $clientId);
        if ($status)   $query->where('status', $status);

        $rows = $query->get(['id', 'number', 'issued_at', 'client_id', 'status',
                             'subtotal_ht', 'total_ttc']);

        $totals = [
            'count' => $rows->count(),
            'ht'    => $rows->sum('subtotal_ht'),
            'ttc'   => $rows->sum('total_ttc'),
        ];

        if ($request->input('export') === 'excel') {
            $data = $rows->map(fn($r) => [
                $r->number,
                $r->issued_at?->format('d/m/Y') ?? '',
                $r->client?->name ?? '—',
                $this->orderStatusLabel($r->status),
                number_format($r->subtotal_ht, 0, ',', ' '),
                number_format($r->total_ttc, 0, ',', ' '),
            ])->toArray();

            return Excel::download(new GenericTableExport(
                sheetTitle:    'Liste des commandes',
                headers:       ['N° Commande', 'Date', 'Client', 'Statut', 'HT', 'TTC'],
                data:          $data,
                from:          $from,
                to:            $to,
                numericColIdxs: [4, 5],
                colWidths:     ['A' => 16, 'B' => 12, 'C' => 30, 'D' => 14, 'E' => 14, 'F' => 14],
                totals:        ['TOTAL (' . $totals['count'] . ' cmd)', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' ')],
            ), 'liste-commandes-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->input('export') === 'pdf') {
            return $this->exportPdf('Liste des commandes', $from, $to,
                headers: [
                    ['label' => 'N° Commande', 'align' => 'l'],
                    ['label' => 'Date',        'align' => 'c'],
                    ['label' => 'Client',      'align' => 'l'],
                    ['label' => 'Statut',      'align' => 'c'],
                    ['label' => 'HT',          'align' => 'r'],
                    ['label' => 'TTC',         'align' => 'r'],
                ],
                rows: $rows->map(fn($r) => [
                    $r->number,
                    $r->issued_at?->format('d/m/Y') ?? '',
                    $r->client?->name ?? '—',
                    '<span class="badge badge-blue">' . $this->orderStatusLabel($r->status) . '</span>',
                    number_format($r->subtotal_ht, 0, ',', ' '),
                    number_format($r->total_ttc, 0, ',', ' '),
                ])->toArray(),
                kpis: [
                    'Nb commandes' => $totals['count'],
                    'Total HT'     => number_format($totals['ht'],  0, ',', ' ') . ' F',
                    'Total TTC'    => number_format($totals['ttc'], 0, ',', ' ') . ' F',
                ],
                totalsRow: ['TOTAL', '', '', '', number_format($totals['ht'], 0, ',', ' '), number_format($totals['ttc'], 0, ',', ' ')],
                filename: 'liste-commandes',
            );
        }

        $clients  = Client::orderBy('name')->get(['id', 'name']);
        $statuses = [
            'brouillon'  => 'Brouillon',
            'confirme'   => 'Confirmée',
            'en_cours'   => 'En cours',
            'livre'      => 'Livrée',
            'facture'    => 'Facturée',
            'annule'     => 'Annulée',
        ];

        return view('reports.liste-commandes', compact(
            'from', 'to', 'clientId', 'status', 'rows', 'totals', 'clients', 'statuses'
        ));
    }

    // =========================================================================
    //  HELPERS PRIVÉS
    // =========================================================================

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'brouillon'           => 'Brouillon',
            'validee'             => 'Validée',
            'envoyee'             => 'Envoyée',
            'partiellement_payee' => 'Part. payée',
            'payee'               => 'Payée',
            'en_retard'           => 'En retard',
            'annulee'             => 'Annulée',
            default               => ucfirst($status),
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'brouillon'           => 'gray',
            'validee'             => 'blue',
            'envoyee'             => 'blue',
            'partiellement_payee' => 'amber',
            'payee'               => 'green',
            'en_retard'           => 'red',
            'annulee'             => 'red',
            default               => 'gray',
        };
    }

    private function quoteStatusLabel(string $status): string
    {
        return match ($status) {
            'brouillon' => 'Brouillon',
            'valide'    => 'Validé',
            'converti'  => 'Converti',
            'expire'    => 'Expiré',
            'refuse'    => 'Refusé',
            'annule'    => 'Annulé',
            default     => ucfirst($status),
        };
    }

    private function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'brouillon' => 'Brouillon',
            'confirme'  => 'Confirmée',
            'en_cours'  => 'En cours',
            'livre'     => 'Livrée',
            'facture'   => 'Facturée',
            'annule'    => 'Annulée',
            default     => ucfirst($status),
        };
    }

    /**
     * Génère un PDF via le template générique et renvoie la réponse download.
     */
    private function exportPdf(
        string $title,
        ?string $from,
        ?string $to,
        array $headers,
        array $rows,
        array $kpis = [],
        ?array $totalsRow = null,
        ?string $subtitle = null,
        string $filename = 'rapport',
    ): mixed {
        $company = currentCompany();

        $pdf = Pdf::loadView('reports.pdf.generic-table', [
            'company'    => $company,
            'title'      => $title,
            'subtitle'   => $subtitle,
            'from'       => $from ? Carbon::parse($from)->format('d/m/Y') : null,
            'to'         => $to   ? Carbon::parse($to)->format('d/m/Y')   : null,
            'kpis'       => $kpis,
            'headers'    => $headers,
            'rows'       => $rows,
            'totalsRow'  => $totalsRow,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename . '_' . now()->format('Ymd_His') . '.pdf');
    }
}
