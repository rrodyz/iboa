<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
.page { padding: 12mm 10mm; }

.header { background:#7C2D12; color:#fff; padding:8px 12px; margin-bottom:3px; }
.header-top { display:table; width:100%; }
.header-left   { display:table-cell; font-size:11pt; font-weight:bold; width:30%; }
.header-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; width:40%; }
.header-right  { display:table-cell; text-align:right; font-size:9pt; width:30%; }
.subheader { background:#B45309; color:#e5e7eb; padding:4px 12px; font-size:8pt; font-style:italic; display:table; width:100%; margin-bottom:12px; }
.subheader-left  { display:table-cell; }
.subheader-right { display:table-cell; text-align:right; color:#FDE68A; }

.account-block { margin-bottom:14px; page-break-inside:avoid; }
.account-header { background:#92400E; color:#fff; padding:6px 10px; display:table; width:100%; }
.acc-left  { display:table-cell; font-weight:bold; font-size:9pt; }
.acc-right { display:table-cell; text-align:right; font-size:8pt; color:#FDE68A; }

table { width:100%; border-collapse:collapse; font-size:8pt; }
thead tr { background:#FEF3C7; }
thead th { padding:4px 5px; font-size:7pt; font-weight:bold; color:#7C2D12; border-bottom:1px solid #F97316; }
th.left, td.left { text-align:left; }
th.center, td.center { text-align:center; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#fafafa; }
td { padding:3px 5px; }
.num { font-family: DejaVu Sans Mono, monospace; }
.badge { background:#FEF3C7; color:#92400E; padding:1px 3px; border-radius:2px; font-size:6.5pt; border:0.5px solid #F59E0B; }
tfoot tr { background:#FEF9C3; }
tfoot td { padding:4px 5px; font-weight:bold; font-size:7.5pt; color:#7C2D12; border-top:1px solid #F97316; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-top">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">GRAND LIVRE FOURNISSEURS (comptes 401)</div>
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
    <div class="subheader-left">Écritures validées — comptes fournisseurs</div>
    <div class="subheader-right">Édition du {{ now()->format('d/m/Y') }}</div>
</div>

@foreach($accounts as $account)
<div class="account-block">
    <div class="account-header">
        <div class="acc-left">{{ $account['code'] }} — {{ $account['name'] }}</div>
        <div class="acc-right">
            Solde ouv. : {{ number_format(abs($account['solde_ouv']),0,',',' ') }} FCFA
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Solde fin : {{ number_format(abs($account['solde_fin']),0,',',' ') }} FCFA
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th class="left"   style="width:9%">Date</th>
                <th class="left"   style="width:13%">N° Écriture</th>
                <th class="center" style="width:6%">Jnl</th>
                <th class="left"   style="width:12%">Référence</th>
                <th class="left"   style="width:32%">Libellé</th>
                <th class="right"  style="width:9%">Débit</th>
                <th class="right"  style="width:9%">Crédit</th>
                <th class="right"  style="width:9%">Solde</th>
            </tr>
        </thead>
        <tbody>
            @foreach($account['lines'] as $item)
            @php $l = $item['line']; @endphp
            <tr>
                <td>{{ $l->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}</td>
                <td style="font-family:DejaVu Sans Mono,monospace;font-size:7pt;">{{ $l->journalEntry?->number ?? '—' }}</td>
                <td class="center"><span class="badge">{{ $l->journalEntry?->journalType?->code ?? '—' }}</span></td>
                <td style="font-size:7pt;color:#6b7280;">{{ $l->journalEntry?->reference ?? '' }}</td>
                <td>{{ $l->label ?: ($l->journalEntry?->description ?? '') }}</td>
                <td class="right num">{{ (int)$l->debit  > 0 ? number_format((int)$l->debit,  0,',',' ') : '—' }}</td>
                <td class="right num">{{ (int)$l->credit > 0 ? number_format((int)$l->credit, 0,',',' ') : '—' }}</td>
                <td class="right num">{{ number_format(abs($item['solde']),0,',',' ') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">Total {{ $account['code'] }}</td>
                <td class="right num">{{ number_format($account['total_d'],   0,',',' ') }}</td>
                <td class="right num">{{ number_format($account['total_c'],   0,',',' ') }}</td>
                <td class="right num">{{ number_format(abs($account['solde_fin']),0,',',' ') }}</td>
            </tr>
        </tfoot>
    </table>
</div>
@endforeach

</div>
</body>
</html>
