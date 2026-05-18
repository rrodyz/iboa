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

table { width:100%; border-collapse:collapse; font-size:8.5pt; }
thead tr { background:#FEF3C7; }
thead th { padding:5px 6px; font-size:7.5pt; font-weight:bold; color:#7C2D12; border-top:1.5px solid #F97316; border-bottom:1.5px solid #F97316; }
th.left, td.left { text-align:left; }
th.center, td.center { text-align:center; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#fafafa; }
td { padding:4px 6px; }
.num { font-family: DejaVu Sans Mono, monospace; }
.badge-jnl { background:#FEF3C7; color:#92400E; padding:1px 4px; border-radius:2px; font-size:7pt; border:0.5px solid #F59E0B; }
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
        <div class="header-center">JOURNAL DES ACHATS</div>
        <div class="header-right">
            @if($dateFrom && $dateTo)
                Du {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
            @else
                Toutes périodes
            @endif
        </div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Écritures validées — journal Achat</div>
    <div class="subheader-right">Édition du {{ now()->format('d/m/Y') }}</div>
</div>

<table>
    <thead>
        <tr>
            <th class="left"   style="width:10%">Date</th>
            <th class="left"   style="width:14%">N° Écriture</th>
            <th class="center" style="width:7%">Jnl</th>
            <th class="left"   style="width:16%">Référence</th>
            <th class="left"   style="width:35%">Libellé</th>
            <th class="right"  style="width:9%">Débit (FCFA)</th>
            <th class="right"  style="width:9%">Crédit (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($entries as $entry)
        <tr>
            <td>{{ $entry->entry_date?->format('d/m/Y') }}</td>
            <td style="font-family:DejaVu Sans Mono,monospace;font-size:7.5pt;">{{ $entry->number }}</td>
            <td class="center"><span class="badge-jnl">{{ $entry->journalType?->code ?? '—' }}</span></td>
            <td style="font-size:7.5pt;color:#6b7280;">{{ $entry->reference ?? '—' }}</td>
            <td>{{ $entry->description ?? '—' }}</td>
            <td class="right num">{{ $entry->total_debit  > 0 ? number_format($entry->total_debit,  0,',',' ') : '—' }}</td>
            <td class="right num">{{ $entry->total_credit > 0 ? number_format($entry->total_credit, 0,',',' ') : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">TOTAUX</td>
            <td class="right num">{{ number_format($totalDebit,  0,',',' ') }}</td>
            <td class="right num">{{ number_format($totalCredit, 0,',',' ') }}</td>
        </tr>
    </tfoot>
</table>

</div>
</body>
</html>
