<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 12mm 10mm; }

/* ── En-tête ── */
.header { background:#4C1D95; color:#fff; padding:8px 14px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:11pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; width:20%; }
.subheader { background:#7C3AED; color:#EDE9FE; padding:3px 14px; display:table; width:100%; font-size:7pt; font-style:italic; }
.s-left  { display:table-cell; }
.s-right { display:table-cell; text-align:right; }

/* ── Synthèse ── */
.summary { background:#F5F3FF; border:1px solid #C4B5FD; padding:6px 14px; margin-top:6px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 14px; border-right:1px solid #DDD6FE; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:7pt; color:#4C1D95; }
.sum-value { font-size:11pt; font-weight:bold; color:#4C1D95; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:8px; }
thead tr { background:#7C3AED; color:#fff; }
th { padding:3.5px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #EDE9FE; }
tbody tr:nth-child(even) { background:#FAF5FF; }
td { padding:3px 5px; vertical-align:middle; }
tfoot td { padding:4px 5px; border-top:1.5px solid #4C1D95; font-weight:bold; background:#F5F3FF; color:#4C1D95; }

.mono { font-family: DejaVu Sans Mono, monospace; }

/* ── Pied de page ── */
.footer { margin-top:10px; border-top:1px solid #DDD6FE; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
.f-left  { display:table-cell; }
.f-right { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

{{-- ── En-tête ── --}}
<div class="header">
    <div class="header-row">
        <div class="h-left">{{ $company?->name }}</div>
        <div class="h-center">PERFORMANCE COMMERCIALE</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="s-left">Ventes par commercial</div>
    <div class="s-right">
        Période : {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
    </div>
</div>

{{-- ── Synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb commerciaux</div>
        <div class="sum-value">{{ $perUser->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Nb factures total</div>
        <div class="sum-value">{{ (int)($grandTotal->nb_factures ?? 0) }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">CA total (FCFA)</div>
        <div class="sum-value">{{ number_format((int)($grandTotal->ca_total ?? 0), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Encaissé (FCFA)</div>
        <div class="sum-value">{{ number_format((int)($grandTotal->encaisse ?? 0), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Taux encaissement</div>
        <div class="sum-value">
            @php $tauxEnc = ($grandTotal->ca_total ?? 0) > 0 ? round(($grandTotal->encaisse / $grandTotal->ca_total) * 100, 1) : 0; @endphp
            {{ $tauxEnc }}%
        </div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:20%">Commercial</th>
            <th class="c" style="width:11%">Nb Factures</th>
            <th class="c" style="width:11%">Nb Clients</th>
            <th class="r" style="width:16%">CA Total (FCFA)</th>
            <th class="r" style="width:14%">Encaissé (FCFA)</th>
            <th class="r" style="width:14%">Reste (FCFA)</th>
            <th class="r" style="width:14%">Panier moyen (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($perUser as $row)
        @php
            $partCA = ($grandTotal->ca_total ?? 0) > 0
                ? round(($row->ca_total / $grandTotal->ca_total) * 100, 1) : 0;
        @endphp
        <tr>
            <td style="font-weight:500">{{ optional($row->creator)->name ?? 'Utilisateur #'.$row->created_by }}</td>
            <td class="c">{{ $row->nb_factures }}</td>
            <td class="c">{{ $row->nb_clients }}</td>
            <td class="r mono" style="font-weight:bold; color:#4C1D95">{{ number_format((int)$row->ca_total, 0, ',', ' ') }}
                <span style="font-size:6pt; font-weight:normal; color:#7C3AED">&nbsp;({{ $partCA }}%)</span>
            </td>
            <td class="r mono">{{ number_format((int)$row->encaisse, 0, ',', ' ') }}</td>
            <td class="r mono" style="{{ (int)$row->reste > 0 ? 'color:#B45309' : 'color:#374151' }}">{{ number_format((int)$row->reste, 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)$row->panier_moyen, 0, ',', ' ') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align:center; padding:16px; color:#9CA3AF;">
                Aucune donnée pour cette période.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($perUser->isNotEmpty())
    <tfoot>
        <tr>
            <td class="l">TOTAL — {{ $perUser->count() }} commercial(aux)</td>
            <td class="c">{{ (int)($grandTotal->nb_factures ?? 0) }}</td>
            <td class="c">—</td>
            <td class="r mono">{{ number_format((int)($grandTotal->ca_total ?? 0), 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)($grandTotal->encaisse ?? 0), 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)(($grandTotal->ca_total ?? 0) - ($grandTotal->encaisse ?? 0)), 0, ',', ' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Performance commerciale</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
