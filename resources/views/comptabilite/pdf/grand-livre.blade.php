<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
.page { padding: 12mm 10mm; }

/* ── Header ── */
.header { background:#312E81; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.header-left  { display:table-cell; font-size:10.5pt; font-weight:bold; vertical-align:middle; }
.header-center{ display:table-cell; text-align:center; font-size:10.5pt; font-weight:bold; vertical-align:middle; }
.header-right { display:table-cell; text-align:right; font-size:8pt; vertical-align:middle; }

.subheader { background:#4338CA; color:#e0e7ff; padding:3px 12px; display:table; width:100%; font-size:7.5pt; font-style:italic; margin-bottom:8px; }
.subheader-left  { display:table-cell; }
.subheader-right { display:table-cell; text-align:right; }

/* ── Grand total bar ── */
.grand-total { background:#EEF2FF; border:1px solid #818CF8; padding:4px 10px; margin-bottom:8px; font-size:8pt; display:table; width:100%; }
.gt-left  { display:table-cell; font-weight:bold; color:#312E81; }
.gt-right { display:table-cell; text-align:right; }
.gt-d { color:#1D4ED8; font-weight:bold; margin-right:16px; }
.gt-c { color:#B91C1C; font-weight:bold; margin-right:16px; }
.gt-s { font-weight:bold; }

/* ── Account block ── */
.account-header { background:#4338CA; color:#fff; padding:4px 8px; margin-top:8px; display:table; width:100%; }
.acc-left  { display:table-cell; font-size:9pt; font-weight:bold; }
.acc-right { display:table-cell; text-align:right; font-size:8pt; }

/* ── Table ── */
table { width:100%; border-collapse:collapse; font-size:7.5pt; }
thead tr { background:#EEF2FF; }
thead th { padding:3px 4px; text-align:center; font-size:7pt; font-weight:bold; color:#3730A3; border-bottom:1px solid #818CF8; }
th.l, td.l { text-align:left !important; }
th.r, td.r { text-align:right !important; }
tbody tr { border-bottom:0.4px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#F8F7FF; }
td { padding:2.5px 4px; }
.mono { font-family: DejaVu Sans Mono, monospace; }
tfoot td { padding:3px 4px; border-top:1px solid #818CF8; font-weight:bold; background:#EEF2FF; color:#312E81; font-size:8pt; }

/* balance colours */
.d-pos { color:#1D4ED8; }
.d-neg { color:#B91C1C; }
.eq    { color:#9CA3AF; }

/* ── Footer ── */
.footer { margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
.footer-left  { display:table-cell; }
.footer-right { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

{{-- ── Page header ── --}}
<div class="header">
    <div class="header-row">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">{{ strtoupper($title) }}</div>
        <div class="header-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">Écritures validées — SYSCOHADA</div>
    <div class="subheader-right">
        Période : {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '—' }}
        →
        {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : 'aujourd\'hui' }}
    </div>
</div>

{{-- ── Grand total ── --}}
@if($accountGroups->isNotEmpty())
<div class="grand-total">
    <div class="gt-left">{{ $accountGroups->count() }} compte(s) avec mouvements</div>
    <div class="gt-right">
        <span class="gt-d">Total D : {{ number_format($grandDebit, 0, ',', ' ') }} FCFA</span>
        <span class="gt-c">Total C : {{ number_format($grandCredit, 0, ',', ' ') }} FCFA</span>
        @if($grandBalance == 0)
            <span class="eq">Équilibré</span>
        @else
            <span class="gt-s {{ $grandBalance > 0 ? 'd-pos' : 'd-neg' }}">
                Solde : {{ number_format(abs($grandBalance), 0, ',', ' ') }} FCFA {{ $grandBalance > 0 ? 'D' : 'C' }}
            </span>
        @endif
    </div>
</div>
@endif

{{-- ── Per-account blocks ── --}}
@php $currentClass = null; @endphp

@foreach($accountGroups as $group)
@php
    $acc      = $group['account'];
    $lines    = $group['lines'];
    $totD     = $group['total_debit'];
    $totC     = $group['total_credit'];
    $bal      = $group['balance'];
    $classNum = substr($acc->code, 0, 1);
@endphp

{{-- Class separator --}}
@if($classNum !== $currentClass)
@php $currentClass = $classNum; @endphp
<div style="margin-top:10px; margin-bottom:2px; font-size:7.5pt; font-weight:bold; color:#312E81; text-transform:uppercase; letter-spacing:0.05em; border-bottom:1px solid #818CF8; padding-bottom:2px;">
    Classe {{ $classNum }}
</div>
@endif

<div class="account-header">
    <div class="acc-left">{{ $acc->code }} — {{ $acc->name }}</div>
    <div class="acc-right">
        D : {{ number_format($totD, 0, ',', ' ') }} &nbsp;|&nbsp;
        C : {{ number_format($totC, 0, ',', ' ') }} &nbsp;|&nbsp;
        Solde :
        @if($bal == 0)
            Équilibré
        @else
            {{ number_format(abs($bal), 0, ',', ' ') }} {{ $bal > 0 ? 'D' : 'C' }}
        @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="l" style="width:9%">Date</th>
            <th style="width:5%">Jnl</th>
            <th class="l" style="width:13%">N° pièce</th>
            <th class="l" style="width:13%">Référence</th>
            <th class="l" style="width:33%">Libellé</th>
            <th class="r" style="width:11%">Débit (FCFA)</th>
            <th class="r" style="width:11%">Crédit (FCFA)</th>
            <th class="r" style="width:5%">Solde</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
        @php $rb = $line->running_balance; @endphp
        <tr>
            <td>{{ $line->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}</td>
            <td style="text-align:center" class="mono">{{ $line->journalEntry?->journalType?->code ?? '—' }}</td>
            <td class="mono">{{ $line->journalEntry?->number ?? '—' }}</td>
            <td>{{ $line->journalEntry?->reference ?? '' }}</td>
            <td>{{ $line->label ?: ($line->journalEntry?->description ?? '') }}</td>
            <td class="r mono">{{ $line->debit  > 0 ? number_format((int)$line->debit,  0, ',', ' ') : '—' }}</td>
            <td class="r mono">{{ $line->credit > 0 ? number_format((int)$line->credit, 0, ',', ' ') : '—' }}</td>
            <td class="r mono {{ $rb > 0 ? 'd-pos' : ($rb < 0 ? 'd-neg' : 'eq') }}" style="font-size:7pt;">
                @if($rb == 0)—@else{{ number_format(abs($rb), 0, ',', ' ') }}<br><span style="font-size:6pt;">{{ $rb > 0 ? 'D' : 'C' }}</span>@endif
            </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center; color:#9CA3AF; padding:6px;">Aucun mouvement</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="l">Total {{ $acc->code }} — {{ $lines->count() }} ligne(s)</td>
            <td class="r mono">{{ $totD > 0 ? number_format((int)$totD, 0, ',', ' ') : '—' }}</td>
            <td class="r mono">{{ $totC > 0 ? number_format((int)$totC, 0, ',', ' ') : '—' }}</td>
            <td class="r mono {{ $bal > 0 ? 'd-pos' : ($bal < 0 ? 'd-neg' : 'eq') }}" style="font-size:7pt;">
                @if($bal == 0)—@else{{ number_format(abs((int)$bal), 0, ',', ' ') }}<br><span style="font-size:6pt;">{{ $bal > 0 ? 'D' : 'C' }}</span>@endif
            </td>
        </tr>
    </tfoot>
</table>

@endforeach

@if($accountGroups->isEmpty())
<div style="text-align:center; padding:20px; color:#9CA3AF; font-size:9pt;">
    Aucun mouvement comptable trouvé pour les filtres sélectionnés.
</div>
@endif

{{-- ── Page footer ── --}}
<div class="footer">
    <div class="footer-left">{{ $company?->name }} — Grand Livre SYSCOHADA</div>
    <div class="footer-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
