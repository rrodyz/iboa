<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color:#111; }
    .page { padding: 10mm 12mm; }
    h2 { font-size:11pt; color:#1e3a5f; margin-bottom:2px; }
    .sub { font-size:7pt; color:#6b7280; margin-bottom:8px; }
    .section { margin-bottom:14px; border-bottom:1px solid #e5e7eb; padding-bottom:10px; }
    .section-title { font-size:9pt; font-weight:bold; color:#1e3a5f; background:#e8edf5; padding:3px 6px; margin-bottom:4px; }
    table { width:100%; border-collapse:collapse; }
    th { background:#1f2937; color:white; padding:3px 5px; font-size:6.5pt; text-transform:uppercase; }
    td { padding:2.5px 5px; border-bottom:1px solid #f3f4f6; font-size:7pt; }
    tr:nth-child(even) td { background:#f9fafb; }
    .r { text-align:right; font-family:monospace; }
    tfoot td { font-weight:bold; border-top:2px solid #1f2937; background:#f1f5f9; }
    .kpi { display:inline-block; padding:5px 10px; background:#1e3a5f; color:white; border-radius:4px; margin:0 3px 6px 0; font-size:7pt; text-align:center; }
    .kpi b { display:block; font-size:9pt; font-family:monospace; }
    .grand-total td { background:#1e3a5f !important; color:white !important; font-weight:bold; }
</style>
</head>
<body>
<div class="page">

<h2>LIVRE DE PAIE — EXERCICE {{ $year }}</h2>
<p class="sub">
    {{ $settings?->company_name ?? $company->name }} ·
    Édité le {{ now()->format('d/m/Y à H:i') }} ·
    {{ $runs->count() }} bulletin(s) validé(s)
</p>

@php
$grandBrut=0; $grandCnssE=0; $grandCnssP=0; $grandIuts=0; $grandNet=0; $grandEff=0;
@endphp

@forelse($runs as $run)
@php
$grandBrut  += $run->total_brut;
$grandCnssE += $run->total_cnss_employee;
$grandCnssP += $run->total_cnss_employer;
$grandIuts  += $run->total_iuts;
$grandNet   += $run->total_net;
$grandEff    = max($grandEff, $run->employee_count);
@endphp

<div class="section">
    <div class="section-title">{{ strtoupper($run->period_label) }} — {{ $run->employee_count }} employé(s) — {{ $run->status_label }}</div>

    <div style="margin-bottom:6px;">
        @foreach([
            ['Brut', number_format($run->total_brut,0,',',' ').' F'],
            ['CNSS sal.', number_format($run->total_cnss_employee,0,',',' ').' F'],
            ['CNSS pat.', number_format($run->total_cnss_employer,0,',',' ').' F'],
            ['IUTS', number_format($run->total_iuts,0,',',' ').' F'],
            ['Net', number_format($run->total_net,0,',',' ').' F'],
            ['Coût emp.', number_format($run->total_brut+$run->total_cnss_employer,0,',',' ').' F'],
        ] as [$l,$v])
        <div class="kpi"><div style="font-size:6pt;">{{ $l }}</div><b>{{ $v }}</b></div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Mat.</th>
                <th>Employé</th>
                <th>Dépt.</th>
                <th class="r">Brut</th>
                <th class="r">CNSS sal.</th>
                <th class="r">CNSS pat.</th>
                <th class="r">IUTS</th>
                <th class="r">Net</th>
                <th class="r">Coût emp.</th>
            </tr>
        </thead>
        <tbody>
        @foreach($run->items->sortBy('employee_name') as $item)
        <tr>
            <td style="font-family:monospace;font-size:6pt;">{{ $item->employee_matricule }}</td>
            <td>{{ $item->employee_name }}</td>
            <td style="font-size:6.5pt;color:#6b7280;">{{ $item->department_name ?: '—' }}</td>
            <td class="r">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
            <td class="r" style="color:#dc2626;">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
            <td class="r" style="color:#d97706;">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
            <td class="r" style="color:#7c3aed;">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
            <td class="r" style="color:#166534;font-weight:bold;">{{ number_format($item->salaire_net, 0, ',', ' ') }}</td>
            <td class="r">{{ number_format($item->salaire_brut + $item->cnss_employer, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">TOTAUX {{ strtoupper($run->period_label) }}</td>
                <td class="r">{{ number_format($run->total_brut, 0, ',', ' ') }}</td>
                <td class="r" style="color:#dc2626;">{{ number_format($run->total_cnss_employee, 0, ',', ' ') }}</td>
                <td class="r" style="color:#d97706;">{{ number_format($run->total_cnss_employer, 0, ',', ' ') }}</td>
                <td class="r" style="color:#7c3aed;">{{ number_format($run->total_iuts, 0, ',', ' ') }}</td>
                <td class="r" style="color:#166534;">{{ number_format($run->total_net, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($run->total_brut + $run->total_cnss_employer, 0, ',', ' ') }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@empty
<p style="text-align:center;color:#9ca3af;padding:20px 0;">Aucun bulletin validé pour {{ $year }}.</p>
@endforelse

{{-- RÉCAPITULATIF ANNUEL --}}
@if($runs->count() > 0)
<div style="margin-top:8px;">
    <div class="section-title" style="font-size:10pt;">RÉCAPITULATIF ANNUEL {{ $year }}</div>
    <table style="margin-top:4px;">
        <thead>
            <tr>
                <th>Rubrique</th>
                @foreach($runs as $r)
                <th class="r" style="font-size:5.5pt;">{{ str_pad($r->period_month,2,'0',STR_PAD_LEFT) }}/{{ $r->period_year }}</th>
                @endforeach
                <th class="r">TOTAL ANNUEL</th>
            </tr>
        </thead>
        <tbody>
        @php
        $rows = [
            ['Masse salariale brute', 'total_brut'],
            ['CNSS salarié (' . $payroll->cnss_employee_rate . '%)',  'total_cnss_employee'],
            ['CNSS patronal (' . $payroll->cnss_employer_rate . '%)', 'total_cnss_employer'],
            ['IUTS',                  'total_iuts'],
            ['Net à payer',           'total_net'],
        ];
        @endphp
        @foreach($rows as [$label,$field])
        @php $lineTotal = $runs->sum($field); @endphp
        <tr>
            <td>{{ $label }}</td>
            @foreach($runs as $r)
            <td class="r" style="font-size:6.5pt;">{{ number_format($r->$field, 0, ',', ' ') }}</td>
            @endforeach
            <td class="r" style="font-weight:bold;">{{ number_format($lineTotal, 0, ',', ' ') }}</td>
        </tr>
        @endforeach
        <tr class="grand-total">
            <td>Coût total employeur</td>
            @foreach($runs as $r)
            <td class="r" style="font-size:6.5pt;">{{ number_format($r->total_brut + $r->total_cnss_employer, 0, ',', ' ') }}</td>
            @endforeach
            <td class="r">{{ number_format($grandBrut + $grandCnssP, 0, ',', ' ') }}</td>
        </tr>
        </tbody>
    </table>
</div>
@endif

</div>
</body>
</html>
