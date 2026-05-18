<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
.page { padding: 14mm 12mm; }

.header { background:#7C2D12; color:#fff; padding:8px 12px; margin-bottom:3px; }
.header-top { display:table; width:100%; }
.header-left   { display:table-cell; font-size:11pt; font-weight:bold; width:30%; }
.header-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; width:40%; }
.header-right  { display:table-cell; text-align:right; font-size:9pt; width:30%; }
.subheader { background:#B45309; color:#e5e7eb; padding:4px 12px; font-size:8pt; font-style:italic; display:table; width:100%; margin-bottom:12px; }
.subheader-left  { display:table-cell; }
.subheader-right { display:table-cell; text-align:right; color:#FDE68A; }

.kpi-row  { display:table; width:100%; margin-bottom:10px; }
.kpi-cell { display:table-cell; width:33%; padding-right:6px; }
.kpi-box  { border:1px solid #d1d5db; border-radius:4px; padding:5px 8px; text-align:center; }
.kpi-label { font-size:6.5pt; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
.kpi-value { font-size:10pt; font-weight:bold; margin-top:2px; color:#7C2D12; }

table { width:100%; border-collapse:collapse; font-size:8.5pt; }
thead tr { background:#FEF3C7; }
thead th { padding:5px 6px; font-size:7.5pt; font-weight:bold; color:#7C2D12; border-top:1.5px solid #F97316; border-bottom:1.5px solid #F97316; }
th.left, td.left { text-align:left; }
th.center, td.center { text-align:center; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#fafafa; }
tbody tr.overdue { background:#FEF2F2; }
td { padding:4px 6px; }
.num { font-family: DejaVu Sans Mono, monospace; }
.badge-retard { background:#FEE2E2; color:#DC2626; padding:1px 5px; border-radius:2px; font-size:7pt; font-weight:bold; }
tfoot tr { background:#7C2D12; color:#fff; font-weight:bold; }
tfoot td { padding:5px 6px; border-top:2px solid #F97316; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-top">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">FACTURES FOURNISSEURS IMPAYÉES</div>
        <div class="header-right">Au {{ $today->format('d/m/Y') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Factures avec solde restant dû</div>
    <div class="subheader-right">Édition du {{ now()->format('d/m/Y') }}</div>
</div>

<div class="kpi-row">
    <div class="kpi-cell">
        <div class="kpi-box">
            <div class="kpi-label">Factures impayées</div>
            <div class="kpi-value">{{ $invoices->count() }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box" style="border-color:#EF4444;background:#FEF2F2;">
            <div class="kpi-label">En retard</div>
            <div class="kpi-value" style="color:#B91C1C;">{{ $invoices->filter(fn($i) => $i->due_at && $i->due_at < $today)->count() }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box" style="border-color:#F97316;background:#FEF3C7;">
            <div class="kpi-label">Restant dû total (FCFA)</div>
            <div class="kpi-value">{{ number_format($totalDue,0,',',' ') }}</div>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left"   style="width:13%">N° Facture</th>
            <th class="left"   style="width:24%">Fournisseur</th>
            <th class="left"   style="width:14%">Réf. Fourn.</th>
            <th class="center" style="width:10%">Date</th>
            <th class="center" style="width:10%">Échéance</th>
            <th class="right"  style="width:13%">Total TTC</th>
            <th class="right"  style="width:13%">Restant dû</th>
            <th class="center" style="width:7%">Retard</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoices as $inv)
        @php
            $overdue = $inv->due_at && $inv->due_at < $today;
            $days    = $inv->due_at ? (int)($today->diffInDays($inv->due_at, false) * -1) : 0;
        @endphp
        <tr class="{{ $overdue ? 'overdue' : '' }}">
            <td style="font-size:7.5pt;font-family:DejaVu Sans Mono,monospace;">{{ $inv->number }}</td>
            <td>{{ $inv->supplier?->name ?? '—' }}</td>
            <td style="font-size:7.5pt;color:#6b7280;">{{ $inv->supplier_invoice_number ?? '—' }}</td>
            <td class="center">{{ $inv->received_at?->format('d/m/Y') ?? '—' }}</td>
            <td class="center" style="{{ $overdue ? 'color:#DC2626;font-weight:bold;' : '' }}">{{ $inv->due_at?->format('d/m/Y') ?? '—' }}</td>
            <td class="right num">{{ number_format($inv->total_ttc,        0,',',' ') }}</td>
            <td class="right num" style="color:#DC2626;font-weight:bold;">{{ number_format($inv->remaining_amount,0,',',' ') }}</td>
            <td class="center">
                @if($overdue)
                    <span class="badge-retard">{{ $days }} j</span>
                @else
                    —
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTAL</td>
            <td class="right num">{{ number_format($invoices->sum('total_ttc'),0,',',' ') }}</td>
            <td class="right num">{{ number_format($totalDue,0,',',' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

</div>
</body>
</html>
