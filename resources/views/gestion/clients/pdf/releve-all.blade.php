<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
.page { padding: 10mm 12mm; }

/* Header global */
.global-header { background:#1E3A5F; color:#fff; padding:8px 12px; margin-bottom:14px; display:table; width:100%; }
.gh-left  { display:table-cell; font-size:12pt; font-weight:bold; }
.gh-center{ display:table-cell; text-align:center; font-size:11pt; font-weight:bold; }
.gh-right { display:table-cell; text-align:right; font-size:8pt; vertical-align:bottom; }

/* Séparateur de page client */
.client-block { page-break-inside: avoid; margin-bottom: 18px; }
.client-block + .client-block { page-break-before: always; }

/* En-tête client */
.client-header { background:#2D5986; color:#e5e7eb; padding:5px 10px; margin-bottom:4px; display:table; width:100%; }
.ch-left  { display:table-cell; font-weight:bold; font-size:10pt; }
.ch-right { display:table-cell; text-align:right; font-size:8pt; vertical-align:bottom; }

/* Info box */
.info-box { background:#f3f4f6; border:1px solid #d1d5db; padding:5px 8px; margin-bottom:6px; display:table; width:100%; }
.info-cell { display:table-cell; padding-right:16px; }
.info-label { font-size:7pt; color:#6b7280; text-transform:uppercase; }
.info-value { font-size:9pt; font-weight:bold; }

/* Table */
table { width:100%; border-collapse:collapse; font-size:8pt; }
thead tr { background:#E0E7FF; }
thead th { padding:4px 5px; font-size:7.5pt; font-weight:bold; color:#1E3A5F; border-top:1.5px solid #6366F1; border-bottom:1.5px solid #6366F1; }
th.left, td.left { text-align:left; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr.ouv { background:#DBEAFE; font-weight:bold; color:#1D4ED8; }
tbody tr.facture { background:#fff; }
tbody tr.avoir { background:#fff9f5; }
tbody tr.reglement { background:#f0fdf4; }
td { padding:3px 5px; }
.badge { display:inline-block; padding:1px 5px; border-radius:8px; font-size:7pt; font-weight:bold; }
.badge-facture   { background:#DBEAFE; color:#1D4ED8; }
.badge-avoir     { background:#FFEDD5; color:#C2410C; }
.badge-reglement { background:#DCFCE7; color:#15803D; }
tfoot tr { background:#1E3A5F; color:#fff; font-weight:bold; }
tfoot td { padding:4px 5px; border-top:2px solid #6366F1; }
.solde-pos { color:#DC2626; font-weight:bold; }
.solde-neg { color:#16A34A; font-weight:bold; }
.num { font-family: DejaVu Sans Mono, monospace; }
.no-activity { color:#9ca3af; font-style:italic; font-size:8pt; padding:6px 8px; }

/* Résumé final */
.summary-table { width:100%; border-collapse:collapse; margin-top:18px; font-size:8.5pt; }
.summary-table th { background:#374151; color:#fff; padding:5px 8px; text-align:left; font-size:7.5pt; }
.summary-table td { padding:4px 8px; border-bottom:0.5px solid #e5e7eb; }
.summary-table tr:nth-child(even) td { background:#f9fafb; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

{{-- En-tête global --}}
<div class="global-header">
    <div class="gh-left">{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</div>
    <div class="gh-center">RELEVÉS DE COMPTE — TOUS LES CLIENTS</div>
    <div class="gh-right">
        Période : {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}<br>
        Imprimé le {{ now()->format('d/m/Y à H:i') }}
    </div>
</div>

{{-- Un bloc par client --}}
@foreach($allClientsData as $data)
@php
    $c         = $data['client'];
    $cLines    = $data['lines'];
    $cSoldeOuv = $data['soldeOuv'];
    $soldeFin  = $cLines->count() ? $cLines->last()['solde'] : $cSoldeOuv;
@endphp
<div class="client-block">

    <div class="client-header">
        <div class="ch-left">{{ $c->name }}@if($c->code) — {{ $c->code }}@endif</div>
        <div class="ch-right">
            Solde ouv. : {{ number_format(abs($cSoldeOuv),0,',',' ') }} FCFA &nbsp;|&nbsp;
            Solde fin. : {{ number_format(abs($soldeFin),0,',',' ') }} FCFA
        </div>
    </div>

    <div class="info-box">
        <div class="info-cell">
            <div class="info-label">Solde d'ouverture</div>
            <div class="info-value {{ $cSoldeOuv >= 0 ? 'solde-pos' : 'solde-neg' }}">{{ number_format(abs($cSoldeOuv),0,',',' ') }} FCFA</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Transactions</div>
            <div class="info-value">{{ $cLines->count() }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Total débit</div>
            <div class="info-value">{{ number_format($cLines->sum('debit'),0,',',' ') }} FCFA</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Total crédit</div>
            <div class="info-value">{{ number_format($cLines->sum('credit'),0,',',' ') }} FCFA</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Solde de clôture</div>
            <div class="info-value {{ $soldeFin >= 0 ? 'solde-pos' : 'solde-neg' }}">{{ number_format(abs($soldeFin),0,',',' ') }} FCFA</div>
        </div>
    </div>

    @if($cLines->count() || $cSoldeOuv != 0)
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
                <td class="right num">{{ $cSoldeOuv > 0 ? number_format($cSoldeOuv,0,',',' ') : '—' }}</td>
                <td class="right num">{{ $cSoldeOuv < 0 ? number_format(abs($cSoldeOuv),0,',',' ') : '—' }}</td>
                <td class="right num">{{ number_format($cSoldeOuv,0,',',' ') }}</td>
            </tr>
            @foreach($cLines as $line)
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
        @if($cLines->count())
        <tfoot>
            <tr>
                <td colspan="4">TOTAUX PÉRIODE</td>
                <td class="right num">{{ number_format($cLines->sum('debit'),0,',',' ') }}</td>
                <td class="right num">{{ number_format($cLines->sum('credit'),0,',',' ') }}</td>
                <td class="right num">{{ number_format($soldeFin,0,',',' ') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
    @else
    <p class="no-activity">Aucune activité sur cette période.</p>
    @endif

</div>
@endforeach

{{-- Tableau récapitulatif final --}}
<div style="page-break-before: always;">
<div class="global-header">
    <div class="gh-left">RÉCAPITULATIF — TOUS LES CLIENTS</div>
    <div class="gh-right">Période : {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</div>
</div>
<table class="summary-table">
    <thead>
        <tr>
            <th>Client</th>
            <th>Code</th>
            <th style="text-align:right">Solde ouv. (FCFA)</th>
            <th style="text-align:right">Débit (FCFA)</th>
            <th style="text-align:right">Crédit (FCFA)</th>
            <th style="text-align:right">Solde fin. (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($allClientsData as $data)
        @php
            $sf = $data['lines']->count() ? $data['lines']->last()['solde'] : $data['soldeOuv'];
        @endphp
        <tr>
            <td>{{ $data['client']->name }}</td>
            <td>{{ $data['client']->code }}</td>
            <td style="text-align:right" class="num {{ $data['soldeOuv'] >= 0 ? 'solde-pos' : 'solde-neg' }}">{{ number_format($data['soldeOuv'],0,',',' ') }}</td>
            <td style="text-align:right" class="num">{{ number_format($data['lines']->sum('debit'),0,',',' ') }}</td>
            <td style="text-align:right" class="num">{{ number_format($data['lines']->sum('credit'),0,',',' ') }}</td>
            <td style="text-align:right" class="num {{ $sf >= 0 ? 'solde-pos' : 'solde-neg' }}"><strong>{{ number_format($sf,0,',',' ') }}</strong></td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2">TOTAL GÉNÉRAL</td>
            <td style="text-align:right" class="num">{{ number_format(collect($allClientsData)->sum('soldeOuv'),0,',',' ') }}</td>
            <td style="text-align:right" class="num">{{ number_format(collect($allClientsData)->sum(fn($d) => $d['lines']->sum('debit')),0,',',' ') }}</td>
            <td style="text-align:right" class="num">{{ number_format(collect($allClientsData)->sum(fn($d) => $d['lines']->sum('credit')),0,',',' ') }}</td>
            <td style="text-align:right" class="num">{{ number_format(collect($allClientsData)->sum(fn($d) => $d['lines']->count() ? $d['lines']->last()['solde'] : $d['soldeOuv']),0,',',' ') }}</td>
        </tr>
    </tfoot>
</table>
</div>

</div>
</body>
</html>
