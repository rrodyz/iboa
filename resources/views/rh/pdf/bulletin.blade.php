<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #1a1a1a; }
    .page { padding: 14mm 12mm; }

    /* ── En-tête ──────────────────────────────────────────── */
    .header { display:table; width:100%; border-bottom: 2px solid #1e3a5f; padding-bottom: 6px; margin-bottom: 8px; }
    .header-left  { display:table-cell; width:50%; vertical-align:top; }
    .header-right { display:table-cell; width:50%; vertical-align:top; text-align:right; }
    .company-name { font-size:11pt; font-weight:bold; color:#1e3a5f; }
    .bulletin-title { font-size:13pt; font-weight:bold; color:#1e3a5f; text-transform:uppercase; letter-spacing:1px; }
    .period-badge { display:inline-block; background:#1e3a5f; color:white; padding:2px 10px; border-radius:3px; font-size:9pt; font-weight:bold; }

    /* ── Bloc employeur / employé ─────────────────────────── */
    .info-grid { display:table; width:100%; margin-bottom:8px; }
    .info-box  { display:table-cell; width:50%; padding: 6px 8px; border: 1px solid #d1d5db; vertical-align:top; }
    .info-box.left  { border-right:none; background:#f8fafc; }
    .info-box.right { background:#fffbeb; }
    .info-title { font-size:7pt; font-weight:bold; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; border-bottom:1px solid #e5e7eb; padding-bottom:2px; }
    .info-line  { display:table; width:100%; margin-bottom:1.5px; }
    .info-label { display:table-cell; width:40%; color:#6b7280; font-size:7pt; }
    .info-value { display:table-cell; font-weight:bold; font-size:7.5pt; }

    /* ── Tableau des rubriques ────────────────────────────── */
    .rubriques { width:100%; border-collapse:collapse; margin-bottom:6px; }
    .rubriques th {
        background:#1e3a5f; color:white; padding:3px 5px;
        font-size:6.5pt; text-transform:uppercase; letter-spacing:.3px;
        text-align:left;
    }
    .rubriques th.r { text-align:right; }
    .rubriques td { padding:2.5px 5px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    .rubriques tr:nth-child(even) td { background:#f9fafb; }

    .rubrique-section td { background:#e8edf5; color:#1e3a5f; font-weight:bold; font-size:7pt; text-transform:uppercase; padding:2px 5px; }
    .rubrique-total td { background:#1e3a5f; color:white; font-weight:bold; }
    .rubrique-net td   { background:#166534; color:white; font-weight:bold; font-size:9pt; }

    .num  { text-align:right; font-family:monospace; }
    .gain { color:#166534; }
    .retr { color:#dc2626; }

    /* ── Pied de bulletin ─────────────────────────────────── */
    .footer-grid { display:table; width:100%; margin-top:6px; border-top:1px solid #d1d5db; padding-top:6px; }
    .footer-left  { display:table-cell; width:60%; vertical-align:top; }
    .footer-right { display:table-cell; width:40%; vertical-align:top; text-align:right; }

    .ytd { width:100%; border-collapse:collapse; margin-top:4px; }
    .ytd th { background:#374151; color:white; padding:2.5px 5px; font-size:6pt; text-transform:uppercase; text-align:right; }
    .ytd th:first-child { text-align:left; }
    .ytd td { padding:2.5px 5px; border-bottom:1px solid #f3f4f6; text-align:right; font-size:6.5pt; }
    .ytd td:first-child { text-align:left; }

    .net-box { border:1.5px solid #166534; background:#f0fdf4; padding:6px 10px; border-radius:3px; }
    .net-amount { font-size:13pt; font-weight:bold; color:#166534; font-family:monospace; }
    .sign-box { border:1px solid #d1d5db; padding:10px; text-align:center; font-size:6.5pt; color:#6b7280; }
    .mention { font-size:6pt; color:#9ca3af; margin-top:4px; }
</style>
</head>
<body>
<div class="page">

{{-- ══ EN-TÊTE ══ --}}
<div class="header">
    <div class="header-left">
        <div class="company-name">{{ $settings?->company_name ?? $run->company?->name }}</div>
        @if($settings?->address)<div style="font-size:7pt;color:#6b7280;margin-top:2px;">{{ $settings->address }}</div>@endif
        @if($settings?->phone)<div style="font-size:7pt;color:#6b7280;">Tél. : {{ $settings->phone }}</div>@endif
        @if($run->company?->cnss_number ?? null)<div style="font-size:7pt;color:#6b7280;">N° CNSS patronal : {{ $run->company->cnss_number }}</div>@endif
    </div>
    <div class="header-right">
        <div class="bulletin-title">Bulletin de Paie</div>
        <div style="margin-top:4px;"><span class="period-badge">{{ strtoupper($run->period_label) }}</span></div>
        <div style="font-size:6.5pt;color:#6b7280;margin-top:4px;">
            Édité le {{ now()->format('d/m/Y') }} — {{ $run->status_label }}
        </div>
    </div>
</div>

{{-- ══ EMPLOYEUR / EMPLOYÉ ══ --}}
<div class="info-grid">
    <div class="info-box left">
        <div class="info-title">Employeur</div>
        @foreach([
            'Raison sociale' => $settings?->company_name ?? $run->company?->name,
            'N° CNSS'        => $run->company?->cnss_number ?? '—',
        ] as $l=>$v)
        <div class="info-line"><span class="info-label">{{ $l }}</span><span class="info-value">{{ $v }}</span></div>
        @endforeach
    </div>
    <div class="info-box right">
        <div class="info-title">Employé</div>
        @php $emp = $item->employee; @endphp
        @foreach(array_filter([
            'Matricule'       => $item->employee_matricule,
            'Nom & Prénom'    => $item->employee_name,
            'Poste'           => $item->job_title,
            'Département'     => $item->department_name,
            'Catégorie'       => $emp?->category_label,
            'Ancienneté'      => $emp ? $emp->anciennete.' an(s)' : null,
            'Date embauche'   => $emp?->hiring_date?->format('d/m/Y'),
            'N° CNSS'         => $emp?->cnss_number,
            'Sit. familiale'  => $emp?->family_status,
            'Parts fiscales'  => $item->nb_parts.' part(s)',
        ]) as $l=>$v)
        <div class="info-line"><span class="info-label">{{ $l }}</span><span class="info-value">{{ $v }}</span></div>
        @endforeach
    </div>
</div>

{{-- ══ TABLEAU DES RUBRIQUES ══ --}}
<table class="rubriques">
    <thead>
        <tr>
            <th style="width:34%">Libellé de la rubrique</th>
            <th class="r" style="width:13%">Base / Nb</th>
            <th class="r" style="width:11%">Taux</th>
            <th class="r" style="width:18%">Gains (F CFA)</th>
            <th class="r" style="width:18%">Retenues (F CFA)</th>
            <th class="r" style="width:6%">Cumul</th>
        </tr>
    </thead>
    <tbody>

    {{-- Gains --}}
    <tr class="rubrique-section"><td colspan="6">ÉLÉMENTS DE RÉMUNÉRATION</td></tr>

    <tr>
        <td>Salaire de base</td>
        <td class="num">{{ $item->worked_days }}/{{ $item->total_days }} j</td>
        <td class="num" style="color:#6b7280;"></td>
        <td class="num gain">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="num"></td>
        <td></td>
    </tr>

    @if($item->hs_25_hours > 0)
    <tr>
        <td>Heures supplémentaires 25%</td>
        <td class="num">{{ $item->hs_25_hours }} h</td>
        <td class="num" style="color:#6b7280;">× 1,25</td>
        <td class="num gain">{{ number_format($item->hs_25_amount, 0, ',', ' ') }}</td>
        <td class="num"></td>
        <td></td>
    </tr>
    @endif

    @if($item->hs_50_hours > 0)
    <tr>
        <td>Heures supplémentaires 50%</td>
        <td class="num">{{ $item->hs_50_hours }} h</td>
        <td class="num" style="color:#6b7280;">× 1,50</td>
        <td class="num gain">{{ number_format($item->hs_50_amount, 0, ',', ' ') }}</td>
        <td class="num"></td>
        <td></td>
    </tr>
    @endif

    @if($item->hs_nuit_hours > 0)
    <tr>
        <td>Heures de nuit</td>
        <td class="num">{{ $item->hs_nuit_hours }} h</td>
        <td class="num" style="color:#6b7280;">× 1,75</td>
        <td class="num gain">{{ number_format($item->hs_nuit_amount, 0, ',', ' ') }}</td>
        <td class="num"></td>
        <td></td>
    </tr>
    @endif

    @if($item->total_allowances_taxable > 0)
    <tr>
        <td>Primes et indemnités imposables</td>
        <td class="num"></td>
        <td class="num" style="color:#6b7280;">Fixe</td>
        <td class="num gain">{{ number_format($item->total_allowances_taxable, 0, ',', ' ') }}</td>
        <td class="num"></td>
        <td></td>
    </tr>
    @endif

    @if($item->primes_exceptionnelles > 0)
    <tr>
        <td>Primes exceptionnelles du mois</td>
        <td class="num"></td>
        <td class="num" style="color:#6b7280;">Ponctuel</td>
        <td class="num gain">{{ number_format($item->primes_exceptionnelles, 0, ',', ' ') }}</td>
        <td class="num"></td>
        <td></td>
    </tr>
    @endif

    @if($item->absence_days > 0)
    <tr>
        <td>Retenue absences ({{ $item->absence_days }} j.)</td>
        <td class="num">{{ $item->absence_days }} j</td>
        <td></td>
        <td class="num"></td>
        <td class="num retr">{{ number_format($item->absence_amount, 0, ',', ' ') }}</td>
        <td></td>
    </tr>
    @endif

    <tr class="rubrique-total">
        <td colspan="3">SALAIRE BRUT</td>
        <td class="num">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
        <td></td>
        <td class="num" style="font-size:6pt;">{{ number_format($item->cumul_brut_ytd, 0, ',', ' ') }}</td>
    </tr>

    {{-- Cotisations sociales --}}
    <tr class="rubrique-section"><td colspan="6">COTISATIONS SOCIALES</td></tr>

    <tr>
        <td>CNSS salarié (plafond {{ number_format(\App\Services\PayrollService::CNSS_CEILING,0,',',' ') }} F)</td>
        <td class="num">{{ number_format($item->cnss_base, 0, ',', ' ') }}</td>
        <td class="num" style="color:#6b7280;">{{ \App\Services\PayrollService::CNSS_EMPLOYEE_RATE }} %</td>
        <td class="num"></td>
        <td class="num retr">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
        <td class="num" style="color:#9ca3af;font-size:6pt;">{{ number_format($item->cumul_cnss_ytd, 0, ',', ' ') }}</td>
    </tr>

    <tr style="color:#9ca3af;font-size:6.5pt;">
        <td style="padding-left:12px;">dont CNSS patronal (à la charge de l'employeur)</td>
        <td class="num">{{ number_format($item->cnss_base, 0, ',', ' ') }}</td>
        <td class="num">{{ \App\Services\PayrollService::CNSS_EMPLOYER_RATE }} %</td>
        <td class="num">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
        <td></td>
        <td></td>
    </tr>

    {{-- IUTS --}}
    <tr class="rubrique-section"><td colspan="6">IUTS — IMPÔT UNIQUE SUR LES TRAITEMENTS ET SALAIRES</td></tr>

    <tr>
        <td>Salaire net imposable (Brut − CNSS salarié)</td>
        <td class="num">{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
        <td class="num" style="color:#6b7280;">{{ $item->nb_parts }} pt(s)</td>
        <td></td><td></td><td></td>
    </tr>
    <tr>
        <td>IUTS (barème progressif quotient familial)</td>
        <td class="num">{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
        <td class="num" style="color:#6b7280;">≈ {{ $item->taux_moyen_iuts }} %</td>
        <td class="num"></td>
        <td class="num retr">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
        <td class="num" style="color:#9ca3af;font-size:6pt;">{{ number_format($item->cumul_iuts_ytd, 0, ',', ' ') }}</td>
    </tr>

    {{-- Éléments non imposables --}}
    @if($item->total_allowances_non_taxable > 0 || $item->autres_gains > 0)
    <tr class="rubrique-section"><td colspan="6">ÉLÉMENTS NON SOUMIS AUX COTISATIONS</td></tr>
    @if($item->total_allowances_non_taxable > 0)
    <tr>
        <td>Indemnités et primes non imposables</td>
        <td></td><td></td>
        <td class="num gain">{{ number_format($item->total_allowances_non_taxable, 0, ',', ' ') }}</td>
        <td></td><td></td>
    </tr>
    @endif
    @if($item->autres_gains > 0)
    <tr>
        <td>Autres gains non imposables</td>
        <td></td><td></td>
        <td class="num gain">{{ number_format($item->autres_gains, 0, ',', ' ') }}</td>
        <td></td><td></td>
    </tr>
    @endif
    @endif

    {{-- Retenues diverses --}}
    @if($item->avances_deductions > 0 || $item->autres_retenues > 0)
    <tr class="rubrique-section"><td colspan="6">RETENUES DIVERSES</td></tr>
    @if($item->avances_deductions > 0)
    <tr>
        <td>Récupération avance sur salaire</td>
        <td></td><td></td><td></td>
        <td class="num retr">{{ number_format($item->avances_deductions, 0, ',', ' ') }}</td>
        <td></td>
    </tr>
    @endif
    @if($item->autres_retenues > 0)
    <tr>
        <td>Autres retenues</td>
        <td></td><td></td><td></td>
        <td class="num retr">{{ number_format($item->autres_retenues, 0, ',', ' ') }}</td>
        <td></td>
    </tr>
    @endif
    @endif

    {{-- Net à payer --}}
    <tr class="rubrique-net">
        <td colspan="3" style="font-size:10pt; letter-spacing:1px;">NET À PAYER</td>
        <td class="num" style="font-size:12pt; letter-spacing:0;">{{ number_format($item->salaire_net, 0, ',', ' ') }}</td>
        <td></td>
        <td class="num" style="font-size:6pt;">{{ number_format($item->cumul_net_ytd, 0, ',', ' ') }}</td>
    </tr>
    </tbody>
</table>

{{-- ══ CUMULS + SIGNATURE ══ --}}
<div class="footer-grid">
    <div class="footer-left" style="padding-right:10px;">
        <div style="font-size:6.5pt;font-weight:bold;color:#374151;margin-bottom:3px;">CUMULS ANNUELS — EXERCICE {{ $run->period_year }}</div>
        <table class="ytd">
            <thead>
                <tr>
                    <th>Rubrique</th>
                    <th>Ce mois (F)</th>
                    <th>Cumul {{ $run->period_year }} (F)</th>
                </tr>
            </thead>
            <tbody>
            @foreach([
                ['Salaire brut',  $item->salaire_brut,  $item->cumul_brut_ytd],
                ['CNSS salarié',  $item->cnss_employee, $item->cumul_cnss_ytd],
                ['IUTS',          $item->iuts_amount,   $item->cumul_iuts_ytd],
                ['Net à payer',   $item->salaire_net,   $item->cumul_net_ytd],
            ] as [$l,$m,$c])
            <tr>
                <td style="text-align:left;">{{ $l }}</td>
                <td>{{ number_format($m, 0, ',', ' ') }}</td>
                <td style="font-weight:bold;">{{ number_format($c, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>

        <div style="margin-top:5px;font-size:6.5pt;color:#6b7280;">
            Coût total employeur : <strong>{{ number_format($item->salaire_brut + $item->cnss_employer, 0, ',', ' ') }} F CFA</strong>
            (brut {{ number_format($item->salaire_brut,0,',',' ') }} + CNSS pat. {{ number_format($item->cnss_employer,0,',',' ') }})
        </div>

        @if($emp && ($emp->bank_name || $emp->bank_account_number || $emp->bank_account))
        <div style="margin-top:4px;font-size:6.5pt;color:#6b7280;">
            Règlement : <strong>{{ strtoupper($emp->payment_mode ?? 'Virement') }}</strong>
            @if($emp->bank_name) — {{ $emp->bank_name }}@endif
            @if($emp->rib_formate) — RIB : {{ $emp->rib_formate }}@endif
        </div>
        @endif
    </div>

    <div class="footer-right">
        <div class="net-box" style="margin-bottom:6px;">
            <div style="font-size:6.5pt;color:#166534;margin-bottom:2px;">NET À PAYER</div>
            <div class="net-amount">{{ number_format($item->salaire_net, 0, ',', ' ') }} F</div>
        </div>
        <div class="sign-box">
            <div style="margin-bottom:18px;">Signature de l'employeur</div>
            <div>Bon pour reçu — Signature de l'employé</div>
        </div>
        <div class="mention">
            Ce bulletin de paie doit être conservé sans limitation de durée.<br>
            En cas de contestation, saisir l'Inspection du Travail sous 2 mois.
        </div>
    </div>
</div>

</div>
</body>
</html>
