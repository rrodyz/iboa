<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; line-height: 1.35; }
    .page { padding: 10mm 12mm; }

    /* En-tête */
    h2  { font-size: 13pt; color: #1e3a5f; margin: 0 0 2px 0; letter-spacing: .3px; }
    .sub { font-size: 8pt; color: #374151; margin-bottom: 10px; }

    /* Bloc mensuel */
    .section { margin-bottom: 16px; border-bottom: 1.5px solid #d1d5db; padding-bottom: 12px; }
    .section-title {
        font-size: 9.5pt; font-weight: bold; color: #fff;
        background: #1e3a5f; padding: 4px 8px; margin-bottom: 6px;
        border-radius: 2px;
    }

    /* KPIs */
    .kpi-row { margin-bottom: 8px; }
    .kpi {
        display: inline-block; padding: 4px 10px;
        background: #f1f5f9; border: 1px solid #cbd5e1;
        border-radius: 4px; margin: 0 4px 4px 0;
        font-size: 7.5pt; text-align: center; color: #111;
    }
    .kpi .lbl { font-size: 6.5pt; color: #6b7280; display: block; }
    .kpi .val { font-family: monospace; font-size: 9pt; font-weight: bold; color: #1e3a5f; display: block; }
    .kpi.net .val  { color: #14532d; }
    .kpi.cout .val { color: #7c2d12; }

    /* Tableau principal */
    table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    thead th {
        background: #1f2937; color: #fff;
        padding: 4px 6px; font-size: 7pt;
        text-transform: uppercase; letter-spacing: .4px;
        font-weight: bold;
    }
    tbody td {
        padding: 3.5px 6px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 8pt;
        color: #111;
        vertical-align: middle;
    }
    tbody tr:nth-child(even) td { background: #f9fafb; }
    tbody tr:hover td { background: #eff6ff; }

    /* Colonnes alignées à droite */
    .r  { text-align: right; font-family: monospace; }

    /* Couleurs de déduction — sombres pour lisibilité PDF */
    .c-cnss-e { color: #991b1b; }   /* rouge foncé  — CNSS salarié  */
    .c-cnss-p { color: #78350f; }   /* brun foncé   — CNSS patronal */
    .c-iuts   { color: #4c1d95; }   /* violet foncé — IUTS          */
    .c-net    { color: #14532d; font-weight: bold; } /* vert foncé  — Net */

    /* Pied de tableau totaux */
    tfoot td {
        padding: 4px 6px;
        font-size: 8pt;
        font-weight: bold;
        background: #1e3a5f;
        color: #fff;
        border-top: none;
    }
    tfoot .r { font-family: monospace; }

    /* Grand total final */
    .grand-total td {
        background: #111827 !important;
        color: #fff !important;
        font-weight: bold;
        font-size: 8pt;
    }

    /* Récapitulatif annuel */
    .recap-title {
        font-size: 11pt; font-weight: bold; color: #fff;
        background: #111827; padding: 5px 8px; margin: 12px 0 6px;
        border-radius: 2px;
    }
    .recap-table thead th {
        background: #374151; color: #fff;
        padding: 4px 6px; font-size: 7pt; text-transform: uppercase;
    }
    .recap-table tbody td {
        padding: 3.5px 6px; font-size: 8pt;
        border-bottom: 1px solid #e5e7eb; color: #111;
    }
    .recap-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .recap-table tbody td.r { font-family: monospace; color: #1e3a5f; font-weight: bold; }
    .recap-table tfoot td {
        background: #111827; color: #fff;
        font-weight: bold; font-size: 8.5pt; padding: 5px 6px;
    }
</style>
</head>
<body>
<div class="page">

<h2>LIVRE DE PAIE &mdash; {{ isset($month) && $month > 0 ? $monthLabel : 'EXERCICE ' . $year }}</h2>
<p class="sub">
    {{ $settings?->company_name ?? $company->name }}
    &nbsp;&middot;&nbsp; Edite le {{ now()->format('d/m/Y a H:i') }}
    &nbsp;&middot;&nbsp; {{ $runs->count() }} bulletin(s) valide(s)
</p>

@php
$grandBrut=0; $grandCnssE=0; $grandCnssP=0; $grandIuts=0; $grandNet=0;
@endphp

@forelse($runs as $run)
@php
$grandBrut  += $run->total_brut;
$grandCnssE += $run->total_cnss_employee;
$grandCnssP += $run->total_cnss_employer;
$grandIuts  += $run->total_iuts;
$grandNet   += $run->total_net;
@endphp

<div class="section">
    <div class="section-title">{{ strtoupper($run->period_label) }} &mdash; {{ $run->employee_count }} employe(s) &mdash; {{ $run->status_label }}</div>

    {{-- KPIs --}}
    <div class="kpi-row">
        @foreach([
            ['Brut',       number_format($run->total_brut,          0,',',' ').' F', ''],
            ['CNSS sal.',  number_format($run->total_cnss_employee,  0,',',' ').' F', ''],
            ['CNSS pat.',  number_format($run->total_cnss_employer,  0,',',' ').' F', ''],
            ['IUTS',       number_format($run->total_iuts,           0,',',' ').' F', ''],
            ['Net',        number_format($run->total_net,            0,',',' ').' F', 'net'],
            ['Cout emp.',  number_format($run->total_brut+$run->total_cnss_employer,0,',',' ').' F', 'cout'],
        ] as [$l,$v,$cls])
        <div class="kpi {{ $cls }}"><span class="lbl">{{ $l }}</span><span class="val">{{ $v }}</span></div>
        @endforeach
    </div>

    {{-- Tableau detail --}}
    <table>
        <thead>
            <tr>
                <th style="width:12%">Mat.</th>
                <th style="width:22%">Employe</th>
                <th style="width:12%">Dept.</th>
                <th class="r" style="width:12%">Brut</th>
                <th class="r" style="width:10%">CNSS sal.</th>
                <th class="r" style="width:10%">CNSS pat.</th>
                <th class="r" style="width:8%">IUTS</th>
                <th class="r" style="width:10%">Net</th>
                <th class="r" style="width:11%">Cout emp.</th>
            </tr>
        </thead>
        <tbody>
        @foreach($run->items->sortBy('employee_name') as $item)
        <tr>
            <td style="font-family:monospace;font-size:7pt;color:#374151;">{{ $item->employee_matricule }}</td>
            <td style="font-weight:600;">{{ $item->employee_name }}</td>
            <td style="font-size:7.5pt;color:#374151;">{{ $item->department_name ?: '---' }}</td>
            <td class="r">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
            <td class="r c-cnss-e">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
            <td class="r c-cnss-p">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
            <td class="r c-iuts">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
            <td class="r c-net">{{ number_format($item->salaire_net, 0, ',', ' ') }}</td>
            <td class="r">{{ number_format($item->salaire_brut + $item->cnss_employer, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTAUX {{ strtoupper($run->period_label) }}</td>
                <td class="r">{{ number_format($run->total_brut, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($run->total_cnss_employee, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($run->total_cnss_employer, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($run->total_iuts, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($run->total_net, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($run->total_brut + $run->total_cnss_employer, 0, ',', ' ') }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@empty
<p style="text-align:center;color:#6b7280;padding:20px 0;">Aucun bulletin valide pour {{ $year }}.</p>
@endforelse

{{-- RECAPITULATIF --}}
@if($runs->count() > 0)
<div>
    <div class="recap-title">
        RECAPITULATIF {{ isset($month) && $month > 0 ? $monthLabel : 'ANNUEL ' . $year }}
    </div>
    <table class="recap-table">
        <thead>
            <tr>
                <th style="width:30%;text-align:left;">Rubrique</th>
                @foreach($runs as $r)
                <th class="r" style="width:{{ max(8, intval(55/$runs->count())) }}%;">
                    {{ str_pad($r->period_month,2,'0',STR_PAD_LEFT) }}/{{ $r->period_year }}
                </th>
                @endforeach
                <th class="r" style="width:15%;">Total</th>
            </tr>
        </thead>
        <tbody>
        @php
        $rows = [
            ['Masse salariale brute',                                    'total_brut'],
            ['CNSS salarie ('  . $payroll->cnss_employee_rate . '%)',    'total_cnss_employee'],
            ['CNSS patronal (' . $payroll->cnss_employer_rate . '%)',    'total_cnss_employer'],
            ['IUTS',                                                      'total_iuts'],
            ['Net a payer',                                               'total_net'],
        ];
        @endphp
        @foreach($rows as [$label,$field])
        @php $lineTotal = $runs->sum($field); @endphp
        <tr>
            <td>{{ $label }}</td>
            @foreach($runs as $r)
            <td class="r">{{ number_format($r->$field, 0, ',', ' ') }}</td>
            @endforeach
            <td class="r">{{ number_format($lineTotal, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr class="grand-total">
            <td>Cout total employeur</td>
            @foreach($runs as $r)
            <td class="r">{{ number_format($r->total_brut + $r->total_cnss_employer, 0, ',', ' ') }}</td>
            @endforeach
            <td class="r">{{ number_format($grandBrut + $grandCnssP, 0, ',', ' ') }}</td>
        </tr>
        </tfoot>
    </table>
</div>
@endif

</div>
</body>
</html>
