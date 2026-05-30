<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #1f2937; }

.header { background: #4F46E5; color: #fff; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; }
.header .company { font-size: 10pt; font-weight: bold; }
.header .title   { font-size: 11pt; font-weight: bold; text-align: center; flex: 1; }
.header .date    { font-size: 8pt; text-align: right; }

.subheader { background: #4338CA; color: #c7d2fe; padding: 3px 12px; font-size: 7.5pt; font-style: italic; }

.period { background: #EEF2FF; padding: 4px 12px; font-size: 7.5pt; color: #3730A3; border-bottom: 1px solid #C7D2FE; }

.totals-band { background: #3730A3; color: #fff; padding: 5px 12px; font-size: 8.5pt; font-weight: bold; }
.totals-band .kpis { display: flex; gap: 24px; }

table { width: 100%; border-collapse: collapse; margin-top: 4px; }
thead th {
    background: #4F46E5; color: #fff;
    padding: 5px 6px; text-align: left;
    font-size: 7.5pt; font-weight: bold;
    border-right: 1px solid #4338CA;
}
thead th.r { text-align: right; }
tbody tr:nth-child(even) { background: #F5F3FF; }
tbody td { padding: 4px 6px; border-bottom: 1px solid #E5E7EB; font-size: 7.5pt; vertical-align: middle; }
tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
tbody td.c { text-align: center; }
tfoot td { background: #312E81; color: #fff; padding: 5px 6px; font-size: 8pt; font-weight: bold; border-top: 2px solid #3730A3; }
tfoot td.r { text-align: right; }

.badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
.badge-green  { background: #D1FAE5; color: #065F46; }
.badge-blue   { background: #DBEAFE; color: #1E40AF; }
.badge-amber  { background: #FEF3C7; color: #92400E; }
.badge-red    { background: #FEE2E2; color: #991B1B; }
.badge-gray   { background: #F3F4F6; color: #374151; }

.footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7pt; color: #9CA3AF; border-top: 1px solid #E5E7EB; padding: 3px; }
</style>
</head>
<body>

<div class="header">
    <div class="company">{{ $company?->name ?? 'ERP' }}</div>
    <div class="title">{{ mb_strtoupper($title) }}</div>
    <div class="date">Édité le {{ now()->format('d/m/Y H:i') }}</div>
</div>

@if(!empty($subtitle))
<div class="subheader">{{ $subtitle }}</div>
@endif

@if($from || $to)
<div class="period">
    Période : {{ $from }} → {{ $to }}
</div>
@endif

@if(!empty($kpis))
<div class="totals-band">
    <div class="kpis">
        @foreach($kpis as $label => $value)
        <div>{{ $label }} : <strong>{{ $value }}</strong></div>
        @endforeach
    </div>
</div>
@endif

<table>
    <thead>
        <tr>
            @foreach($headers as $h)
            <th class="{{ ($h['align'] ?? 'l') === 'r' ? 'r' : (($h['align'] ?? 'l') === 'c' ? 'c' : '') }}">
                {{ $h['label'] }}
            </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        <tr>
            @foreach($headers as $i => $h)
            @php $val = is_array($row) ? ($row[$i] ?? '') : (object_get($row, $h['key'] ?? '') ?? ''); @endphp
            <td class="{{ ($h['align'] ?? 'l') === 'r' ? 'r' : (($h['align'] ?? 'l') === 'c' ? 'c' : '') }}">
                {{-- [SEC-XSS] Échapper par défaut ; passer 'raw' => true dans $headers pour du HTML voulu (badges status) --}}
                @if(!empty($h['raw'])){!! $val !!}@else{{ $val }}@endif
            </td>
            @endforeach
        </tr>
        @empty
        <tr>
            <td colspan="{{ count($headers) }}" style="text-align:center;color:#9CA3AF;padding:12px;">Aucune donnée</td>
        </tr>
        @endforelse
    </tbody>
    @if(!empty($totalsRow))
    <tfoot>
        <tr>
            @foreach($totalsRow as $i => $v)
            @php $align = ($headers[$i]['align'] ?? 'l') === 'r' ? 'r' : ''; @endphp
            <td class="{{ $align }}">{{ $v }}</td>
            @endforeach
        </tr>
    </tfoot>
    @endif
</table>

<div class="footer">
    {{ $company?->name }} — {{ $title }} — Page <span class="pagenum"></span>
</div>
</body>
</html>
