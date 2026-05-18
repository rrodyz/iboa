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
.hl  { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; }
.hc  { display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.hr  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; }
.sub { background:#4338CA; color:#e0e7ff; padding:3px 12px; display:table; width:100%; font-size:7.5pt; margin-bottom:8px; }
.sl { display:table-cell; }
.sr { display:table-cell; text-align:right; }
table { width:100%; border-collapse:collapse; font-size:7.5pt; }
thead tr { background:#312E81; color:#fff; }
th { padding:3px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#F8F7FF; }
td { padding:2.5px 5px; }
tfoot td { padding:3.5px 5px; border-top:1.5px solid #312E81; font-weight:bold; background:#EEF2FF; color:#312E81; }
.mono { font-family: DejaVu Sans Mono, monospace; }
.d { color:#1D4ED8; font-weight:600; }
.c-col { color:#B91C1C; font-weight:600; }
.badge { padding:1px 5px; border-radius:3px; font-size:7pt; font-weight:bold; }
.badge-valide   { background:#DCFCE7; color:#166534; }
.badge-brouillon{ background:#FEF9C3; color:#854D0E; }
.badge-cloture  { background:#F3F4F6; color:#374151; }
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
        <div class="hc">JOURNAL COMPTABLE</div>
        <div class="hr">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="sub">
    <div class="sl">{{ $entries->count() }} écriture(s)</div>
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
            <th class="l" style="width:12%">Numéro</th>
            <th class="c" style="width:7%">Journal</th>
            <th class="l" style="width:9%">Date</th>
            <th class="l" style="width:13%">Référence</th>
            <th class="l" style="width:33%">Libellé</th>
            <th class="r" style="width:11%">Débit (FCFA)</th>
            <th class="r" style="width:11%">Crédit (FCFA)</th>
            <th class="c" style="width:4%">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $entry)
        <tr>
            <td class="mono" style="color:#4338CA; font-weight:600">{{ $entry->number }}</td>
            <td class="c mono" style="font-size:7pt; background:#F5F3FF; color:#5B21B6; font-weight:600">{{ $entry->journalType?->code }}</td>
            <td>{{ $entry->entry_date?->format('d/m/Y') }}</td>
            <td style="font-size:7pt; color:#6B7280">{{ $entry->reference }}</td>
            <td>{{ \Illuminate\Support\Str::limit($entry->description, 60) }}</td>
            <td class="r mono d">{{ $entry->total_debit  > 0 ? number_format((int)$entry->total_debit,  0,',',' ') : '—' }}</td>
            <td class="r mono c-col">{{ $entry->total_credit > 0 ? number_format((int)$entry->total_credit, 0,',',' ') : '—' }}</td>
            <td class="c">
                @php
                    $bc = $entry->status === 'valide' ? 'valide' : ($entry->status === 'brouillon' ? 'brouillon' : 'cloture');
                    $bl = $entry->status === 'valide' ? 'Validé' : ($entry->status === 'brouillon' ? 'Brouillon' : 'Clôturé');
                @endphp
                <span class="badge badge-{{ $bc }}">{{ $bl }}</span>
            </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center; padding:10px; color:#9CA3AF;">Aucune écriture.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="l">Total — {{ $entries->count() }} écriture(s)</td>
            <td class="r mono d">{{ number_format((int)$totalDebit,  0,',',' ') }}</td>
            <td class="r mono c-col">{{ number_format((int)$totalCredit, 0,',',' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    <div class="fl">{{ $company?->name }} — Journal comptable SYSCOHADA</div>
    <div class="fr">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>
</div>
</body>
</html>
