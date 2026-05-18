<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 12mm 10mm; }
.header { background:#312E81; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.header-left  { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; }
.header-center{ display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.header-right { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; }
.subheader { background:#4338CA; color:#e0e7ff; padding:3px 12px; display:table; width:100%; font-size:7.5pt; margin-bottom:8px; }
.subheader-left  { display:table-cell; }
.subheader-right { display:table-cell; text-align:right; }
table { width:100%; border-collapse:collapse; font-size:7.5pt; }
.header-row-1 { background:#4338CA; color:#fff; }
.header-row-2 { background:#5B50D4; color:#fff; }
th { padding:3px 4px; font-weight:bold; font-size:7pt; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
tbody tr { border-bottom:0.4px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#F8F7FF; }
td { padding:2.5px 4px; }
.class-row td { background:#EEF2FF; font-weight:bold; color:#312E81; font-size:7pt; padding:2px 4px; }
tfoot td { padding:3px 4px; border-top:1.5px solid #312E81; font-weight:bold; background:#EEF2FF; color:#312E81; }
.mono { font-family: DejaVu Sans Mono, monospace; }
.d { color:#1D4ED8; }
.c { color:#B91C1C; }
.dim { color:#9CA3AF; }
.footer { margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
.footer-left  { display:table-cell; }
.footer-right { display:table-cell; text-align:right; }
.warn { color:#B45309; font-size:7pt; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-row">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">BALANCE GÉNÉRALE SYSCOHADA</div>
        <div class="header-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">{{ $accounts->count() }} compte(s) avec mouvements — Écritures validées</div>
    <div class="subheader-right">
        Période : {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '—' }}
        → {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'aujourd\'hui' }}
    </div>
</div>

<table>
    <thead>
        <tr class="header-row-1">
            <th class="l" style="width:9%" rowspan="2">Code</th>
            <th class="l" style="width:27%" rowspan="2">Libellé</th>
            <th class="r" colspan="2" style="width:16%; {{ !$hasPeriod ? 'opacity:0.4;' : '' }}">Ouverture</th>
            <th class="r" colspan="2" style="width:16%">Mouvements</th>
            <th class="r" colspan="2" style="width:16%">Soldes finaux</th>
        </tr>
        <tr class="header-row-2">
            <th class="r" style="{{ !$hasPeriod ? 'opacity:0.4;' : '' }}">Débit</th>
            <th class="r" style="{{ !$hasPeriod ? 'opacity:0.4;' : '' }}">Crédit</th>
            <th class="r">Débit</th>
            <th class="r">Crédit</th>
            <th class="r" style="color:#93C5FD">Débiteur</th>
            <th class="r" style="color:#FCA5A5">Créditeur</th>
        </tr>
    </thead>
    <tbody>
        @php $currentClass = null; @endphp
        @forelse($accounts as $account)
        @php $classNum = substr($account->code, 0, 1); @endphp
        @if($classNum !== $currentClass)
        @php $currentClass = $classNum; @endphp
        <tr class="class-row">
            <td colspan="8">Classe {{ $classNum }}@if($account->accountClass?->name) — {{ $account->accountClass->name }}@endif</td>
        </tr>
        @endif
        <tr>
            <td class="mono" style="color:#4338CA; font-weight:600">{{ $account->code }}</td>
            <td>{{ $account->name }}</td>
            <td class="r mono dim">{{ $hasPeriod ? ($account->open_debit  > 0 ? number_format($account->open_debit,  0,',',' ') : '—') : '—' }}</td>
            <td class="r mono dim">{{ $hasPeriod ? ($account->open_credit > 0 ? number_format($account->open_credit, 0,',',' ') : '—') : '—' }}</td>
            <td class="r mono">{{ $account->period_debit  > 0 ? number_format($account->period_debit,  0,',',' ') : '—' }}</td>
            <td class="r mono">{{ $account->period_credit > 0 ? number_format($account->period_credit, 0,',',' ') : '—' }}</td>
            <td class="r mono d">{{ $account->solde_debiteur  > 0 ? number_format($account->solde_debiteur,  0,',',' ') : '—' }}</td>
            <td class="r mono c">{{ $account->solde_crediteur > 0 ? number_format($account->solde_crediteur, 0,',',' ') : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center; padding:10px; color:#9CA3AF;">Aucun mouvement.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" class="l" style="font-size:8pt">Totaux généraux</td>
            <td class="r mono dim">{{ $hasPeriod ? number_format($totals['open_debit'],  0,',',' ') : '—' }}</td>
            <td class="r mono dim">{{ $hasPeriod ? number_format($totals['open_credit'], 0,',',' ') : '—' }}</td>
            <td class="r mono d">{{ number_format($totals['period_debit'],    0,',',' ') }}</td>
            <td class="r mono c">{{ number_format($totals['period_credit'],   0,',',' ') }}</td>
            <td class="r mono d">{{ number_format($totals['solde_debiteur'],  0,',',' ') }}</td>
            <td class="r mono c">{{ number_format($totals['solde_crediteur'], 0,',',' ') }}</td>
        </tr>
        @if(!$isBalanced)
        <tr>
            <td colspan="8" class="warn" style="text-align:center">
                ⚠ Écart de {{ number_format(abs($totals['solde_debiteur'] - $totals['solde_crediteur']), 0,',',' ') }} — vérifier les écritures
            </td>
        </tr>
        @endif
    </tfoot>
</table>

<div class="footer">
    <div class="footer-left">{{ $company?->name }} — Balance générale SYSCOHADA</div>
    <div class="footer-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>
</div>
</body>
</html>
