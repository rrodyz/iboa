<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 12mm 10mm; }

/* ── En-tête ── */
.header { background:#3730A3; color:#fff; padding:8px 14px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:11pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; width:20%; }
.subheader { background:#4F46E5; color:#E0E7FF; padding:3px 14px; display:table; width:100%; font-size:7pt; font-style:italic; }
.s-left  { display:table-cell; }
.s-right { display:table-cell; text-align:right; }

/* ── Synthèse ── */
.summary { background:#EEF2FF; border:1px solid #A5B4FC; padding:6px 14px; margin-top:6px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 14px; border-right:1px solid #C7D2FE; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:7pt; color:#3730A3; }
.sum-value { font-size:11pt; font-weight:bold; color:#3730A3; }
.sum-value.dim { font-size:9pt; color:#4338CA; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:8pt; margin-top:8px; }
thead tr { background:#4F46E5; color:#fff; }
th { padding:4px 6px; font-size:7.5pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #E0E7FF; }
tbody tr:nth-child(even) { background:#F5F3FF; }
td { padding:3px 6px; vertical-align:middle; }
tfoot td { padding:4px 6px; border-top:1.5px solid #3730A3; font-weight:bold; background:#EEF2FF; color:#3730A3; }

.mono { font-family: DejaVu Sans Mono, monospace; }

/* ── Pied de page ── */
.footer { margin-top:10px; border-top:1px solid #C7D2FE; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
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
        <div class="h-center">RAPPORT CHIFFRE D'AFFAIRES</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="s-left">Analyse des ventes par période</div>
    <div class="s-right">
        Période : {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
    </div>
</div>

{{-- ── Synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb périodes</div>
        <div class="sum-value">{{ $serie->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Nb factures</div>
        <div class="sum-value">{{ (int)($totals->nb_factures ?? 0) }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">CA HT (FCFA)</div>
        <div class="sum-value">{{ number_format((int)($totals->total_ht ?? 0), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">CA TTC (FCFA)</div>
        <div class="sum-value">{{ number_format((int)($totals->total_ttc ?? 0), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Encaissé (FCFA)</div>
        <div class="sum-value dim">{{ number_format((int)($totals->total_encaisse ?? 0), 0, ',', ' ') }}</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:22%">Période</th>
            <th class="c" style="width:13%">Nb Factures</th>
            <th class="r" style="width:22%">CA HT (FCFA)</th>
            <th class="r" style="width:22%">CA TTC (FCFA)</th>
            <th class="r" style="width:21%">Encaissé (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($serie as $row)
        <tr>
            <td style="font-weight:500">{{ $row->label }}</td>
            <td class="c">{{ $row->nb }}</td>
            <td class="r mono">{{ number_format((int)$row->ht, 0, ',', ' ') }}</td>
            <td class="r mono" style="font-weight:bold; color:#3730A3">{{ number_format((int)$row->ttc, 0, ',', ' ') }}</td>
            <td class="r mono" style="color:#374151">{{ number_format((int)$row->encaisse, 0, ',', ' ') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center; padding:16px; color:#9CA3AF;">
                Aucune donnée pour cette période.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($serie->isNotEmpty())
    <tfoot>
        <tr>
            <td class="l">TOTAL — {{ $serie->count() }} période(s)</td>
            <td class="c">{{ (int)($totals->nb_factures ?? 0) }}</td>
            <td class="r mono">{{ number_format((int)($totals->total_ht ?? 0), 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)($totals->total_ttc ?? 0), 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)($totals->total_encaisse ?? 0), 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Rapport CA</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
