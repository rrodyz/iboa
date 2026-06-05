<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Contrat de travail — {{ $employee->full_name }}</title>
<style>
/* ─── FORMAT A4 ────────────────────────────────────────────────────────
   A4 = 210 × 297 mm
   Marges : 18mm haut/bas, 20mm gauche/droite
   Zone imprimable : 170 × 261 mm
   DomPDF : utiliser float + clear (pas display:table)
──────────────────────────────────────────────────────────────────────── */
@page {
    margin: 18mm 20mm 20mm 20mm;
    size: A4 portrait;
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 9pt;
    color: #1c1c1c;
    line-height: 1.5;
    width: 100%;
}

/* ─── PIED DE PAGE FIXE ─────────────────────────────────────────────── */
.page-footer {
    position: fixed;
    bottom: -14mm;
    left: 0; right: 0;
    border-top: 1.5px solid #1a3557;
    padding-top: 3pt;
    font-size: 6.5pt;
    color: #94a3b8;
}
.footer-left  { float: left; }
.footer-right { float: right; }
.footer-center { text-align: center; }
.footer-clear  { clear: both; }

/* ─── BARRE SUPÉRIEURE ──────────────────────────────────────────────── */
.top-bar {
    background: #1a3557;
    height: 5pt;
    width: 100%;
    margin-bottom: 8pt;
}

