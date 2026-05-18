<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 10mm 10mm; }

/* ── Document header bar ──────────────────────────────────────── */
.doc-header { background:#0F766E; color:#fff; padding:6px 12px; margin-bottom:0; }
.doc-header-row { display:table; width:100%; }
.dhl { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; }
.dhc { display:table-cell; text-align:center; font-size:10.5pt; font-weight:bold; vertical-align:middle; letter-spacing:0.5px; }
.dhr { display:table-cell; text-align:right; font-size:7pt; vertical-align:middle; color:#CCFBF1; }

.doc-sub { background:#134E4A; color:#CCFBF1; padding:3px 12px; margin-bottom:8px; display:table; width:100%; font-size:7.5pt; }
.dsl { display:table-cell; }
.dsr { display:table-cell; text-align:right; }

/* ── Session info grid ────────────────────────────────────────── */
.info-grid { display:table; width:100%; border-collapse:collapse; margin-bottom:8px; }
.info-row  { display:table-row; }
.info-cell {
    display:table-cell; padding:4px 8px; font-size:7.5pt;
    border:1px solid #E5E7EB; background:#F9FAFB; vertical-align:top;
    width:25%;
}
.info-label { font-weight:bold; color:#374151; display:block; margin-bottom:1px; font-size:6.5pt; text-transform:uppercase; }
.info-value { color:#111827; }

/* ── Summary bar ──────────────────────────────────────────────── */
.summary { display:table; width:100%; border-collapse:collapse; margin-bottom:8px; }
.sum-cell {
    display:table-cell; text-align:center; padding:5px 4px;
    border:1px solid #E5E7EB; font-size:7.5pt;
}
.sum-val { font-size:11pt; font-weight:bold; display:block; line-height:1.2; }
.sum-lbl { font-size:6.5pt; color:#6B7280; }

/* ── Items table ──────────────────────────────────────────────── */
table.items { width:100%; border-collapse:collapse; font-size:7.5pt; }
thead tr { background:#0F766E; color:#fff; }
th { padding:3px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #E5E7EB; }
tbody tr:nth-child(even) { background:#F0FDFA; }
td { padding:2.5px 5px; vertical-align:middle; }

/* variance colours */
tr.pos { background:#F0FDF4; }
tr.neg { background:#FFF5F5; }
tr.pos td { color:#166534; }
tr.neg td { color:#991B1B; }
.badge-pos { color:#fff; background:#16A34A; padding:1px 5px; border-radius:3px; font-weight:bold; font-size:6.5pt; }
.badge-neg { color:#fff; background:#DC2626; padding:1px 5px; border-radius:3px; font-weight:bold; font-size:6.5pt; }
.badge-ok  { color:#166534; background:#DCFCE7; padding:1px 5px; border-radius:3px; font-weight:bold; font-size:6.5pt; }
.badge-nd  { color:#6B7280; background:#F3F4F6; padding:1px 5px; border-radius:3px; font-size:6.5pt; }

tfoot td {
    padding:3.5px 5px; border-top:1.5px solid #0F766E;
    font-weight:bold; background:#CCFBF1; color:#0F766E;
}
.mono { font-family: DejaVu Sans Mono, monospace; }

/* ── Footer ───────────────────────────────────────────────────── */
.footer { margin-top:10px; border-top:1px solid #E5E7EB; padding-top:4px; font-size:6.5pt; color:#6B7280; display:table; width:100%; }
.fl { display:table-cell; }
.fr { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">

@include('pdf-header')

{{-- ── Document header bar ─────────────────────────────────────────── --}}
@php
    $statusColors = [
        'ouvert'   => '#6B7280',
        'en_cours' => '#1D4ED8',
        'valide'   => '#166534',
        'annule'   => '#B91C1C',
    ];
    $statusColor = $statusColors[$session->status] ?? '#374151';

    $countedItems   = $session->items->filter(fn($i) => $i->counted_quantity !== null)->count();
    $totalItems     = $session->items->count();
    $posVariance    = $session->items->filter(fn($i) => $i->counted_quantity !== null && (float)$i->counted_quantity > (float)$i->theoretical_quantity)->count();
    $negVariance    = $session->items->filter(fn($i) => $i->counted_quantity !== null && (float)$i->counted_quantity < (float)$i->theoretical_quantity)->count();
    $conformItems   = $session->items->filter(fn($i) => $i->counted_quantity !== null && (float)$i->counted_quantity == (float)$i->theoretical_quantity)->count();

    $totalVarianceVal = $session->items
        ->filter(fn($i) => $i->counted_quantity !== null)
        ->sum(fn($i) => ((float)$i->counted_quantity - (float)$i->theoretical_quantity) * (float)$i->unit_cost);
@endphp

<div class="doc-header">
    <div class="doc-header-row">
        <div class="dhl">{{ $session->number ?? '#' . $session->id }}</div>
        <div class="dhc">FICHE D'INVENTAIRE PHYSIQUE</div>
        <div class="dhr">Édité le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="doc-sub">
    <div class="dsl">
        Entrepôt : <strong>{{ $session->warehouse?->name ?? '—' }}</strong>
        &nbsp;·&nbsp; Type : {{ $session->typeLabel() }}
        &nbsp;·&nbsp; Statut : <span style="color:#{{ ltrim($statusColor, '#') }}; font-weight:bold;">{{ $session->statusLabel() }}</span>
    </div>
    <div class="dsr">{{ $totalItems }} article(s) · {{ $countedItems }} compté(s)</div>
</div>

{{-- ── Session info grid ──────────────────────────────────────────── --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="info-label">N° Inventaire</span>
            <span class="info-value mono">{{ $session->number ?? '—' }}</span>
        </div>
        <div class="info-cell">
            <span class="info-label">Date de démarrage</span>
            <span class="info-value">{{ $session->started_at?->format('d/m/Y à H:i') ?? '—' }}</span>
        </div>
        <div class="info-cell">
            <span class="info-label">Date de validation</span>
            <span class="info-value">{{ $session->validated_at?->format('d/m/Y à H:i') ?? '—' }}</span>
        </div>
        <div class="info-cell">
            <span class="info-label">Créé par</span>
            <span class="info-value">{{ $session->createdBy?->name ?? '—' }}</span>
        </div>
    </div>
</div>

{{-- ── Summary bar ────────────────────────────────────────────────── --}}
<div class="summary">
    <div class="sum-cell" style="border-color:#D1D5DB;">
        <span class="sum-val" style="color:#111827;">{{ $totalItems }}</span>
        <span class="sum-lbl">Articles total</span>
    </div>
    <div class="sum-cell" style="border-color:#0D9488; background:#F0FDFA;">
        <span class="sum-val" style="color:#0D9488;">{{ $countedItems }}</span>
        <span class="sum-lbl">Articles comptés</span>
    </div>
    <div class="sum-cell" style="border-color:#86EFAC; background:#F0FDF4;">
        <span class="sum-val" style="color:#166534;">{{ $posVariance }}</span>
        <span class="sum-lbl">Excédents</span>
    </div>
    <div class="sum-cell" style="border-color:#FECACA; background:#FFF5F5;">
        <span class="sum-val" style="color:#DC2626;">{{ $negVariance }}</span>
        <span class="sum-lbl">Manquants</span>
    </div>
    <div class="sum-cell" style="border-color:#D1D5DB; {{ $totalVarianceVal >= 0 ? 'background:#F0FDF4;' : 'background:#FFF5F5;' }}">
        <span class="sum-val" style="color:{{ $totalVarianceVal >= 0 ? '#166534' : '#DC2626' }}; font-size:9pt;">
            {{ ($totalVarianceVal >= 0 ? '+' : '') . number_format((int)$totalVarianceVal, 0, ',', ' ') }}
        </span>
        <span class="sum-lbl">Valeur écart (FCFA)</span>
    </div>
</div>

{{-- ── Items table ─────────────────────────────────────────────────── --}}
@php
    $grandTheo    = 0;
    $grandCounted = 0;
    $grandVarVal  = 0;
@endphp

<table class="items">
    <thead>
        <tr>
            <th class="l"  style="width:10%">Référence</th>
            <th class="l"  style="width:30%">Désignation</th>
            <th class="r"  style="width:10%">Stock théo.</th>
            <th class="r"  style="width:10%">Qté comptée</th>
            <th class="r"  style="width:8%">Écart</th>
            <th class="r"  style="width:12%">Coût unit.</th>
            <th class="r"  style="width:12%">Valeur écart</th>
            <th class="c"  style="width:8%">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($session->items as $item)
        @php
            $theo     = (float) $item->theoretical_quantity;
            $counted  = $item->counted_quantity !== null ? (float) $item->counted_quantity : null;
            $variance = $counted !== null ? $counted - $theo : null;
            $varValue = $variance !== null ? $variance * (float) $item->unit_cost : null;

            if ($variance === null)      { $rowCss = ''; $badge = '<span class="badge-nd">Non compté</span>'; }
            elseif ($variance > 0)       { $rowCss = 'pos'; $badge = '<span class="badge-pos">Excédent</span>'; }
            elseif ($variance < 0)       { $rowCss = 'neg'; $badge = '<span class="badge-neg">Manquant</span>'; }
            else                         { $rowCss = ''; $badge = '<span class="badge-ok">Conforme</span>'; }

            $grandTheo    += $theo;
            $grandCounted += $counted ?? 0;
            $grandVarVal  += $varValue ?? 0;
        @endphp
        <tr class="{{ $rowCss }}">
            <td class="mono l" style="font-size:7pt; color:#0F766E">{{ $item->product?->reference ?? '—' }}</td>
            <td class="l">{{ $item->product?->name ?? '—' }}</td>
            <td class="r mono">{{ number_format($theo, 2, ',', ' ') }}</td>
            <td class="r mono">{{ $counted !== null ? number_format($counted, 2, ',', ' ') : '—' }}</td>
            <td class="r mono" style="font-weight:{{ $variance !== null && $variance != 0 ? 'bold' : 'normal' }}">
                @if($variance === null)
                    —
                @elseif($variance == 0)
                    =
                @else
                    {{ ($variance > 0 ? '+' : '') . number_format($variance, 2, ',', ' ') }}
                @endif
            </td>
            <td class="r mono">{{ (float)$item->unit_cost > 0 ? number_format((int)$item->unit_cost, 0, ',', ' ') : '—' }}</td>
            <td class="r mono">{{ $varValue !== null ? number_format((int)$varValue, 0, ',', ' ') : '—' }}</td>
            <td class="c">{!! $badge !!}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align:center; padding:12px; color:#9CA3AF;">
                Aucun article dans cet inventaire.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($session->items->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="2" class="l">TOTAUX — {{ $totalItems }} article(s), {{ $countedItems }} compté(s)</td>
            <td class="r mono">{{ number_format($grandTheo, 2, ',', ' ') }}</td>
            <td class="r mono">{{ number_format($grandCounted, 2, ',', ' ') }}</td>
            <td class="r mono" style="color:{{ $grandVarVal >= 0 ? '#166534' : '#DC2626' }}">
                {{ ($grandVarVal >= 0 ? '+' : '') . number_format($grandVarVal, 0, ',', ' ') }}
            </td>
            <td></td>
            <td class="r mono" style="color:{{ $grandVarVal >= 0 ? '#166534' : '#DC2626' }}">
                {{ ($grandVarVal >= 0 ? '+' : '') . number_format((int)$grandVarVal, 0, ',', ' ') }} FCFA
            </td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Notes ──────────────────────────────────────────────────────── --}}
@if($session->notes)
<div style="margin-top:8px; padding:6px 8px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:4px; font-size:7.5pt; color:#92400E;">
    <strong>Notes :</strong> {{ $session->notes }}
</div>
@endif

{{-- ── Signature block ─────────────────────────────────────────────── --}}
<div style="margin-top:14px; display:table; width:100%; font-size:7.5pt;">
    <div style="display:table-cell; width:33%; text-align:center; padding:0 8px;">
        <div style="border-top:1px solid #9CA3AF; padding-top:4px; margin-top:24px; color:#6B7280;">
            Établi par
        </div>
        <div style="margin-top:2px;">{{ $session->createdBy?->name ?? '............................' }}</div>
    </div>
    <div style="display:table-cell; width:33%; text-align:center; padding:0 8px;">
        <div style="border-top:1px solid #9CA3AF; padding-top:4px; margin-top:24px; color:#6B7280;">
            Contrôlé par
        </div>
        <div style="margin-top:2px;">............................</div>
    </div>
    <div style="display:table-cell; width:33%; text-align:center; padding:0 8px;">
        <div style="border-top:1px solid #9CA3AF; padding-top:4px; margin-top:24px; color:#6B7280;">
            Validé par
        </div>
        <div style="margin-top:2px;">{{ $session->validatedBy?->name ?? '............................' }}</div>
    </div>
</div>

<div class="footer">
    <div class="fl">{{ $session->warehouse?->name ?? '' }} — Inventaire {{ $session->number ?? '' }}</div>
    <div class="fr">Document généré le {{ now()->format('d/m/Y à H:i') }} — Confidentiel</div>
</div>

</div>
</body>
</html>
