<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 12mm 10mm; }

/* ── En-tête ── */
.header { background:#065F46; color:#fff; padding:8px 14px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:11pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; width:20%; }
.subheader { background:#059669; color:#D1FAE5; padding:3px 14px; display:table; width:100%; font-size:7pt; font-style:italic; }
.s-left  { display:table-cell; }
.s-right { display:table-cell; text-align:right; }

/* ── Synthèse ── */
.summary { background:#ECFDF5; border:1px solid #6EE7B7; padding:6px 14px; margin-top:6px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 12px; border-right:1px solid #A7F3D0; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:7pt; color:#065F46; }
.sum-value { font-size:11pt; font-weight:bold; color:#065F46; }
.sum-value.warn { color:#92400E; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:8px; }
thead tr { background:#059669; color:#fff; }
th { padding:3.5px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #D1FAE5; }
tbody tr:nth-child(even) { background:#F0FDF4; }
td { padding:3px 5px; vertical-align:middle; }
tfoot td { padding:4px 5px; border-top:1.5px solid #065F46; font-weight:bold; background:#ECFDF5; color:#065F46; }

.mono { font-family: DejaVu Sans Mono, monospace; }

/* ── Taux marge badge ── */
.taux-ok   { color:#166534; font-weight:bold; }
.taux-warn { color:#B45309; font-weight:bold; }
.taux-neg  { color:#991B1B; font-weight:bold; }

/* ── Pied de page ── */
.footer { margin-top:10px; border-top:1px solid #D1FAE5; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
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
        <div class="h-center">ANALYSE DES MARGES</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="s-left">Rentabilité par produit</div>
    <div class="s-right">
        Période : {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
    </div>
</div>

{{-- ── Synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb produits</div>
        <div class="sum-value">{{ $products->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">CA HT total (FCFA)</div>
        <div class="sum-value">{{ number_format((int)$totalCaHt, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Coût achat total (FCFA)</div>
        <div class="sum-value">{{ number_format((int)$totalCout, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Marge brute (FCFA)</div>
        <div class="sum-value {{ $totalMarge < 0 ? 'warn' : '' }}">{{ number_format((int)$totalMarge, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Taux moyen</div>
        <div class="sum-value {{ $tauxMoyen < 0 ? 'warn' : '' }}">{{ $tauxMoyen }}%</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:10%">Référence</th>
            <th class="l" style="width:26%">Produit</th>
            <th class="r" style="width:9%">Qté vendue</th>
            <th class="r" style="width:14%">CA HT (FCFA)</th>
            <th class="r" style="width:14%">Coût achat (FCFA)</th>
            <th class="r" style="width:14%">Marge brute (FCFA)</th>
            <th class="c" style="width:13%">Taux marge (%)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($products as $product)
        @php $tauxClass = $product->taux_marge >= 20 ? 'taux-ok' : ($product->taux_marge >= 0 ? 'taux-warn' : 'taux-neg'); @endphp
        <tr>
            <td class="mono" style="color:#065F46; font-weight:bold; font-size:7pt">{{ $product->reference ?? '—' }}</td>
            <td style="font-weight:500">{{ $product->name }}</td>
            <td class="r mono">{{ number_format((float)$product->qty_vendue, 2, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)$product->ca_ht, 0, ',', ' ') }}</td>
            <td class="r mono" style="color:#374151">{{ number_format((int)$product->cout_achats, 0, ',', ' ') }}</td>
            <td class="r mono" style="font-weight:bold; color:#065F46">{{ number_format((int)$product->marge_brute, 0, ',', ' ') }}</td>
            <td class="c {{ $tauxClass }}">{{ $product->taux_marge }}%</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align:center; padding:16px; color:#9CA3AF;">
                Aucune donnée pour cette période.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($products->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="3" class="l">TOTAL — {{ $products->count() }} produit(s)</td>
            <td class="r mono">{{ number_format((int)$totalCaHt, 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)$totalCout, 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)$totalMarge, 0, ',', ' ') }}</td>
            <td class="c">{{ $tauxMoyen }}%</td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Analyse des marges</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
