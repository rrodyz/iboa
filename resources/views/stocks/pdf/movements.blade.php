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
.badge { padding:1px 5px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.t-entree   { background:#DCFCE7; color:#166534; }
.t-sortie   { background:#FEE2E2; color:#991B1B; }
.t-transfert{ background:#DBEAFE; color:#1E40AF; }
.t-ajust    { background:#FEF9C3; color:#854D0E; }
.t-other    { background:#F3F4F6; color:#374151; }
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
        <div class="hc">JOURNAL DES MOUVEMENTS DE STOCK</div>
        <div class="hr">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="sub">
    <div class="sl">{{ $movements->count() }} mouvement(s)</div>
    <div class="sr">
        @if(!empty($filters['date_from']) || !empty($filters['date_to']))
        Période : {{ !empty($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') : '—' }}
        → {{ !empty($filters['date_to']) ? \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') : 'aujourd\'hui' }}
        @else
        Toutes les dates
        @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="l" style="width:9%">Date</th>
            <th class="c" style="width:9%">Type</th>
            <th class="l" style="width:11%">Référence</th>
            <th class="l" style="width:25%">Produit</th>
            <th class="l" style="width:12%">Entrepôt</th>
            <th class="r" style="width:8%">Quantité</th>
            <th class="r" style="width:11%">Coût unit. (FCFA)</th>
            <th class="r" style="width:11%">Total (FCFA)</th>
            <th class="l" style="width:4%">Par</th>
        </tr>
    </thead>
    <tbody>
        @php $grandQty = 0; $grandVal = 0; @endphp
        @forelse($movements as $mv)
        @php
            $isEntry  = in_array($mv->type, ['entree','retour_client','retour_fournisseur','inventaire']);
            $qty      = $mv->quantity;
            $unit     = $mv->unit_cost ?? 0;
            $total    = $qty * $unit;
            if ($isEntry) { $grandQty += $qty; $grandVal += $total; }
            else { $grandQty -= $qty; }
            $typeCss = match($mv->type) {
                'entree','retour_client','retour_fournisseur' => 't-entree',
                'sortie' => 't-sortie',
                'transfert' => 't-transfert',
                'ajustement','inventaire' => 't-ajust',
                default => 't-other',
            };
        @endphp
        <tr>
            <td>{{ $mv->occurred_at?->format('d/m/Y') }}</td>
            <td class="c"><span class="badge {{ $typeCss }}">{{ $typeLabels[$mv->type] ?? $mv->type }}</span></td>
            <td class="mono" style="font-size:7pt; color:#0F766E">{{ $mv->reference ?? '—' }}</td>
            <td>
                {{ $mv->product?->name }}
                @if($mv->product?->reference)<span style="color:#9CA3AF; font-size:6.5pt"> ({{ $mv->product->reference }})</span>@endif
            </td>
            <td style="font-size:7pt; color:#6B7280">{{ $mv->warehouse?->name }}</td>
            <td class="r mono" style="{{ $isEntry ? 'color:#166534;' : 'color:#B91C1C;' }}">
                {{ $isEntry ? '+' : '-' }}{{ number_format($qty, 0,',',' ') }}
            </td>
            <td class="r mono">{{ $unit > 0 ? number_format((int)$unit, 0,',',' ') : '—' }}</td>
            <td class="r mono">{{ $total > 0 ? number_format((int)$total, 0,',',' ') : '—' }}</td>
            <td style="font-size:6.5pt; color:#9CA3AF">{{ $mv->createdBy?->name ? substr($mv->createdBy->name, 0, 8) : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center; padding:10px; color:#9CA3AF;">Aucun mouvement.</td></tr>
        @endforelse
    </tbody>
    @if($movements->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="5" class="l">Total — {{ $movements->count() }} mouvement(s)</td>
            <td class="r mono"></td>
            <td></td>
            <td class="r mono">{{ number_format((int)$grandVal, 0,',',' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

<div class="footer">
    <div class="fl">{{ $company?->name }} — Mouvements de stock</div>
    <div class="fr">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>
</div>
</body>
</html>
