<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
.page { padding: 14mm 12mm; }

/* Header */
.header { background:#1E3A5F; color:#fff; padding:8px 12px; margin-bottom:3px; }
.header-top { display:table; width:100%; }
.header-left { display:table-cell; font-size:11pt; font-weight:bold; }
.header-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; }
.header-right { display:table-cell; text-align:right; font-size:9pt; }
.subheader { background:#2D5986; color:#e5e7eb; padding:4px 12px; font-size:8pt; font-style:italic; display:table; width:100%; margin-bottom:10px; }
.subheader-left { display:table-cell; }
.subheader-right { display:table-cell; text-align:right; }

/* KPI boxes */
.kpi-row { display:table; width:100%; margin-bottom:10px; border-collapse:separate; border-spacing:4px; }
.kpi-cell { display:table-cell; width:16%; }
.kpi-box { border:1px solid #d1d5db; border-radius:4px; padding:5px 8px; text-align:center; }
.kpi-label { font-size:6.5pt; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
.kpi-value { font-size:10pt; font-weight:bold; margin-top:2px; }
.kpi-total { border-color:#6366F1; background:#EEF2FF; }
.kpi-total .kpi-value { color:#312E81; }
.kpi-non-echu { border-color:#6366F1; background:#DBEAFE; }
.kpi-non-echu .kpi-value { color:#1D4ED8; }
.kpi-j1-30 { border-color:#EAB308; background:#FEF9C3; }
.kpi-j1-30 .kpi-value { color:#713F12; }
.kpi-j31-60 { border-color:#F97316; background:#FFEDD5; }
.kpi-j31-60 .kpi-value { color:#7C2D12; }
.kpi-j61-90 { border-color:#EF4444; background:#FEE2E2; }
.kpi-j61-90 .kpi-value { color:#7F1D1D; }
.kpi-j90p { border-color:#B91C1C; background:#FECACA; }
.kpi-j90p .kpi-value { color:#7F1D1D; }

/* Table */
table { width:100%; border-collapse:collapse; font-size:8.5pt; }
thead tr { background:#E0E7FF; }
thead th { padding:5px 6px; text-align:center; font-size:7.5pt; font-weight:bold; color:#1E3A5F; border-top:1.5px solid #6366F1; border-bottom:1.5px solid #6366F1; }
th.left, td.left { text-align:left; }
th.right, td.right { text-align:right; }
th.bg-blue { background:#DBEAFE; }
th.bg-yellow { background:#FEF9C3; }
th.bg-orange { background:#FFEDD5; }
th.bg-red { background:#FEE2E2; }
th.bg-darkred { background:#FECACA; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#f9fafb; }
td { padding:4px 6px; }
.num { font-family: DejaVu Sans Mono, monospace; }
tfoot tr { background:#1E3A5F; color:#fff; font-weight:bold; }
tfoot td { padding:5px 6px; border-top:2px solid #6366F1; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-top">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">BALANCE ÂGÉE CLIENTS</div>
        <div class="header-right">Au {{ $today->format('d/m/Y') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Créances en cours ventilées par ancienneté</div>
    <div class="subheader-right">Édition du {{ now()->format('d/m/Y') }}</div>
</div>

<div class="kpi-row">
    <div class="kpi-cell">
        <div class="kpi-box kpi-total">
            <div class="kpi-label">Total dû</div>
            <div class="kpi-value">{{ number_format($totals['total'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box kpi-non-echu">
            <div class="kpi-label">Non échu</div>
            <div class="kpi-value">{{ number_format($totals['non_echu'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box kpi-j1-30">
            <div class="kpi-label">1 – 30 j</div>
            <div class="kpi-value">{{ number_format($totals['j1_30'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box kpi-j31-60">
            <div class="kpi-label">31 – 60 j</div>
            <div class="kpi-value">{{ number_format($totals['j31_60'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box kpi-j61-90">
            <div class="kpi-label">61 – 90 j</div>
            <div class="kpi-value">{{ number_format($totals['j61_90'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box kpi-j90p">
            <div class="kpi-label">+ 90 j</div>
            <div class="kpi-value">{{ number_format($totals['j90p'],0,',',' ') }}</div>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left" style="width:8%">Code</th>
            <th class="left" style="width:28%">Client</th>
            <th class="right" style="width:14%">Total dû (FCFA)</th>
            <th class="right bg-blue" style="width:12%">Non échu</th>
            <th class="right bg-yellow" style="width:9%">1 – 30 j</th>
            <th class="right bg-orange" style="width:9%">31 – 60 j</th>
            <th class="right bg-red" style="width:9%">61 – 90 j</th>
            <th class="right bg-darkred" style="width:9%">+ 90 j</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td>{{ $row['code'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td class="right num">{{ $row['total']    > 0 ? number_format($row['total'],0,',',' ')    : '—' }}</td>
            <td class="right num">{{ $row['non_echu'] > 0 ? number_format($row['non_echu'],0,',',' ') : '—' }}</td>
            <td class="right num">{{ $row['j1_30']    > 0 ? number_format($row['j1_30'],0,',',' ')    : '—' }}</td>
            <td class="right num">{{ $row['j31_60']   > 0 ? number_format($row['j31_60'],0,',',' ')   : '—' }}</td>
            <td class="right num">{{ $row['j61_90']   > 0 ? number_format($row['j61_90'],0,',',' ')   : '—' }}</td>
            <td class="right num">{{ $row['j90p']     > 0 ? number_format($row['j90p'],0,',',' ')     : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td class="right num">{{ number_format($totals['total'],0,',',' ') }}</td>
            <td class="right num">{{ $totals['non_echu'] > 0 ? number_format($totals['non_echu'],0,',',' ') : '—' }}</td>
            <td class="right num">{{ $totals['j1_30']    > 0 ? number_format($totals['j1_30'],0,',',' ')    : '—' }}</td>
            <td class="right num">{{ $totals['j31_60']   > 0 ? number_format($totals['j31_60'],0,',',' ')   : '—' }}</td>
            <td class="right num">{{ $totals['j61_90']   > 0 ? number_format($totals['j61_90'],0,',',' ')   : '—' }}</td>
            <td class="right num">{{ $totals['j90p']     > 0 ? number_format($totals['j90p'],0,',',' ')     : '—' }}</td>
        </tr>
    </tfoot>
</table>

</div>
</body>
</html>
