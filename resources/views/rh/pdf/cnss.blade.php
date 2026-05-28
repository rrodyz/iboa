<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
    .page { padding: 15px 20px; }
    h2 { font-size: 12px; color: #1e40af; margin-bottom: 4px; }
    .sub { font-size: 9px; color: #6b7280; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1e40af; color: white; padding: 5px 6px; text-align: left; font-size: 8px; text-transform: uppercase; }
    td { padding: 4px 6px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #f9fafb; }
    .text-right { text-align: right; font-family: monospace; }
    tfoot td { font-weight: bold; border-top: 2px solid #1e40af; background: #eff6ff; }
</style>
</head>
<body>
<div class="page">
<h2>BORDEREAU DE DÉCLARATION CNSS</h2>
<p class="sub">Période : {{ $run->period_label }} — Généré le {{ now()->format('d/m/Y') }}</p>

<table>
    <thead>
        <tr>
            <th>N° CNSS</th>
            <th>Nom & Prénom</th>
            <th class="text-right">Salaire brut</th>
            <th class="text-right">Base CNSS plafonnée</th>
            <th class="text-right">Cotis. salariale ({{ $payroll->cnss_employee_rate }}%)</th>
            <th class="text-right">Cotis. patronale ({{ $payroll->cnss_employer_rate }}%)</th>
            <th class="text-right">Total CNSS</th>
        </tr>
    </thead>
    <tbody>
    @foreach($run->items as $item)
    <tr>
        <td style="font-family:monospace;">{{ optional($item->employee)->cnss_number ?: '—' }}</td>
        <td>{{ $item->employee_name }}</td>
        <td class="text-right">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
        <td class="text-right">{{ number_format($item->cnss_base, 0, ',', ' ') }}</td>
        <td class="text-right" style="color:#dc2626;">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
        <td class="text-right" style="color:#d97706;">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
        <td class="text-right" style="font-weight:600;">{{ number_format($item->cnss_employee + $item->cnss_employer, 0, ',', ' ') }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">TOTAUX</td>
            <td class="text-right">{{ number_format($run->total_cnss_employee, 0, ',', ' ') }}</td>
            <td class="text-right">{{ number_format($run->total_cnss_employer, 0, ',', ' ') }}</td>
            <td class="text-right">{{ number_format($run->total_cnss_employee + $run->total_cnss_employer, 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
</table>
<p style="margin-top:10px; font-size:8px; color:#6b7280;">
    Taux employé : {{ $payroll->cnss_employee_rate }}% | Taux patronal : {{ $payroll->cnss_employer_rate }}% | Plafond mensuel : {{ number_format($payroll->cnss_ceiling, 0, ',', ' ') }} {{ $payroll->currency_code }}<br>
    {{ $settings?->company_name ?? '' }} — {{ $settings?->tax_id ? 'NINEA : '.$settings->tax_id : '' }}
</p>
</div>
</body>
</html>
