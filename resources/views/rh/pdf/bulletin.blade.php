<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #111; }
.page { padding: 8mm 10mm 6mm 10mm; }

/* ── Tableau principal ─────────────────────────────────────────── */
.mt { width:100%; border-collapse:collapse; margin-bottom:4px; }
.mt th, .mt td { border:1px solid #999; padding:2px 4px; vertical-align:middle; font-size:7pt; }
.th-main { background:#d8d8d8; text-align:center; font-weight:bold; font-size:7pt; }
.th-sal  { background:#e0e0e0; text-align:center; font-weight:bold; font-size:7pt; }
.th-pat  { background:#c5d5ea; text-align:center; font-weight:bold; font-size:7pt; }
.td-pat  { background:#e8f0f8; }
.r   { text-align:right; }
.c   { text-align:center; }
.num { font-family: DejaVu Sans Mono, monospace; }
.code-cell { font-size:6.5pt; color:#444; text-align:center; }
.total-brut td { background:#eeeeee; font-weight:bold; font-size:7pt; }
.total-cot  td { background:#e0e0e0; font-weight:bold; font-size:7pt; }
.empty-row  td { height:9px; font-size:5pt; }

/* ── Cumuls ────────────────────────────────────────────────────── */
.cumuls { width:100%; border-collapse:collapse; font-size:6.5pt; }
.cumuls th { background:#333; color:#fff; padding:2px 3px; text-align:center;
             border:1px solid #333; font-size:6pt; line-height:1.3; }
.cumuls th:first-child { text-align:left; }
.cumuls td { padding:2px 4px; border:1px solid #bbb; text-align:right; font-size:6.5pt; }
.cumuls td:first-child { text-align:left; font-weight:bold; background:#f5f5f5; }

/* ── Info employé ──────────────────────────────────────────────── */
.inf { border-collapse:collapse; width:100%; font-size:7pt; line-height:1.55; }
.inf td { padding:0.5px 0; vertical-align:top; }
.inf .k { font-weight:bold; padding-right:5px; white-space:nowrap; }
</style>
</head>
<body>
<div class="page">

@php
    $emp = $item->employee;

    /* ── Primes actives (chargement forcé) — ANCIENNETE exclue (calculée auto) */
    try {
        $activeAllowances = $emp
            ? $emp->allowances()
                   ->where('is_active', true)
                   ->with('type')
                   ->whereHas('type', fn($q) => $q->where('code', '!=', 'ANCIENNETE'))
                   ->get()
            : collect();
    } catch (\Exception $e) {
        $activeAllowances = collect();
    }

    /* ── Infos générales ─────────────────────────────────────────────────── */
    $logoBase64  = $run->company?->logo_base64;
    $periodDate  = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
    $monthName   = ucfirst($periodDate->translatedFormat('F'));
    $yearLabel   = $run->period_year;

    /* ── Ancienneté — lue depuis le bulletin calculé (PayrollService Art.109) */
    $hiringDate     = $emp?->hiring_date;
    $yearsOfService = $hiringDate ? (int) $hiringDate->diffInYears($periodDate) : 0;
    $monthsRemain   = $hiringDate ? ((int) $hiringDate->diffInMonths($periodDate)) % 12 : 0;
    // Lire les valeurs stockées lors du calcul (cohérence brut ↔ bulletin)
    $ancRate   = (int) ($item->anc_rate   ?? 0);
    $ancAmount = (int) ($item->anc_amount ?? 0);
    // Fallback si bulletin ancien (avant migration) : recalcul à la volée
    if ($ancRate === 0 && $yearsOfService > 0) {
        $ancRate   = min($yearsOfService * 2, 25);
        $ancAmount = (int) round($item->base_salary * $ancRate / 100);
    }

    /* ── Civilité ────────────────────────────────────────────────────────── */
    $civility = ($emp?->gender === 'F') ? 'Mme' : 'M.';

    /* ── CNSS split BF ───────────────────────────────────────────────────── */
    $cnssBase  = $item->cnss_base;
    $rpRatePat = (float) ($payroll->cnss_at_rate ?? 1.5);
    $pfRatePat = 6.0;
    $avRateEmp = (float) $payroll->cnss_employee_rate;          // 5,50
    $avRatePat = (float) $payroll->cnss_employer_rate - $pfRatePat - $rpRatePat; // 8,50
    $avAmtEmp  = $item->cnss_employee;
    $avAmtPat  = (int) round($cnssBase * $avRatePat / 100);
    $pfAmtPat  = (int) round($cnssBase * $pfRatePat / 100);
    $rpAmtPat  = max(0, $item->cnss_employer - $avAmtPat - $pfAmtPat);

    /* ── TPA — Taxe Professionnelle d'Apprentissage (3% patronal/brut total) ─
     * Base légale BF : l'ensemble de la masse salariale brute, y compris les
     * indemnités non-cotisables (transport, spécifique…).                     */
    $tpaRatePat = 3.0;
    $tpaBrutBase = $item->salaire_brut + ($item->total_allowances_non_taxable ?? 0);
    $tpaAmtPat  = (int) round($tpaBrutBase * $tpaRatePat / 100);

    /* ── Effort de paix ─────────────────────────────────────────────────── */
    $effortPaix     = (int) ($item->effort_paix_amount ?? 0);
    $effortPaixRate = (float) ($payroll->effort_paix_rate ?? 1);
    // Base = salaire net avant EP = brut_total (incl. non-imposables) − CNSS − IUTS
    $effortPaixBase = max(0, (int)$item->salaire_brut + (int)($item->total_allowances_non_taxable ?? 0)
                               - (int)$item->cnss_employee - (int)$item->iuts_amount);

    /* ── Totaux cotisations (effort de paix hors Total Cotisations) ──────── */
    $totalCotSal = $item->cnss_employee + $item->iuts_amount;
    $totalCotPat = $item->cnss_employer + $tpaAmtPat;

    /* ── HS & heures ─────────────────────────────────────────────────────── */
    $hsTotal     = round($item->hs_25_hours + $item->hs_50_hours + $item->hs_nuit_hours, 2);
    $workedHours = (int) round($item->worked_days * ($payroll->work_hours_day ?? 8));

    /* ── Codes rubriques ─────────────────────────────────────────────────── */
    $codeMap = [
        'ANCIENNETE' => '1010',
        'LOGEMENT'   => '1200',
        'TRANSP'     => '1210',
        'TRANSPORT'  => '1210',
        'REPAS'      => '1215',
        'FONCTION'   => '1220',
        'RENDEMENT'  => '1230',
        'SPECIFIQUE' => '1250',
        'GRATIF'     => '1300',
    ];
    $dynCode = 1260;
@endphp

{{-- ══ EN-TÊTE ══════════════════════════════════════════════════════════════ --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:6px;">
    <tr>
        {{-- Gauche : logo + infos entreprise --}}
        <td style="width:33%; vertical-align:top; padding-right:8px;">
            @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Logo"
                 style="max-height:75px; max-width:115px; object-fit:contain; display:block; margin-bottom:4px;">
            @endif
            @if($settings?->phone)
            <div style="font-size:7.5pt;"><strong>Tél. : {{ $settings->phone }}</strong></div>
            @endif
            @if($settings?->address)
            <div style="font-size:7pt; margin-top:1px;">{{ $settings->address }}</div>
            @endif
            @if($run->company?->cnss_number)
            <div style="font-size:7pt; margin-top:2px;">
                <strong>N° d'affiliation CNSS : {{ $run->company->cnss_number }}</strong>
            </div>
            @endif
        </td>
        {{-- Droite : BULLETIN DE PAIE + période --}}
        <td style="width:67%; vertical-align:top; text-align:right;">
            <div style="font-size:22pt; font-weight:bold; letter-spacing:4px; color:#111;">BULLETIN&nbsp;&nbsp;DE&nbsp;&nbsp;PAIE</div>
            <div style="font-size:11pt; margin-top:5px;">
                Période : &nbsp;&nbsp; <strong>{{ $monthName }}</strong> &nbsp;&nbsp; <strong>{{ $yearLabel }}</strong>
            </div>
        </td>
    </tr>
</table>

{{-- ══ BLOC EMPLOYÉ ══════════════════════════════════════════════════════════ --}}
<table style="width:100%; border-collapse:collapse; border:1px solid #888; margin-bottom:5px;">
    <tr>
        {{-- Gauche : N° CNSS, ancienneté, nb charge, emploi, service --}}
        <td style="width:52%; padding:5px 8px; border-right:1px solid #888; vertical-align:top;">
            <table class="inf">
                <tr>
                    <td class="k">N° CNSS :</td>
                    <td>{{ $emp?->cnss_number ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="k">Ancienneté</td>
                    <td>{{ $yearsOfService }} an(s) et {{ $monthsRemain }} mois</td>
                </tr>
                <tr>
                    <td class="k">Nb Charge :</td>
                    <td>{{ $emp?->nb_children ?? 0 }}</td>
                </tr>
                <tr>
                    <td class="k">Emploi :</td>
                    <td>{{ strtoupper($item->job_title ?? ($emp?->job_title ?? '')) }}</td>
                </tr>
                <tr>
                    <td class="k">Service :</td>
                    <td>{{ strtoupper($item->department_name ?? '') }}</td>
                </tr>
            </table>
        </td>
        {{-- Droite : Matricule + Nom --}}
        <td style="width:48%; padding:6px 10px; vertical-align:top;">
            <table class="inf" style="margin-bottom:5px;">
                <tr>
                    <td class="k">Matricule :</td>
                    <td style="font-weight:bold;">{{ $item->employee_matricule }}</td>
                </tr>
            </table>
            <div style="font-size:10pt; font-weight:bold; margin-top:4px;">
                {{ $civility }} &nbsp; {{ strtoupper($item->employee_name) }}
            </div>
        </td>
    </tr>
</table>

{{-- ══ TABLEAU PRINCIPAL ══════════════════════════════════════════════════════ --}}
<table class="mt">
    <thead>
        <tr>
            <th class="th-main" rowspan="2" style="width:5%">N°</th>
            <th class="th-main" rowspan="2" style="width:28%; text-align:left; padding-left:5px;">Désignation</th>
            <th class="th-main r" rowspan="2" style="width:7%">Nombre</th>
            <th class="th-main r" rowspan="2" style="width:10%">Base</th>
            <th class="th-sal" colspan="3">Part salariale</th>
            <th class="th-pat" colspan="2">Part patronale</th>
        </tr>
        <tr>
            <th class="th-sal r" style="width:6%">Taux</th>
            <th class="th-sal r" style="width:12%">Gain</th>
            <th class="th-sal r" style="width:12%">Retenue</th>
            <th class="th-pat r" style="width:6%">Taux</th>
            <th class="th-pat r" style="width:14%">Retenue</th>
        </tr>
    </thead>
    <tbody>

    {{-- ── 1000 Salaire de base ── --}}
    <tr>
        <td class="code-cell">1000</td>
        <td>Salaire de base</td>
        <td class="r num">{{ number_format($item->worked_days, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td></td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>

    {{-- ── 1010 Prime d'ancienneté (auto — BF Art. 109 : 2%/an, max 25%) ── --}}
    @if($ancAmount > 0)
    <tr>
        <td class="code-cell">1010</td>
        <td>Prime d'ancienneté</td>
        <td class="r num"></td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format($ancRate, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($ancAmount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Primes & indemnités individuelles ── --}}
    @foreach($activeAllowances as $allowance)
    @php
        $tc       = $allowance->type?->code ?? '';
        $tName    = $allowance->type?->name ?? 'Prime';
        $rubCode  = $codeMap[$tc] ?? sprintf('%04d', $dynCode++);
        $lineBase   = (int) $allowance->amount;
        $lineTaux   = '';
        $lineAmount = (int) $allowance->amount;
        $lineNombre = number_format($item->worked_days, 2, ',', '');
    @endphp
    @if($lineAmount > 0)
    <tr>
        <td class="code-cell">{{ $rubCode }}</td>
        <td>{{ $tName }}</td>
        <td class="r num">{{ $lineNombre }}</td>
        <td class="r num">{{ number_format($lineBase, 0, ',', ' ') }}</td>
        <td class="r num">{{ $lineTaux }}</td>
        <td class="r num">{{ number_format($lineAmount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>
    @endif
    @endforeach

    {{-- ── Heures supplémentaires ── --}}
    @if($item->hs_25_hours > 0)
    <tr>
        <td class="code-cell">1100</td>
        <td>Heures supplémentaires {{ $payroll->hs_rate_25 }} %</td>
        <td class="r num">{{ number_format($item->hs_25_hours, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format(1 + $payroll->hs_rate_25/100, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->hs_25_amount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif
    @if($item->hs_50_hours > 0)
    <tr>
        <td class="code-cell">1101</td>
        <td>Heures supplémentaires {{ $payroll->hs_rate_50 }} %</td>
        <td class="r num">{{ number_format($item->hs_50_hours, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format(1 + $payroll->hs_rate_50/100, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->hs_50_amount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif
    @if($item->hs_nuit_hours > 0)
    <tr>
        <td class="code-cell">1102</td>
        <td>Heures de nuit {{ $payroll->hs_rate_nuit }} %</td>
        <td class="r num">{{ number_format($item->hs_nuit_hours, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format(1 + $payroll->hs_rate_nuit/100, 2, ',', '') }}</td>
        <td class="r num">{{ number_format($item->hs_nuit_amount, 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Absences ── --}}
    @if($item->absence_days > 0)
    <tr>
        <td class="code-cell">1300</td>
        <td>Retenue absences</td>
        <td class="r num">{{ number_format($item->absence_days, 2, ',', '') }}</td>
        <td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->absence_amount, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── TOTAL BRUT ── --}}
    <tr class="total-brut">
        <td colspan="5" style="text-align:center; letter-spacing:1px;">Total Brut</td>
        <td class="r num">{{ number_format($item->salaire_brut + ($item->total_allowances_non_taxable ?? 0), 0, ',', ' ') }}</td>
        <td></td>
        <td class="td-pat" style="background:#d8d8d8;"></td>
        <td class="td-pat" style="background:#d8d8d8;"></td>
    </tr>

    {{-- ── 2000 TPA (Taxe Professionnelle d'Apprentissage) ── --}}
    <tr>
        <td class="code-cell">2000</td>
        <td>TPA</td>
        <td></td>
        <td class="r num">{{ number_format($tpaBrutBase, 0, ',', ' ') }}</td>
        <td class="r num">0,00</td>
        <td class="r num">0</td>
        <td></td>
        <td class="r num td-pat">{{ number_format($tpaRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($tpaAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2010 CNSS Assurance Vieillesse ── --}}
    <tr>
        <td class="code-cell">2010</td>
        <td>CNSS, Assurance Vieillesse</td>
        <td></td>
        <td class="r num">{{ number_format($cnssBase, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format($avRateEmp, 2, ',', '') }}</td>
        <td></td>
        <td class="r num">{{ number_format($avAmtEmp, 0, ',', ' ') }}</td>
        <td class="r num td-pat">{{ number_format($avRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($avAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2011 CNSS Prestations Familiales ── --}}
    <tr>
        <td class="code-cell">2011</td>
        <td>CNSS, Prestations Familiales</td>
        <td></td>
        <td class="r num">{{ number_format($cnssBase, 0, ',', ' ') }}</td>
        <td class="r num">0,00</td>
        <td></td>
        <td class="r num" style="color:#999;">0</td>
        <td class="r num td-pat">{{ number_format($pfRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($pfAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2012 CNSS Risques Professionnels ── --}}
    <tr>
        <td class="code-cell">2012</td>
        <td>CNSS, Risques Professionnels</td>
        <td></td>
        <td class="r num">{{ number_format($cnssBase, 0, ',', ' ') }}</td>
        <td class="r num">0,00</td>
        <td></td>
        <td class="r num" style="color:#999;">0</td>
        <td class="r num td-pat">{{ number_format($rpRatePat, 2, ',', '') }}</td>
        <td class="r num td-pat">{{ number_format($rpAmtPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 2110 Retenue IUTS ── --}}
    <tr>
        <td class="code-cell">2110</td>
        <td>Retenue IUTS</td>
        <td></td>
        <td class="r num">{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
        <td></td>
        <td></td>
        <td class="r num">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
        <td class="td-pat"></td>
        <td class="r num td-pat" style="color:#999;">0</td>
    </tr>

    {{-- ── TOTAL COTISATIONS ── --}}
    <tr class="total-cot">
        <td colspan="5" style="text-align:center; letter-spacing:1px;">Total Cotisations</td>
        <td></td>
        <td class="r num">{{ number_format($totalCotSal, 0, ',', ' ') }}</td>
        <td class="td-pat" style="background:#b0c0d8;"></td>
        <td class="r num td-pat" style="background:#b0c0d8;">{{ number_format($totalCotPat, 0, ',', ' ') }}</td>
    </tr>

    {{-- ── 9000 Effort de paix ── --}}
    @if($effortPaix > 0)
    <tr>
        <td class="code-cell">9000</td>
        <td>Retenue Effort de paix de {{ (int)$effortPaixRate }}%</td>
        <td></td>
        <td class="r num">{{ number_format($effortPaixBase, 0, ',', ' ') }}</td>
        <td class="r num">{{ number_format($effortPaixRate, 2, ',', '') }}</td>
        <td></td>
        <td class="r num">{{ number_format($effortPaix, 0, ',', ' ') }}</td>
        <td class="td-pat"></td>
        <td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Avance sur salaire ── --}}
    @if($item->avances_deductions > 0)
    <tr>
        <td class="code-cell">3100</td>
        <td>Récupération avance sur salaire</td>
        <td></td><td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->avances_deductions, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Remboursement prêt ── --}}
    @if(($item->loan_deductions ?? 0) > 0)
    <tr>
        <td class="code-cell">3200</td>
        <td>Remboursement prêt salarié</td>
        <td></td><td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->loan_deductions, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Autres retenues ── --}}
    @if(($item->autres_retenues ?? 0) > 0)
    <tr>
        <td class="code-cell">3300</td>
        <td>Autres retenues</td>
        <td></td><td></td><td></td><td></td>
        <td class="r num">{{ number_format($item->autres_retenues, 0, ',', ' ') }}</td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endif

    {{-- ── Lignes vides de padding ── --}}
    @php
    $varCount = ($item->hs_25_hours>0?1:0) + ($item->hs_50_hours>0?1:0)
              + ($item->hs_nuit_hours>0?1:0) + ($item->absence_days>0?1:0)
              + ($item->avances_deductions>0?1:0) + (($item->loan_deductions??0)>0?1:0)
              + (($item->autres_retenues??0)>0?1:0);
    $fillerCount = max(0, 4 - $varCount);
    @endphp
    @for($i = 0; $i < $fillerCount; $i++)
    <tr class="empty-row">
        <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
        <td class="td-pat"></td><td class="td-pat"></td>
    </tr>
    @endfor

    </tbody>
</table>

{{-- ══ CUMULS + NET À PAYER ═══════════════════════════════════════════════════ --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:5px;">
    <tr>
        {{-- Tableau des cumuls --}}
        <td style="vertical-align:top; padding-right:4px;">
            <table class="cumuls">
                <thead>
                    <tr>
                        <th style="width:8%; text-align:left;">Cumuls</th>
                        <th style="width:13%">Salaire brut</th>
                        <th style="width:11%">Charges<br>salariales</th>
                        <th style="width:11%">Charges<br>patronales</th>
                        <th style="width:9%">Avantages<br>en nature</th>
                        <th style="width:13%">Net imposable</th>
                        <th style="width:10%">Heures<br>travaillées</th>
                        <th style="width:12%">Heures<br>supplémentaires</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Période</td>
                        <td>{{ number_format($item->salaire_brut + ($item->total_allowances_non_taxable ?? 0), 0, ',', ' ') }}</td>
                        <td>{{ number_format($item->cnss_employee,     0, ',', ' ') }}</td>
                        <td>{{ number_format($totalCotPat,             0, ',', ' ') }}</td>
                        <td>0</td>
                        <td>{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
                        <td>{{ $workedHours }}</td>
                        <td>{{ $hsTotal > 0 ? number_format($hsTotal, 0, ',', ' ') : 0 }}</td>
                    </tr>
                    <tr>
                        <td>Année</td>
                        <td>{{ number_format($item->cumul_brut_ytd  ?? $item->salaire_brut,  0, ',', ' ') }}</td>
                        <td>{{ number_format($item->cumul_cnss_ytd  ?? $item->cnss_employee, 0, ',', ' ') }}</td>
                        <td>{{ number_format($totalCotPat,          0, ',', ' ') }}</td>
                        <td>0</td>
                        <td>{{ number_format($item->salaire_imposable, 0, ',', ' ') }}</td>
                        <td>{{ $workedHours }}</td>
                        <td>0</td>
                    </tr>
                </tbody>
            </table>
        </td>
        {{-- NET À PAYER box --}}
        <td style="width:110px; vertical-align:middle; text-align:center;
                   border:2px solid #1a5c1a; background:#f0fff0; padding:5px 6px;">
            <div style="font-size:6pt; font-weight:bold; color:#1a5c1a;
                        text-transform:uppercase; letter-spacing:.5px; margin-bottom:3px;">
                NET A PAYER
            </div>
            <div style="font-size:12pt; font-weight:bold; color:#111; font-family:DejaVu Sans Mono,monospace; white-space:nowrap;">
                {{ number_format($item->salaire_net, 0, ',', ' ') }}
            </div>
        </td>
    </tr>
</table>

{{-- ══ DATES DE CONGÉS + CACHET ═══════════════════════════════════════════════ --}}
<table style="width:100%; border-collapse:collapse; margin-top:3px;">
    <tr>
        <td style="width:55%; vertical-align:top; padding-right:10px;">
            <div style="font-size:7pt; font-weight:bold; margin-bottom:4px;">Dates de congés</div>
            <table style="font-size:7pt; border-collapse:collapse;">
                @for($r = 0; $r < 3; $r++)
                <tr>
                    <td style="padding:2px 6px 2px 0; font-weight:bold;">Du</td>
                    <td style="border-bottom:1px solid #888; min-width:72px; padding:2px 4px;">&nbsp;</td>
                    <td style="padding:2px 10px; font-weight:bold;">Au</td>
                    <td style="border-bottom:1px solid #888; min-width:72px; padding:2px 4px;">&nbsp;</td>
                </tr>
                @endfor
            </table>
        </td>
        <td style="width:45%; vertical-align:bottom; padding-top:6px;">
            <div style="font-size:8pt; font-weight:bold;">Cachet de l'employeur</div>
            @if($emp && ($emp->bank_name || $emp->bank_account_number))
            <div style="font-size:6.5pt; color:#555; margin-top:3px;">
                Règlement : <strong>{{ strtoupper($emp->payment_mode ?? 'VIREMENT') }}</strong>
                @if($emp->bank_name) &mdash; {{ $emp->bank_name }}@endif
            </div>
            @endif
        </td>
    </tr>
</table>

</div>
</body>
</html>
