<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
    .mono { font-family: DejaVu Sans Mono, monospace; }
    h1 { font-size: 15pt; margin: 0 0 2px; color: #1e293b; }
    .muted { color: #6b7280; }
    .right { text-align: right; }
    .center { text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { background: #4f46e5; color: #fff; padding: 6px 8px; font-size: 8pt; text-transform: uppercase; }
    td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
    tfoot td { background: #eef2ff; font-weight: bold; color: #312e81; border-top: 2px solid #c7d2fe; }
    .head { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 4px; }
    .kpis { width: 100%; margin: 12px 0; }
    .kpis td { border: none; padding: 4px 6px; }
    .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px 8px; }
    .green { color: #047857; }
    .red { color: #b91c1c; }
    .indigo { color: #4338ca; }
</style>
</head>
<body>

    <div class="head">
        <table style="width:100%"><tr>
            <td style="border:none;padding:0">
                <h1>{{ $company->name }}</h1>
                <div class="muted" style="font-size:8pt">
                    {{ $company->address }}@if($company->phone) · {{ $company->phone }}@endif
                    @if($company->ifu) · IFU {{ $company->ifu }}@endif
                </div>
            </td>
            <td style="border:none;padding:0;text-align:right">
                <strong style="font-size:12pt">État de trésorerie</strong><br>
                <span class="muted" style="font-size:8pt">
                    Du {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
                </span>
            </td>
        </tr></table>
    </div>

    {{-- Synthèse --}}
    <table class="kpis"><tr>
        <td><div class="box"><div class="muted" style="font-size:7pt">SOLDE OUVERTURE</div><strong class="mono">{{ number_format($totals['ouverture'], 0, ',', ' ') }}</strong></div></td>
        <td><div class="box"><div class="muted" style="font-size:7pt">ENTRÉES</div><strong class="mono green">+{{ number_format($totals['entrees'], 0, ',', ' ') }}</strong></div></td>
        <td><div class="box"><div class="muted" style="font-size:7pt">SORTIES</div><strong class="mono red">−{{ number_format($totals['sorties'], 0, ',', ' ') }}</strong></div></td>
        <td><div class="box"><div class="muted" style="font-size:7pt">SOLDE CLÔTURE</div><strong class="mono indigo">{{ number_format($totals['cloture'], 0, ',', ' ') }}</strong></div></td>
    </tr></table>

    {{-- Détail par compte --}}
    <table>
        <thead>
            <tr>
                <th style="text-align:left">Compte</th>
                <th style="text-align:left">Type</th>
                <th class="right">Ouverture</th>
                <th class="right">Entrées</th>
                <th class="right">Sorties</th>
                <th class="right">Clôture</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td>{{ $r->name }}</td>
                <td style="text-transform:capitalize">{{ $r->type }}</td>
                <td class="right mono">{{ number_format($r->ouverture, 0, ',', ' ') }}</td>
                <td class="right mono green">{{ $r->entrees ? '+'.number_format($r->entrees, 0, ',', ' ') : '—' }}</td>
                <td class="right mono red">{{ $r->sorties ? '−'.number_format($r->sorties, 0, ',', ' ') : '—' }}</td>
                <td class="right mono"><strong>{{ number_format($r->cloture, 0, ',', ' ') }}</strong></td>
            </tr>
            @empty
            <tr><td colspan="6" class="center muted" style="padding:20px">Aucun mouvement de trésorerie sur la période.</td></tr>
            @endforelse
        </tbody>
        @if($rows->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="2">TOTAL</td>
                <td class="right mono">{{ number_format($totals['ouverture'], 0, ',', ' ') }}</td>
                <td class="right mono">+{{ number_format($totals['entrees'], 0, ',', ' ') }}</td>
                <td class="right mono">−{{ number_format($totals['sorties'], 0, ',', ' ') }}</td>
                <td class="right mono">{{ number_format($totals['cloture'], 0, ',', ' ') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="muted center" style="margin-top:20px;font-size:7pt">
        Édité le {{ now()->format('d/m/Y à H:i') }} — {{ $company->name }}
    </div>

</body>
</html>
