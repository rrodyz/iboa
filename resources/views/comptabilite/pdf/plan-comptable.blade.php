<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111827; }
.page { padding: 12mm 10mm; }
.header { background:#312E81; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.hl  { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; }
.hc  { display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.hr  { display:table-cell; text-align:right; font-size:7.5pt; vertical-align:middle; }
.sub { background:#4338CA; color:#e0e7ff; padding:3px 12px; display:table; width:100%; font-size:7.5pt; margin-bottom:8px; }
.sl { display:table-cell; }
.sr { display:table-cell; text-align:right; }
table { width:100%; border-collapse:collapse; font-size:7.5pt; }
thead tr { background:#312E81; color:#fff; }
th { padding:3px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
.class-row td { background:#EEF2FF; font-weight:bold; color:#312E81; font-size:7.5pt; padding:3px 5px; border-top:1px solid #818CF8; }
tbody tr { border-bottom:0.4px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#FAFAF9; }
td { padding:2.5px 5px; }
.mono { font-family: DejaVu Sans Mono, monospace; }
.code { color:#4338CA; font-weight:bold; }
.grp  { color:#9CA3AF; }
.type-actif    { background:#DBEAFE; color:#1E40AF; padding:1px 4px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.type-passif   { background:#EDE9FE; color:#4C1D95; padding:1px 4px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.type-charge   { background:#FEE2E2; color:#991B1B; padding:1px 4px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.type-produit  { background:#DCFCE7; color:#166534; padding:1px 4px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.type-bilan    { background:#F3F4F6; color:#374151; padding:1px 4px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.type-resultat { background:#FEF9C3; color:#854D0E; padding:1px 4px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.footer { margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
.fl { display:table-cell; }
.fr { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-row">
        <div class="hl">{{ $company?->name }}</div>
        <div class="hc">PLAN COMPTABLE SYSCOHADA</div>
        <div class="hr">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="sub">
    <div class="sl">{{ $accounts->count() }} compte(s)</div>
    <div class="sr">{{ $classId ? 'Classe '.$classes->firstWhere('id',$classId)?->number : 'Toutes les classes' }}</div>
</div>

<table>
    <thead>
        <tr>
            <th class="l" style="width:12%">Code</th>
            <th class="l" style="width:42%">Libellé</th>
            <th class="c" style="width:10%">Classe</th>
            <th class="c" style="width:12%">Type</th>
            <th class="r" style="width:12%">Solde débiteur</th>
            <th class="r" style="width:12%">Solde créditeur</th>
        </tr>
    </thead>
    <tbody>
        @php $currentClass = null; @endphp
        @forelse($accounts as $account)
        @php $classNum = substr($account->code, 0, 1); @endphp
        @if($classNum !== $currentClass)
        @php $currentClass = $classNum; @endphp
        <tr class="class-row"><td colspan="6">Classe {{ $classNum }}@if($account->accountClass?->name) — {{ $account->accountClass->name }}@endif</td></tr>
        @endif
        <tr>
            <td class="mono code {{ !$account->is_detail ? 'grp' : '' }}">{{ $account->code }}</td>
            <td class="{{ !$account->is_detail ? 'grp' : '' }}">
                {{ $account->name }}
                @if($account->parent)<span style="color:#9CA3AF; font-size:6.5pt"> ↳ {{ $account->parent->code }}</span>@endif
            </td>
            <td class="c" style="color:#4338CA; font-size:7pt">{{ $account->accountClass?->number }}</td>
            <td class="c">
                @php $t = $account->type; @endphp
                <span class="type-{{ in_array($t, ['actif','passif','charge','produit','bilan','resultat']) ? $t : 'bilan' }}">
                    {{ ucfirst($t) }}
                </span>
            </td>
            <td class="r mono" style="color:#1D4ED8">{{ $account->debit_balance  > 0 ? number_format((int)$account->debit_balance,  0,',',' ') : '—' }}</td>
            <td class="r mono" style="color:#B91C1C">{{ $account->credit_balance > 0 ? number_format((int)$account->credit_balance, 0,',',' ') : '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center; padding:10px; color:#9CA3AF;">Aucun compte.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <div class="fl">{{ $company?->name }} — Plan comptable SYSCOHADA</div>
    <div class="fr">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>
</div>
</body>
</html>
