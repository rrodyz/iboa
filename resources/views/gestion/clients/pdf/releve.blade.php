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

/* Info box */
.info-box { background:#f3f4f6; border:1px solid #d1d5db; padding:6px 10px; margin-bottom:10px; display:table; width:100%; }
.info-cell { display:table-cell; padding-right:20px; }
.info-label { font-size:7pt; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; }
.info-value { font-size:9pt; font-weight:bold; color:#111827; }

/* Table */
table { width:100%; border-collapse:collapse; font-size:8.5pt; }
thead tr { background:#E0E7FF; }
thead th { padding:5px 6px; text-align:center; font-size:7.5pt; font-weight:bold; color:#1E3A5F; border-top:1.5px solid #6366F1; border-bottom:1.5px solid #6366F1; }
th.left, td.left { text-align:left; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr.ouv { background:#DBEAFE; font-weight:bold; color:#1D4ED8; }
tbody tr.facture { background:#fff; }
tbody tr.avoir { background:#fff9f5; }
tbody tr.reglement { background:#f0fdf4; }
td { padding:4px 6px; }
.badge { display:inline-block; padding:1px 6px; border-radius:10px; font-size:7.5pt; font-weight:bold; }
.badge-facture { background:#DBEAFE; color:#1D4ED8; }
.badge-avoir   { background:#FFEDD5; color:#C2410C; }
.badge-reglement { background:#DCFCE7; color:#15803D; }
tfoot tr { background:#1E3A5F; color:#fff; font-weight:bold; }
tfoot td { padding:5px 6px; border-top:2px solid #6366F1; }
.solde-pos { color:#DC2626; font-weight:bold; }
.solde-neg { color:#16A34A; font-weight:bold; }
.num { font-family: DejaVu Sans Mono, monospace; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-top">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">RELEVÉ DE COMPTE CLIENT</div>
        <div class="header-right">Au {{ now()->format('d/m/Y') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Client : <strong>{{ $client->name }}</strong>@if($client->code) ({{ $client->code }})@endif</div>
    <div class="subheader-right">Période : {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
</div>

<div class="info-box">
    <div class="info-cell">
        <div class="info-label">Client</div>
        <div class="info-value">{{ $client->name }}</div>
    </div>
    <div class="info-cell">
        <div class="info-label">Solde d'ouverture</div>
        <div class="info-value {{ $soldeOuv >= 0 ? 'solde-pos' : 'solde-neg' }}">{{ number_format(abs($soldeOuv),0,',',' ') }} FCFA</div>
    </div>
    <div class="info-cell">
        <div class="info-label">Solde de clôture</div>
        @if($lines->count())
        <div class="info-value {{ $lines->last()['solde'] >= 0 ? 'solde-pos' : 'solde-neg' }}">{{ number_format(abs($lines->last()['solde']),0,',',' ') }} FCFA</div>
        @else
        <div class="info-value">—</div>
        @endif
    </div>
    <div class="info-cell">
        <div class="info-label">Transactions</div>
        <div class="info-value">{{ $lines->count() }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left">Date</th>
            <th class="left">Référence</th>
            <th>Type</th>
            <th>Échéance</th>
            <th class="right">Débit (FCFA)</th>
            <th class="right">Crédit (FCFA)</th>
            <th class="right">Solde (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="ouv">
            <td>{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</td>
            <td colspan="3">SOLDE D'OUVERTURE</td>
            <td class="right num">{{ $soldeOuv > 0 ? number_format($soldeOuv,0,',',' ') : '—' }}</td>
            <td class="right num">{{ $soldeOuv < 0 ? number_format(abs($soldeOuv),0,',',' ') : '—' }}</td>
            <td class="right num">{{ number_format($soldeOuv,0,',',' ') }}</td>
        </tr>
        @foreach($lines as $line)
        <tr class="{{ $line['type'] }}">
            <td>{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</td>
            <td><strong>{{ $line['reference'] }}</strong></td>
            <td style="text-align:center">
                <span class="badge badge-{{ $line['type'] }}">
                    {{ $line['type'] === 'facture' ? 'Facture' : ($line['type'] === 'avoir' ? 'Avoir' : 'Règlement') }}
                </span>
            </td>
            <td style="text-align:center">{{ $line['echeance'] ? \Carbon\Carbon::parse($line['echeance'])->format('d/m/Y') : '—' }}</td>
            <td class="right num">{{ $line['debit'] > 0 ? number_format($line['debit'],0,',',' ') : '—' }}</td>
            <td class="right num">{{ $line['credit'] > 0 ? number_format($line['credit'],0,',',' ') : '—' }}</td>
            <td class="right num {{ $line['solde'] >= 0 ? 'solde-pos' : 'solde-neg' }}">{{ number_format($line['solde'],0,',',' ') }}</td>
        </tr>
        @endforeach
    </tbody>
    @if($lines->count())
    <tfoot>
        <tr>
            <td colspan="4">TOTAUX PÉRIODE</td>
            <td class="right num">{{ number_format($lines->sum('debit'),0,',',' ') }}</td>
            <td class="right num">{{ number_format($lines->sum('credit'),0,',',' ') }}</td>
            <td class="right num">{{ number_format($lines->last()['solde'],0,',',' ') }}</td>
        </tr>
    </tfoot>
    @endif
</table>

</div>
</body>
</html>