/* ─── EN-TÊTE ───────────────────────────────────────────────────────── */
.header-wrap {
    width: 100%;
    border-bottom: 1pt solid #dde3ea;
    padding-bottom: 8pt;
    margin-bottom: 0;
    overflow: hidden; /* clearfix */
}
.header-logo {
    float: left;
    width: 18%;
}
.header-logo img {
    max-width: 72pt;
    max-height: 48pt;
}
.header-company {
    float: left;
    width: 52%;
    padding-left: 10pt;
    border-left: 2pt solid #c8a84b;
    margin-left: 2pt;
}
.company-name {
    font-size: 12pt;
    font-weight: bold;
    color: #1a3557;
    margin-bottom: 2pt;
}
.company-sub {
    font-size: 7pt;
    color: #64748b;
    line-height: 1.5;
}
.header-ref {
    float: right;
    width: 26%;
    text-align: right;
}
.ref-box {
    display: inline-block;
    border: 1pt solid #1a3557;
    border-radius: 3pt;
    padding: 4pt 8pt;
    text-align: center;
}
.ref-lbl { font-size: 6.5pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.3pt; }
.ref-val { font-size: 8pt; font-weight: bold; color: #1a3557; }

/* ─── TITRE PRINCIPAL ───────────────────────────────────────────────── */
.title-band {
    background: #1a3557;
    color: #fff;
    text-align: center;
    padding: 9pt 0 7pt;
    margin-top: 0;
    margin-bottom: 10pt;
    width: 100%;
}
.title-band h1 {
    font-size: 13pt;
    font-weight: bold;
    letter-spacing: 1.5pt;
    text-transform: uppercase;
    margin-bottom: 3pt;
}
.type-badge {
    display: inline-block;
    background: #c8a84b;
    color: #1a3557;
    font-size: 7.5pt;
    font-weight: bold;
    padding: 2pt 14pt;
    border-radius: 8pt;
    letter-spacing: 0.8pt;
}
.legal-ref-sub {
    font-size: 6.5pt;
    color: rgba(255,255,255,0.6);
    margin-top: 3pt;
}

/* ─── INTRO ─────────────────────────────────────────────────────────── */
.intro-box {
    border: 1pt solid #dde3ea;
    border-left: 3pt solid #1a3557;
    border-radius: 3pt;
    background: #f8fafc;
    padding: 7pt 10pt;
    font-size: 8.5pt;
    color: #334155;
    text-align: justify;
    line-height: 1.6;
    margin-bottom: 10pt;
}

/* ─── BLOCS PARTIES (2 colonnes) ────────────────────────────────────── */
.parties-wrap {
    width: 100%;
    margin-bottom: 10pt;
    overflow: hidden;
}
.partie-col {
    float: left;
    width: 49%;
    border: 1pt solid #dde3ea;
    border-radius: 3pt;
    overflow: hidden;
}
.partie-col.right { float: right; }

.partie-head {
    padding: 4pt 10pt;
    font-size: 7.5pt;
    font-weight: bold;
    letter-spacing: 0.5pt;
    text-transform: uppercase;
    color: #fff;
    background: #1a3557;
}
.partie-head.gold { background: #c8a84b; color: #1a3557; }
.partie-body { padding: 6pt 10pt; }

/* ─── TABLE INFOS ───────────────────────────────────────────────────── */
.tbl {
    width: 100%;
    border-collapse: collapse;
}
.tbl td {
    font-size: 8pt;
    padding: 2pt 0;
    vertical-align: top;
    border-bottom: 0.5pt dotted #dde3ea;
}
.tbl tr:last-child td { border-bottom: none; }
.tbl .lbl {
    width: 42%;
    color: #64748b;
    font-weight: bold;
    padding-right: 6pt;
}
.tbl .val { color: #1c1c1c; }

.clearfix { clear: both; }

/* ─── SÉPARATEUR ────────────────────────────────────────────────────── */
.sep {
    border: none;
    border-top: 0.5pt solid #dde3ea;
    margin: 8pt 0;
    width: 100%;
}
.sep-gold {
    border-top: 0.5pt solid #c8a84b;
    margin: 6pt 0;
}

/* ─── ARTICLES ──────────────────────────────────────────────────────── */
.art {
    margin-bottom: 9pt;
    page-break-inside: avoid;
    width: 100%;
    overflow: hidden;
}
.art-head {
    overflow: hidden;
    margin-bottom: 5pt;
    width: 100%;
}
.art-num {
    float: left;
    background: #1a3557;
    color: #fff;
    font-size: 7pt;
    font-weight: bold;
    padding: 3pt 7pt;
    border-radius: 2pt 0 0 2pt;
    min-width: 30pt;
    text-align: center;
}
.art-label {
    float: left;
    background: #eef2f8;
    color: #1a3557;
    font-size: 8.5pt;
    font-weight: bold;
    padding: 2pt 10pt 3pt;
    border-left: 2pt solid #c8a84b;
    border-radius: 0 2pt 2pt 0;
}
.art-body {
    font-size: 8.5pt;
    color: #334155;
    text-align: justify;
    line-height: 1.6;
}
.art-body p { margin-bottom: 4pt; }

/* ─── BLOC DURÉE ────────────────────────────────────────────────────── */
.duree-wrap {
    background: #f8fafc;
    border: 1pt solid #dde3ea;
    border-radius: 3pt;
    padding: 6pt 0 4pt;
    margin: 5pt 0;
    overflow: hidden;
    width: 100%;
}
.duree-item {
    float: left;
    text-align: center;
    padding: 0 12pt;
    border-right: 0.5pt solid #dde3ea;
    width: 33.33%;
}
.duree-item.no-border { border-right: none; }
.duree-lbl  { font-size: 6.5pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.3pt; }
.duree-val  { font-size: 11pt; font-weight: bold; color: #1a3557; margin: 1pt 0; }
.duree-sub  { font-size: 7pt; color: #64748b; }

/* ─── BLOC SALAIRE ──────────────────────────────────────────────────── */
.salary-wrap {
    border: 1.5pt solid #c8a84b;
    border-radius: 3pt;
    overflow: hidden;
    margin: 5pt 0;
    width: 100%;
}
.salary-main {
    float: left;
    width: 58%;
    padding: 8pt 12pt;
}
.salary-side {
    float: right;
    width: 42%;
    background: #1a3557;
    padding: 8pt 10pt;
    min-height: 50pt;
}
.salary-lbl   { font-size: 7pt; color: #64748b; margin-bottom: 2pt; }
.salary-amt   { font-size: 17pt; font-weight: bold; color: #1a3557; }
.salary-curr  { font-size: 9pt; font-weight: bold; color: #c8a84b; }
.salary-note  { font-size: 7pt; color: #64748b; margin-top: 2pt; }
.salary-detail { font-size: 7pt; color: rgba(255,255,255,0.85); line-height: 1.6; }

/* ─── PÉRIODE ESSAI BADGE ───────────────────────────────────────────── */
.essai-badge {
    display: inline;
    background: #fef3c7;
    border: 0.5pt solid #f59e0b;
    padding: 1pt 6pt;
    font-size: 8pt;
    font-weight: bold;
    color: #92400e;
    border-radius: 2pt;
}

/* ─── OBLIGATIONS 2 COL ─────────────────────────────────────────────── */
.oblig-wrap { overflow: hidden; width: 100%; margin: 5pt 0; }
.oblig-col  { float: left; width: 49%; }
.oblig-col.right { float: right; }
.oblig-title {
    font-size: 7.5pt;
    font-weight: bold;
    color: #1a3557;
    border-bottom: 0.5pt solid #c8a84b;
    padding-bottom: 2pt;
    margin-bottom: 4pt;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
}
.oblig-list {
    padding-left: 12pt;
    font-size: 8pt;
    color: #334155;
}
.oblig-list li { margin-bottom: 2pt; line-height: 1.5; }

/* ─── MENTION LÉGALE ────────────────────────────────────────────────── */
.legal-note {
    text-align: center;
    font-size: 8pt;
    color: #64748b;
    border: 0.5pt solid #dde3ea;
    border-radius: 3pt;
    padding: 6pt;
    margin: 10pt 0 8pt;
    background: #f8fafc;
}

/* ─── SIGNATURES ────────────────────────────────────────────────────── */
.sig-section {
    margin-top: 14pt;
    page-break-inside: avoid;
    width: 100%;
    overflow: hidden;
}
.sig-title-center {
    text-align: center;
    font-size: 7pt;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 1pt;
    margin-bottom: 10pt;
}
.sig-col {
    float: left;
    width: 46%;
    text-align: center;
    padding: 0 10pt;
}
.sig-col.right { float: right; }
.sig-who   { font-size: 8.5pt; font-weight: bold; color: #1a3557; margin-bottom: 2pt; }
.sig-name  { font-size: 8pt; color: #475569; }
.sig-mat   { font-size: 7.5pt; color: #94a3b8; margin-bottom: 6pt; }
.sig-box   {
    border: 1.5pt dashed #cbd5e1;
    border-radius: 4pt;
    height: 56pt;
    margin: 0 auto 4pt;
    background: #fafafa;
    position: relative;
}
.sig-wm {
    position: absolute;
    top: 50%; left: 50%;
    font-size: 6.5pt;
    color: #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 2pt;
    white-space: nowrap;
    /* DomPDF ne supporte pas transform, centrage approximatif */
    margin-top: -6pt;
    margin-left: -40pt;
}
.sig-date    { font-size: 7pt; color: #94a3b8; border-top: 0.5pt solid #e2e8f0; padding-top: 3pt; margin-top: 3pt; }
.sig-mention { font-size: 6.5pt; color: #94a3b8; font-style: italic; }
</style>
</head>
<body>

@php
    use Carbon\Carbon;
    $civilite   = $employee->gender === 'F' ? 'Mme' : 'M.';
    $ee         = $employee->gender === 'F' ? 'ée' : 'é';
    $logoPath   = storage_path('app/public/' . ($company->logo ?? ''));
    $logoExists = $company->logo && file_exists($logoPath);
    $startDate  = Carbon::parse($contract->start_date);
    $endDate    = $contract->end_date ? Carbon::parse($contract->end_date) : null;
    $essaiMois  = match($contract->type) { 'CDI'=>3, 'CDD'=>1, 'STAGE'=>1, default=>1 };
    $essaiFin   = $startDate->copy()->addMonths($essaiMois);
    $typeLabel  = match($contract->type) {
        'CDI'   => 'Contrat à Durée Indéterminée (CDI)',
        'CDD'   => 'Contrat à Durée Déterminée (CDD)',
        'STAGE' => 'Contrat de Stage',
        default => $contract->type,
    };
    $typeShort  = match($contract->type) {
        'CDI'   => 'CONTRAT À DURÉE INDÉTERMINÉE — CDI',
        'CDD'   => 'CONTRAT À DURÉE DÉTERMINÉE — CDD',
        'STAGE' => 'CONTRAT DE STAGE',
        default => strtoupper($contract->type),
    };
    $refCode  = 'CTR-' . $contract->type . '-' . str_pad($contract->id, 4, '0', STR_PAD_LEFT);
    $lastArt  = $contract->type === 'CDI' ? 10 : 9;
    $today    = now()->locale('fr_FR')->isoFormat('D MMMM YYYY');
    $dureeLabel = $endDate ? $startDate->diffInMonths($endDate) . ' mois' : 'Indéterminée';
@endphp

{{-- PIED DE PAGE FIXE --}}
<div class="page-footer">
    <span class="footer-left">{{ $company->name }} &mdash; RCCM {{ $company->rccm }}</span>
    <span class="footer-right">Établi le {{ now()->format('d/m/Y') }}</span>
    <div class="footer-center">Contrat de travail &mdash; {{ $employee->full_name }} &mdash; {{ $contract->type }}</div>
    <div class="footer-clear"></div>
</div>

{{-- ── BARRE BLEUE ────────────────────────────────────────────────────── --}}
<div class="top-bar"></div>

{{-- ── EN-TÊTE ─────────────────────────────────────────────────────────── --}}
<div class="header-wrap">
    <div class="header-logo">
        @if($logoExists)
            <img src="{{ $logoPath }}" alt="{{ $company->name }}">
        @endif
    </div>
    <div class="header-company">
        <div class="company-name">{{ $company->name }}</div>
        <div class="company-sub">
            {{ $company->legal_form }}
            @if($company->share_capital) &mdash; Capital : {{ number_format($company->share_capital,0,',',' ') }} FCFA @endif<br>
            {{ $company->address }}, {{ $company->city }}, Burkina Faso<br>
            RCCM&nbsp;: {{ $company->rccm }} &bull; IFU&nbsp;: {{ $company->ifu }}
            @if($company->phone) &bull; {{ $company->phone }} @endif
        </div>
    </div>
    <div class="header-ref">
        <div class="ref-box">
            <div class="ref-lbl">Référence</div>
            <div class="ref-val">{{ $refCode }}</div>
            <div class="ref-lbl" style="margin-top:3pt;">Date d'émission</div>
            <div class="ref-val">{{ now()->format('d/m/Y') }}</div>
        </div>
    </div>
    <div class="clearfix"></div>
</div>

{{-- ── TITRE ────────────────────────────────────────────────────────────── --}}
<div class="title-band">
    <h1>Contrat de Travail</h1>
    <div style="margin-top:3pt;"><span class="type-badge">{{ $typeShort }}</span></div>
    <div class="legal-ref-sub">Loi n°028-2008/AN du 13 mai 2008 — Code du Travail du Burkina Faso</div>
</div>

{{-- ── INTRO ────────────────────────────────────────────────────────────── --}}
<div class="intro-box">
    Entre les soussignés&nbsp;:
    <strong>{{ $company->name }}</strong>, {{ $company->legal_form }}
    @if($company->share_capital) au capital de {{ number_format($company->share_capital,0,',',' ') }} FCFA, @endif
    immatriculée sous le n°&nbsp;<strong>{{ $company->rccm }}</strong>,
    dont le siège est à <strong>{{ $company->address }}, {{ $company->city }}</strong>,
    ci-après désignée <strong>«&nbsp;l'Employeur&nbsp;»</strong>&nbsp;;
    <br><br>
    <strong>et</strong> {{ $civilite }}&nbsp;<strong>{{ $employee->full_name }}</strong>,
    @if($employee->birth_date)
    n{{ $ee }} le {{ Carbon::parse($employee->birth_date)->format('d/m/Y') }}
    @if($employee->birth_place) à {{ $employee->birth_place }}@endif,
    @endif
    de nationalité {{ $employee->nationality ?? 'Burkinabè' }},
    matricule&nbsp;<strong>{{ $employee->matricule }}</strong>,
    ci-après désign{{ $ee }} <strong>«&nbsp;le Salarié&nbsp;»</strong>&nbsp;;
    <br><br>
    Il est convenu et arrêté les conditions suivantes&nbsp;:
</div>

{{-- ── BLOCS PARTIES ────────────────────────────────────────────────────── --}}
<div class="parties-wrap">
    <div class="partie-col">
        <div class="partie-head">▸ L'Employeur</div>
        <div class="partie-body">
            <table class="tbl">
                <tr><td class="lbl">Raison sociale</td><td class="val">{{ $company->name }}</td></tr>
                <tr><td class="lbl">Forme juridique</td><td class="val">{{ $company->legal_form }}</td></tr>
                <tr><td class="lbl">Siège social</td><td class="val">{{ $company->address }}, {{ $company->city }}</td></tr>
                <tr><td class="lbl">RCCM</td><td class="val">{{ $company->rccm }}</td></tr>
                <tr><td class="lbl">IFU</td><td class="val">{{ $company->ifu }}</td></tr>
                @if($company->nif && $company->nif !== 'À RENSEIGNER')
                <tr><td class="lbl">NIF</td><td class="val">{{ $company->nif }}</td></tr>
                @endif
                @if($company->phone)
                <tr><td class="lbl">Téléphone</td><td class="val">{{ $company->phone }}</td></tr>
                @endif
            </table>
        </div>
    </div>
    <div class="partie-col right">
        <div class="partie-head gold">▸ Le Salarié</div>
        <div class="partie-body">
            <table class="tbl">
                <tr><td class="lbl">Nom &amp; Prénom(s)</td><td class="val" style="font-weight:bold;">{{ $employee->full_name }}</td></tr>
                <tr><td class="lbl">Matricule</td><td class="val">{{ $employee->matricule }}</td></tr>
                @if($employee->birth_date)
                <tr><td class="lbl">Date naissance</td><td class="val">{{ Carbon::parse($employee->birth_date)->format('d/m/Y') }}{{ $employee->birth_place ? ' — '.$employee->birth_place : '' }}</td></tr>
                @endif
                <tr><td class="lbl">Nationalité</td><td class="val">{{ $employee->nationality ?? 'Burkinabè' }}</td></tr>
                @if($employee->cin_number)
                <tr><td class="lbl">CIN / Pièce</td><td class="val">{{ $employee->cin_number }}</td></tr>
                @endif
                @if($employee->cnss_number)
                <tr><td class="lbl">N° CNSS</td><td class="val">{{ $employee->cnss_number }}</td></tr>
                @endif
                @if($employee->address)
                <tr><td class="lbl">Adresse</td><td class="val">{{ $employee->address }}{{ $employee->city ? ', '.$employee->city : '' }}</td></tr>
                @endif
            </table>
        </div>
    </div>
    <div class="clearfix"></div>
</div>

<hr class="sep">

{{-- ═══════════════════════════ ARTICLES ══════════════════════════════ --}}

{{-- ART 1 — NATURE --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;1</span>
        <span class="art-label">Nature du contrat</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <p>
            {{ $company->name }} engage {{ $civilite }}&nbsp;<strong>{{ $employee->full_name }}</strong>
            dans le cadre d'un <strong>{{ $typeLabel }}</strong>,
            @if($contract->type === 'CDI')
                conformément aux articles 52 à 57 du Code du Travail du Burkina Faso.
            @elseif($contract->type === 'CDD')
                conformément aux articles 58 à 62 du Code du Travail du Burkina Faso.
            @else
                conformément aux dispositions du Code du Travail.
            @endif
        </p>
    </div>
</div>

{{-- ART 2 — POSTE --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;2</span>
        <span class="art-label">Poste et lieu de travail</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <table class="tbl" style="margin-bottom:5pt;width:80%;">
            <tr><td class="lbl" style="width:32%;">Fonction</td>
                <td class="val" style="font-weight:bold;font-size:9.5pt;color:#1a3557;">{{ $employee->job_title ?? $employee->fonction ?? '—' }}</td></tr>
            @if($employee->department)
            <tr><td class="lbl">Département</td><td class="val">{{ $employee->department->name }}</td></tr>
            @endif
            @if($employee->category)
            <tr><td class="lbl">Catégorie</td><td class="val">{{ $employee->category }}</td></tr>
            @endif
            <tr><td class="lbl">Lieu de travail</td><td class="val">{{ $company->address }}, {{ $company->city }}</td></tr>
        </table>
        <p>{{ $civilite }} {{ $employee->full_name }} exerce ses fonctions sous l'autorité hiérarchique de sa direction. Il/Elle pourra être amené{{ $ee }} à effectuer toute tâche connexe selon les nécessités du service.</p>
    </div>
</div>

{{-- ART 3 — DURÉE --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;3</span>
        <span class="art-label">Durée et date d'effet</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <div class="duree-wrap">
            <div class="duree-item">
                <div class="duree-lbl">Date d'entrée</div>
                <div class="duree-val">{{ $startDate->format('d/m/Y') }}</div>
                <div class="duree-sub">{{ $startDate->locale('fr')->isoFormat('dddd') }}</div>
            </div>
            @if($endDate)
            <div class="duree-item">
                <div class="duree-lbl">Date d'échéance</div>
                <div class="duree-val">{{ $endDate->format('d/m/Y') }}</div>
                <div class="duree-sub">{{ $endDate->locale('fr')->isoFormat('dddd') }}</div>
            </div>
            <div class="duree-item no-border">
                <div class="duree-lbl">Durée totale</div>
                <div class="duree-val">{{ $startDate->diffInMonths($endDate) }} mois</div>
                <div class="duree-sub">CDD</div>
            </div>
            @else
            <div class="duree-item">
                <div class="duree-lbl">Durée</div>
                <div class="duree-val">Indéterminée</div>
                <div class="duree-sub">CDI</div>
            </div>
            <div class="duree-item no-border">
                <div class="duree-lbl">Ancienneté</div>
                <div class="duree-val">{{ $startDate->diffForHumans(now(), true) }}</div>
                <div class="duree-sub">au {{ now()->format('d/m/Y') }}</div>
            </div>
            @endif
            <div class="clearfix"></div>
        </div>
        <p>
            @if($contract->type === 'CDI')
            Le présent CDI peut être rompu par l'une ou l'autre des parties moyennant le respect du préavis légal (art. 80 CT-BF).
            @else
            Le contrat prend fin de plein droit à l'échéance fixée. La durée maximale est de <strong>2 ans</strong> renouvellements inclus (art. 58 CT-BF).
            @endif
        </p>
    </div>
</div>

{{-- ART 4 — PÉRIODE D'ESSAI --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;4</span>
        <span class="art-label">Période d'essai</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <p>
            Période d'essai de <span class="essai-badge">{{ $essaiMois }} mois</span>
            du <strong>{{ $startDate->format('d/m/Y') }}</strong>
            au <strong>{{ $essaiFin->format('d/m/Y') }}</strong>
            (art. 64 CT-BF). Durant cette période, la rupture est possible sans préavis ni indemnité.
        </p>
    </div>
</div>

{{-- ART 5 — RÉMUNÉRATION --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;5</span>
        <span class="art-label">Rémunération</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <div class="salary-wrap">
            <div class="salary-main">
                <div class="salary-lbl">Salaire de base brut mensuel</div>
                <div>
                    <span class="salary-amt">{{ number_format($contract->base_salary,0,',',' ') }}</span>
                    <span class="salary-curr">&nbsp;FCFA</span>
                </div>
                <div class="salary-note">Versement mensuel — virement / espèces</div>
            </div>
            <div class="salary-side">
                <div class="salary-detail">
                    &bull; CNSS salarié : 5,5 % (plafond 800 000 FCFA/mois)<br>
                    &bull; CNSS patronal : 16 % + 3,5 % AT (à charge employeur)<br>
                    &bull; IUTS : tranches progressives 0 % &#8594; 33 %<br>
                    &bull; Abattement IUTS : 20 % du brut imposable
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
        <p>Ce salaire est supérieur ou égal au SMIG légal (45 000 FCFA/mois — Décret n°2017-0050/PRES/PM/MFPTSS).</p>
    </div>
</div>

{{-- ART 6 — DURÉE DU TRAVAIL --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;6</span>
        <span class="art-label">Durée du travail</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <p>Durée légale&nbsp;: <strong>40 heures / semaine</strong> sur 5 jours ouvrables (art.&nbsp;127 CT-BF). Heures supplémentaires majorées de <strong>+25&nbsp;%</strong> (8 premières h), <strong>+50&nbsp;%</strong> (au-delà), <strong>+75&nbsp;%</strong> (nuit / jours fériés).</p>
    </div>
</div>

{{-- ART 7 — CONGÉS --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;7</span>
        <span class="art-label">Congés annuels</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <p><strong>30 jours ouvrables / an</strong> (2,5 j/mois — art.&nbsp;144 CT-BF). Majoration ancienneté&nbsp;: 2&nbsp;%/an de service, plafonnée à 25&nbsp;% (art.&nbsp;146 CT-BF).</p>
    </div>
</div>

{{-- ART 8 — OBLIGATIONS --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;8</span>
        <span class="art-label">Obligations des parties</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <div class="oblig-wrap">
            <div class="oblig-col">
                <div class="oblig-title">Obligations du salarié</div>
                <ul class="oblig-list">
                    <li>Exécuter son travail avec diligence et loyauté</li>
                    <li>Respecter le règlement intérieur et les consignes</li>
                    <li>Observer la confidentialité des informations</li>
                    <li>Ne pas exercer d'activité concurrente</li>
                    <li>Signaler toute absence en temps utile</li>
                </ul>
            </div>
            <div class="oblig-col right">
                <div class="oblig-title">Obligations de l'employeur</div>
                <ul class="oblig-list">
                    <li>Verser la rémunération convenue à l'échéance</li>
                    <li>Affilier le salarié à la CNSS dès l'embauche</li>
                    <li>Fournir les moyens d'exercice des fonctions</li>
                    <li>Respecter le Code du Travail et les conventions</li>
                    <li>Délivrer le bulletin de paie mensuel</li>
                </ul>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</div>

{{-- ART 9 — RUPTURE (CDI seulement) --}}
@if($contract->type === 'CDI')
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;9</span>
        <span class="art-label">Rupture du contrat</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <p>La rupture est soumise au préavis légal (art.&nbsp;80 CT-BF) variable selon la catégorie et l'ancienneté. La faute lourde dûment constatée autorise la rupture immédiate. Le licenciement économique est soumis aux articles 99 à 105 CT-BF.</p>
    </div>
</div>
@endif

{{-- ART FINAL — DROIT APPLICABLE --}}
<div class="art">
    <div class="art-head">
        <span class="art-num">Art.&nbsp;{{ $lastArt }}</span>
        <span class="art-label">Droit applicable et litiges</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body">
        <p>Droit burkinabè applicable. Tout différend sera soumis à la procédure de conciliation (art.&nbsp;195 CT-BF) puis, à défaut d'accord, au <strong>Tribunal du Travail de Ouagadougou</strong>.</p>
    </div>
</div>

{{-- Notes --}}
@if($contract->notes)
<div class="art">
    <div class="art-head">
        <span class="art-num" style="background:#c8a84b;color:#1a3557;">Note</span>
        <span class="art-label">Clauses particulières</span>
        <div class="clearfix"></div>
    </div>
    <div class="art-body"><p>{{ $contract->notes }}</p></div>
</div>
@endif

{{-- ── MENTION LÉGALE ────────────────────────────────────────────────────── --}}
<div class="legal-note">
    Fait à <strong>{{ $company->city }}</strong>, le <strong>{{ $today }}</strong>,
    en deux (2) exemplaires originaux, dont un exemplaire remis à chaque partie.
</div>

{{-- ── SIGNATURES ────────────────────────────────────────────────────────── --}}
<div class="sig-section">
    <div class="sig-title-center">— Signatures des parties —</div>
    <div class="sig-col">
        <div class="sig-who">Pour l'Employeur</div>
        <div class="sig-name">{{ $company->name }}</div>
        <div class="sig-mat">Le représentant légal</div>
        <div class="sig-box">
            <div class="sig-wm">Cachet &amp; Signature</div>
        </div>
        <div class="sig-date">À {{ $company->city }}, le ..................................</div>
        <div class="sig-mention">Nom, qualité et signature</div>
    </div>
    <div class="sig-col right">
        <div class="sig-who">Le Salarié</div>
        <div class="sig-name">{{ $employee->full_name }}</div>
        <div class="sig-mat">Matricule : {{ $employee->matricule }}</div>
        <div class="sig-box">
            <div class="sig-wm">« Lu et approuvé »</div>
        </div>
        <div class="sig-date">À {{ $company->city }}, le ..................................</div>
        <div class="sig-mention">Précéder de la mention manuscrite « Lu et approuvé »</div>
    </div>
    <div class="clearfix"></div>
</div>

</body>
</html>
