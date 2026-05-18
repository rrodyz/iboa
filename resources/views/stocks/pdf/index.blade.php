<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 12mm 10mm; }
.header { background:#0F766E; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.hl  { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; }
.hc  { display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.hr  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; }
.sub { background:#0D9488; color:#CCFBF1; padding:3px 12px; display:table; width:100%; font-size:7.5pt; margin-bottom:8px; }
.sl { display:table-cell; }
.sr { display:table-cell; text-align:right; }
table { width:100%; border-collapse:collapse; font-size:7.5pt; }
thead tr { background:#0F766E; color:#fff; }
th { padding:3px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#F0FDFA; }
td { padding:2.5px 5px; }
tfoot td { padding:3.5px 5px; border-top:1.5px solid #0F766E; font-weight:bold; background:#CCFBF1; color:#0F766E; }
.mono { font-family: DejaVu Sans Mono, monospace; }
.ok   { color:#166534; font-weight:600; }
.warn { color:#B45309; font-weight:600; }
.crit { color:#B91C1C; font-weight:600; }
.footer { margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
.fl { display:table-cell; }
.fr { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-row">
        <div class="hl">{{ $company?->name }}</div>
        <div class="hc">ÉTAT DES STOCKS{{ $lowStock ? ' — STOCK BAS' : '' }}</div>
        <div class="hr">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="sub">
    <div class="sl">
        {{ $stocks->count() }} article(s)
        @if($warehouseName) — Entrepôt : {{ $warehouseName }}@endif
        @if($search) — Filtre : "{{ $search }}"@endif
    </div>
    <div class="sr">Quantités en unités de gestion de stock</div>
</div>

<table>
    <thead>
        <tr>
            <th class="l" style="width:11%">Référence</th>
            <th class="l" style="width:30%">Désignation</th>
            <th class="l" style="width:13%">Entrepôt</th>
            <th class="r" style="width:8%">Total</th>
            <th class="r" style="width:8%">Réservé</th>
            <th class="r" style="width:9%">Disponible</th>
            <th class="r" style="width:7%">Min</th>
            <th class="r" style="width:14%">Valeur (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @php
            $grandQty   = 0;
            $grandValue = 0;
        @endphp
        @forelse($stocks as $stock)
        @php
            $available = $stock->quantity - $stock->reserved_quantity;
            $min       = $stock->product?->stock_min ?? 0;
            $isLow     = $available <= $min && $min > 0;
            $isOut     = $available <= 0;
            $value     = $stock->quantity * ($stock->product?->purchase_price ?? 0);
            $grandQty  += $stock->quantity;
            $grandValue+= $value;
            $css = $isOut ? 'crit' : ($isLow ? 'warn' : 'ok');
        @endphp
        <tr>
            <td class="mono" style="font-size:7pt; color:#0F766E">{{ $stock->product?->reference }}</td>
            <td>{{ $stock->product?->name }}</td>
            <td style="font-size:7pt; color:#6B7280">{{ $stock->warehouse?->name }}</td>
            <td class="r mono">{{ number_format($stock->quantity, 0,',',' ') }}</td>
            <td class="r mono" style="color:#6B7280">{{ $stock->reserved_quantity > 0 ? number_format($stock->reserved_quantity, 0,',',' ') : '—' }}</td>
            <td class="r mono {{ $css }}">{{ number_format($available, 0,',',' ') }}</td>
            <td class="r mono" style="color:#6B7280">{{ $min > 0 ? number_format($min, 0,',',' ') : '—' }}</td>
            <td class="r mono">{{ $value > 0 ? number_format((int)$value, 0,',',' ') : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center; padding:10px; color:#9CA3AF;">Aucun article en stock.</td></tr>
        @endforelse
    </tbody>
    @if($stocks->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="3" class="l">Total</td>
            <td class="r mono">{{ number_format((int)$grandQty, 0,',',' ') }}</td>
            <td></td><td></td><td></td>
            <td class="r mono">{{ number_format((int)$grandValue, 0,',',' ') }}</td>
        </tr>
    </tfoot>
    @endif
</table>

<div class="footer">
    <div class="fl">{{ $company?->name }} — État des stocks</div>
    <div class="fr">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>
</div>
</body>
</html>
