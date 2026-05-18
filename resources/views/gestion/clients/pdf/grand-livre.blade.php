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

/* Account block */
.account-header { background:#312E81; color:#fff; padding:5px 10px; margin-top:10px; margin-bottom:0; display:table; width:100%; }
.account-header-left { display:table-cell; font-size:9.5pt; font-weight:bold; }
.account-header-right { display:table-cell; text-align:right; font-size:8.5pt; }

/* Table */
table { width:100%; border-collapse:collapse; font-size:8pt; }
thead tr { background:#EEF2FF; }
thead th { padding:4px 5px; text-align:center; font-size:7pt; font-weight:bold; color:#3730A3; border-bottom:1px solid #818CF8; }
th.left, td.left { text-align:left; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
td { padding:3px 5px; }
.num { font-family: DejaVu Sans Mono, monospace; }
.solde-pos { color:#DC2626; font-weight:bold; }
.solde-neg { color:#16A34A; font-weight:bold; }
tfoot tr { background:#EEF2FF; }
tfoot td { padding:4px 5px; border-top:1px solid #818CF8; font-weight:bold; color:#312E81; font-size:8pt; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-top">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">GRAND LIVRE CLIENTS (comptes 411)</div>
        <div class="header-right">Édition du {{ now()->format('d/m/Y') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Écritures validées — comptes clients</div>
    <div class="subheader-right">Période : {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '—' }} → {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '—' }}</div>
</div>

@foreach($accounts as $account)
<div class="account-header">
    <div class="account-header-left">{{ $account['code'] }} — {{ $account['name'] }}</div>
    <div class="account-header-right">
        Solde ouv. : {{ number_format($account['solde_ouv'],0,',',' ') }} &nbsp;|&nbsp;
        Solde fin : {{ number_format($account['solde_fin'],0,',',' ') }}
    </div>
</div>
<table>
    <thead>
        <tr>
            <th class="left" style="width:10%">Date</th>
            <th class="left" style="width:13%">N° Écriture</th>
            <th style="width:6%">Jnl</th>
            <th class="left" style="width:13%">Référence</th>
            <th class="left" style="width:34%">Libellé</th>
            <th class="right" style="width:12%">Débit (FCFA)</th>
            <th class="right" style="width:12%">Crédit (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($account['lines'] as $item)
        @php $l = $item['line']; @endphp
        <tr>
            <td>{{ $l->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}</td>
            <td>{{ $l->journalEntry?->number ?? '—' }}</td>
            <td style="text-align:center">{{ $l->journalEntry?->journalType?->code ?? '—' }}</td>
            <td>{{ $l->journalEntry?->reference ?? '' }}</td>
            <td>{{ $l->label ?: ($l->journalEntry?->description ?? '') }}</td>
            <td class="right num">{{ (int)$l->debit  > 0 ? number_format((int)$l->debit,0,',',' ')  : '—' }}</td>
            <td class="right num">{{ (int)$l->credit > 0 ? number_format((int)$l->credit,0,',',' ') : '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Total {{ $account['code'] }}</td>
            <td class="right num">{{ $account['total_d'] > 0 ? number_format($account['total_d'],0,',',' ') : '—' }}</td>
            <td class="right num">{{ $account['total_c'] > 0 ? number_format($account['total_c'],0,',',' ') : '—' }}</td>
        </tr>
    </tfoot>
</table>
@endforeach

</div>
</body>
</html>
