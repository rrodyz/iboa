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

    /* ── Progression prêt ─── */
    .progress-bar { background:#e5e7eb; border-radius:3px; height:6px; width:100%; display:block; }
    .progress-fill { background:#1a3c5c; border-radius:3px; height:6px; display:block; }

    /* ── Badges ─── */
    .badge { display:inline-block; padding:1px 5px; border-radius:3px; font-size:6pt; font-weight:bold; }
    .badge-green  { background:#d1fae5; color:#065f46; }
    .badge-blue   { background:#dbeafe; color:#1e40af; }
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
    $totalMensualite = $payments->sum('amount');
    $nbPrets         = $payments->count();
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
        <div class="doc-title">ÉTAT DES PRÊTS</div>
        <div class="doc-sub">Période : {{ $periodLabel }}</div>
        <div class="doc-sub">Généré le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<hr class="hr">

{{-- KPIs --}}
<div class="kpi-bar">
    <div class="kpi-cell">
        <div class="kpi-label">Remboursements</div>
        <div class="kpi-val">{{ $nbPrets }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Total mensualités</div>
        <div class="kpi-val">{{ number_format($totalMensualite, 0, ',', ' ') }} F</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-label">Solde restant total</div>
        <div class="kpi-val">{{ number_format($payments->sum('balance_after'), 0, ',', ' ') }} F</div>
    </div>
</div>

{{-- Tableau --}}
@if($payments->isEmpty())
<div class="empty">Aucun remboursement de prêt enregistré sur ce bulletin de paie.</div>
@else
<table>
    <thead>
        <tr>
            <th style="width:11%">Matricule</th>
            <th style="width:25%">Nom & Prénom</th>
            <th style="width:11%">N° Prêt</th>
            <th style="width:12%" class="r">Montant prêt</th>
            <th style="width:12%" class="r">Mensualité</th>
            <th style="width:13%" class="r">Solde restant</th>
            <th style="width:16%" class="c">Avancement</th>
        </tr>
    </thead>
    <tbody>
    @foreach($payments as $payment)
    @php
        $loan       = $payment->loan;
        $employee   = $loan?->employee;
        $totalLoan  = (int) ($loan?->amount ?? 0);
        $remaining  = (int) ($payment->balance_after ?? 0);
        $paidPct    = $totalLoan > 0 ? min(100, round(($totalLoan - $remaining) / $totalLoan * 100)) : 0;
    @endphp
    <tr>
        <td>{{ $employee?->matricule ?? '—' }}</td>
        <td>{{ $employee?->full_name ?? '—' }}</td>
        <td class="c">{{ $loan?->loan_number ?? ('P-' . str_pad($payment->employee_loan_id, 4, '0', STR_PAD_LEFT)) }}</td>
        <td class="r">{{ number_format($totalLoan, 0, ',', ' ') }}</td>
        <td class="r">{{ number_format($payment->amount, 0, ',', ' ') }}</td>
        <td class="r">{{ number_format($remaining, 0, ',', ' ') }}</td>
        <td class="c">
            <span class="progress-bar">
                <span class="progress-fill" style="width:{{ $paidPct }}%;"></span>
            </span>
            <span style="font-size:6pt; color:#6b7280;">{{ $paidPct }}% remboursé</span>
        </td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"><strong>TOTAL</strong></td>
            <td class="r">{{ number_format($payments->sum(fn($p) => $p->loan?->amount ?? 0), 0, ',', ' ') }}</td>
            <td class="r">{{ number_format($totalMensualite, 0, ',', ' ') }}</td>
            <td class="r">{{ number_format($payments->sum('balance_after'), 0, ',', ' ') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

<div class="footer">
    {{ $company->name ?? '' }} — État des prêts — {{ $periodLabel }} — {{ now()->format('d/m/Y') }}
</div>

</div>
</body>
</html>
