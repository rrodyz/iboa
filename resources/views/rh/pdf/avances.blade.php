<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
    .page { padding: 12mm 14mm; }

    /* ── En-tête ─── */
    .header { display:table; width:100%; margin-bottom:8px; }
    .header-left  { display:table-cell; vertical-align:top; width:40%; }
    .header-right { display:table-cell; vertical-align:top; text-align:right; }
    .company-name { font-size:11pt; font-weight:bold; color:#1a3c5c; }
    .doc-title    { font-size:14pt; font-weight:bold; color:#1a3c5c; letter-spacing:1px; }
    .doc-sub      { font-size:8pt; color:#6b7280; margin-top:2px; }

    /* ── Séparateur ─── */
    .hr { border:none; border-top:2px solid #1a3c5c; margin:6px 0; }

    /* ── KPIs ─── */
    .kpi-bar { background:#1a3c5c; color:#fff; border-radius:4px; padding:6px 10px;
               display:table; width:100%; margin-bottom:10px; }
    .kpi-cell { display:table-cell; text-align:center; padding:0 8px; }
    .kpi-label { font-size:6pt; opacity:0.75; }
    .kpi-val   { font-size:10pt; font-weight:bold; font-family:monospace; }

    /* ── Tableau ─── */
    table { width:100%; border-collapse:collapse; font-size:7.5pt; }
    thead tr th {
        background:#1a3c5c; color:#fff; padding:4px 6px;
        text-align:left; font-size:7pt; font-weight:bold;
    }
    thead tr th.r { text-align:right; }
    tbody tr td { padding:4px 6px; border-bottom:1px solid #e5e7eb; vertical-align:middle; }
    tbody tr:nth-child(even) td { background:#f8fafc; }
    tfoot tr td { padding:5px 6px; font-weight:bold; border-top:2px solid #1a3c5c;
                  background:#f0f4f8; }
    .r  { text-align:right; font-family:monospace; }
    .c  { text-align:center; }

    /* ── Badges statut ─── */
    .badge { display:inline-block; padding:1px 5px; border-radius:3px; font-size:6pt; font-weight:bold; }
    .badge-green  { background:#d1fae5; color:#065f46; }
    .badge-red    { background:#fee2e2; color:#991b1b; }
    .badge-amber  { background:#fef3c7; color:#92400e; }

    /* ── Pied de page ─── */
    .footer { margin-top:12px; font-size:6.5pt; color:#9ca3af; text-align:center; border-top:1px solid #e5e7eb; padding-top:4px; }

    /* ── Message vide ─── */
    .empty { text-align:center; padding:20px; color:#9ca3af; font-size:8pt; font-style:italic; }
</style>
</head>
<body>
<div class="page">

@php
    $totalMontant    = $avances->sum('amount');
    $totalRecupere   = $avances->where('status', 'recupere')->sum('amount');
    $nbAvances       = $avances->count();
    $periodDate      = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
    $periodLabel     = ucfirst($periodDate->translatedFormat('F Y'));
@endphp

{{-- En-tête --}}
<div class="header">
    <div class="header-left">
        <div class="company-name">{{ strtoupper($company->name ?? 'ENTREPRISE') }}</div>
        @if($company->address)<div style="font-size:7pt; color:#6b7280; margin-top:1px;">{{ $company->address }}</div>@endif
        @if($company->cnss_number)<div style="font-size:7pt; color:#6b7280;">CNSS : {{ $company->cnss_number }}</div>@endif
    </div>
    <div class="header-right">
        <div class="doc-title">ÉTAT DES AVANCES</div>
        <div class="doc-sub">Période : {{ $periodLabel }}</div>
        <div class="doc-sub">Généré le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<hr class="hr">

{{-- KPIs --}}
<div class="kpi-bar">
    <div class="kpi-cell">
        <div class="kpi-label">Nombre d'avances</div>
        <div class="kpi-val">{{ $nbAvances }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Total avances</div>
        <div class="kpi-val">{{ number_format($totalMontant, 0, ',', ' ') }} F</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Récupérées ce mois</div>
        <div class="kpi-val">{{ number_format($totalRecupere, 0, ',', ' ') }} F</div>
    </div>
</div>

{{-- Tableau --}}
@if($avances->isEmpty())
<div class="empty">Aucune avance récupérée sur ce bulletin de paie.</div>
@else
<table>
    <thead>
        <tr>
            <th style="width:12%">Matricule</th>
            <th style="width:28%">Nom & Prénom</th>
            <th style="width:14%">Date avance</th>
            <th style="width:20%">Motif</th>
            <th style="width:14%" class="r">Montant (FCFA)</th>
            <th style="width:12%" class="c">Statut</th>
        </tr>
    </thead>
    <tbody>
    @foreach($avances as $avance)
    <tr>
        <td>{{ $avance->employee?->matricule ?? '—' }}</td>
        <td>{{ $avance->employee?->full_name ?? '—' }}</td>
        <td class="c">{{ $avance->advance_date ? \Carbon\Carbon::parse($avance->advance_date)->format('d/m/Y') : '—' }}</td>
        <td>{{ $avance->reason ? Str::limit($avance->reason, 35) : '—' }}</td>
        <td class="r">{{ number_format($avance->amount, 0, ',', ' ') }}</td>
        <td class="c">
            @if($avance->status === 'recupere')
                <span class="badge badge-green">Récupérée</span>
            @elseif($avance->status === 'approuve')
                <span class="badge badge-amber">Approuvée</span>
            @else
                <span class="badge badge-red">{{ ucfirst($avance->status) }}</span>
            @endif
        </td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTAL</strong></td>
            <td class="r">{{ number_format($totalMontant, 0, ',', ' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

<div class="footer">
    {{ $company->name ?? '' }} — État des avances — {{ $periodLabel }} — {{ now()->format('d/m/Y') }}
</div>

</div>
</body>
</html>
