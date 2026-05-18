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
.kpi-cell { display:table-cell; width:25%; padding-right:6px; }
.kpi-box  { border:1px solid #d1d5db; border-radius:4px; padding:5px 8px; text-align:center; }
.kpi-label { font-size:6.5pt; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
.kpi-value { font-size:10pt; font-weight:bold; margin-top:2px; color:#7C2D12; }

table { width:100%; border-collapse:collapse; font-size:8.5pt; }
thead tr { background:#FEF3C7; }
thead th { padding:5px 6px; font-size:7.5pt; font-weight:bold; color:#7C2D12; border-top:1.5px solid #F97316; border-bottom:1.5px solid #F97316; }
th.left, td.left { text-align:left; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#fafafa; }
td { padding:4px 6px; }
.num { font-family: DejaVu Sans Mono, monospace; }
.credit { color:#15803D; }
.debit  { color:#B91C1C; }
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
        <div class="header-center">BALANCE FOURNISSEURS</div>
        <div class="header-right">Au {{ now()->format('d/m/Y') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Solde comptable par fournisseur</div>
    <div class="subheader-right">Édition du {{ now()->format('d/m/Y') }}</div>
</div>

<div class="kpi-row">
    <div class="kpi-cell">
        <div class="kpi-box">
            <div class="kpi-label">Total facturé</div>
            <div class="kpi-value">{{ number_format($totals['total_fact'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box">
            <div class="kpi-label">Retours</div>
            <div class="kpi-value" style="color:#C2410C;">{{ number_format($totals['total_retour'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box">
            <div class="kpi-label">Total payé</div>
            <div class="kpi-value" style="color:#15803D;">{{ number_format($totals['total_paye'],0,',',' ') }}</div>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-box" style="border-color:#F97316;background:#FEF3C7;">
            <div class="kpi-label">Solde dû</div>
            <div class="kpi-value" style="color:#B91C1C;">{{ number_format($totals['solde'],0,',',' ') }}</div>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left" style="width:8%">Code</th>
            <th class="left" style="width:32%">Fournisseur</th>
            <th class="right" style="width:17%">Total facturé</th>
            <th class="right" style="width:14%">Retours</th>
            <th class="right" style="width:14%">Total payé</th>
            <th class="right" style="width:14%">Solde dû</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td style="font-size:7.5pt;color:#6b7280;">{{ $row['code'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td class="right num">{{ number_format($row['total_fact'],  0,',',' ') }}</td>
            <td class="right num credit">{{ $row['total_retour'] > 0 ? number_format($row['total_retour'],0,',',' ') : '—' }}</td>
            <td class="right num credit">{{ number_format($row['total_paye'],  0,',',' ') }}</td>
            <td class="right num debit">{{ number_format($row['solde'], 0,',',' ') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL</td>
            <td class="right num">{{ number_format($totals['total_fact'],  0,',',' ') }}</td>
            <td class="right num">{{ number_format($totals['total_retour'],0,',',' ') }}</td>
            <td class="right num">{{ number_format($totals['total_paye'],  0,',',' ') }}</td>
            <td class="right num">{{ number_format($totals['solde'],       0,',',' ') }}</td>
        </tr>
    </tfoot>
</table>

</div>
</body>
</html>
