<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 10mm 8mm; }

/* ── En-tête ── */
.header { background:#4C1D95; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; width:20%; }
.subheader { background:#6D28D9; color:#EDE9FE; padding:3px 12px; display:table; width:100%; font-size:7pt; font-style:italic; }

/* ── Synthèse ── */
.summary { background:#F5F3FF; border:1px solid #C4B5FD; padding:5px 12px; margin-top:5px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 10px; border-right:1px solid #DDD6FE; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:6.5pt; color:#4C1D95; }
.sum-value { font-size:10pt; font-weight:bold; color:#4C1D95; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:7pt; margin-top:7px; }
thead tr { background:#7C3AED; color:#fff; }
th { padding:3px 4px; font-size:6.5pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.3px solid #EDE9FE; }
tbody tr:nth-child(even) { background:#FAF5FF; }
td { padding:2.5px 4px; vertical-align:middle; }
tfoot td { padding:4px; border-top:1.5px solid #4C1D95; font-weight:bold; background:#F5F3FF; color:#4C1D95; }

.mono { font-family: DejaVu Sans Mono, monospace; font-size:6.5pt; }

/* ── Badges ── */
.badge { padding:1px 4px; border-radius:3px; font-size:6pt; font-weight:bold; }
.b-actif   { background:#DCFCE7; color:#166534; }
.b-inactif { background:#F3F4F6; color:#6B7280; }
.b-type-s  { background:#EDE9FE; color:#4C1D95; }
.b-type-c  { background:#DBEAFE; color:#1E40AF; }

/* ── Pied de page ── */
.footer { margin-top:8px; border-top:1px solid #DDD6FE; padding-top:3px; font-size:6.5pt; color:#6B7280; display:table; width:100%; }
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
        <div class="h-center">CATALOGUE ARTICLES</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div>
        Référentiel produits actifs
        @if(!empty($filters['family_id'])) &nbsp;|&nbsp; Famille filtrée @endif
        @if(!empty($filters['search'])) &nbsp;|&nbsp; Recherche : {{ $filters['search'] }} @endif
    </div>
</div>

{{-- ── Synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb articles</div>
        <div class="sum-value">{{ $products->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Total valeur stock (FCFA)</div>
        <div class="sum-value">{{ number_format((int)$products->sum('sale_price'), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">TVA moyenne (%)</div>
        <div class="sum-value">{{ $products->count() > 0 ? round($products->avg(fn($p) => $p->taxRate?->rate ?? 0), 1) : 0 }}%</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Types distincts</div>
        <div class="sum-value">{{ $products->pluck('type')->unique()->count() }}</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:9%">Référence</th>
            <th class="l" style="width:20%">Désignation</th>
            <th class="l" style="width:11%">Famille</th>
            <th class="l" style="width:8%">Marque</th>
            <th class="c" style="width:5%">Unité</th>
            <th class="c" style="width:5%">TVA</th>
            <th class="r" style="width:10%">Prix achat (FCFA)</th>
            <th class="r" style="width:10%">Prix vente (FCFA)</th>
            <th class="c" style="width:6%">Stock min</th>
            <th class="c" style="width:6%">Stock max</th>
            <th class="c" style="width:7%">Méthode</th>
            <th class="c" style="width:6%">Type</th>
        </tr>
    </thead>
    <tbody>
        @forelse($products as $product)
        <tr>
            <td class="mono" style="color:#4C1D95; font-weight:bold">{{ $product->reference }}</td>
            <td style="font-weight:500">{{ $product->name }}</td>
            <td style="color:#374151">{{ $product->family?->name ?? '—' }}</td>
            <td style="color:#374151">{{ $product->brand?->name ?? '—' }}</td>
            <td class="c">{{ $product->unit?->abbreviation ?? '—' }}</td>
            <td class="c">{{ $product->taxRate?->rate ?? 0 }}%</td>
            <td class="r mono">{{ number_format((int)$product->purchase_price, 0, ',', ' ') }}</td>
            <td class="r mono" style="font-weight:bold; color:#4C1D95">{{ number_format((int)$product->sale_price, 0, ',', ' ') }}</td>
            <td class="c">{{ $product->stock_min ?? '—' }}</td>
            <td class="c">{{ $product->stock_max ?? '—' }}</td>
            <td class="c"><span class="mono">{{ strtoupper($product->valuation_method ?? 'CMP') }}</span></td>
            <td class="c">
                @php
                    $typeClass = match($product->type ?? 'standard') {
                        'service'  => 'b-type-s',
                        'compose'  => 'b-type-c',
                        default    => 'b-type-s',
                    };
                @endphp
                <span class="badge {{ $typeClass }}">{{ ucfirst($product->type ?? 'standard') }}</span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="12" style="text-align:center; padding:14px; color:#9CA3AF;">
                Aucun article trouvé.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($products->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="6" class="l">TOTAL — {{ $products->count() }} article(s)</td>
            <td class="r mono">{{ number_format((int)$products->sum('purchase_price'), 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)$products->sum('sale_price'), 0, ',', ' ') }}</td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Catalogue articles</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
