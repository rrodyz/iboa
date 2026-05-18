<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #111827; }
.page { padding: 10mm 8mm; }

/* ── En-tête ── */
.header { background:#92400E; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:7pt; vertical-align:middle; width:20%; }
.subheader { background:#D97706; color:#FEF3C7; padding:3px 12px; display:table; width:100%; font-size:6.5pt; font-style:italic; }

/* ── Synthèse ── */
.summary { background:#FFFBEB; border:1px solid #FCD34D; padding:5px 12px; margin-top:5px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 10px; border-right:1px solid #FDE68A; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:6.5pt; color:#92400E; }
.sum-value { font-size:10pt; font-weight:bold; color:#92400E; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:6.5pt; margin-top:7px; }
thead tr { background:#D97706; color:#fff; }
th { padding:3px 3px; font-size:6pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.3px solid #FDE68A; }
tbody tr:nth-child(even) { background:#FFFBEB; }
td { padding:2.5px 3px; vertical-align:middle; }
tfoot td { padding:4px 3px; border-top:1.5px solid #92400E; font-weight:bold; background:#FFFBEB; color:#92400E; }

.mono { font-family: DejaVu Sans Mono, monospace; font-size:6pt; }

/* ── Badges ── */
.badge { padding:1px 4px; border-radius:3px; font-size:5.5pt; font-weight:bold; }
.b-actif   { background:#DCFCE7; color:#166534; }
.b-inactif { background:#F3F4F6; color:#6B7280; }

/* ── Pied de page ── */
.footer { margin-top:8px; border-top:1px solid #FDE68A; padding-top:3px; font-size:6pt; color:#6B7280; display:table; width:100%; }
.f-left  { display:table-cell; }
.f-right { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

{{-- ── En-tête ── --}}
<div class="header">
    <div class="header-row">
        <div class="h-left">{{ $company?->name }}</div>
        <div class="h-center">LISTE DES FOURNISSEURS</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div>
        Répertoire fournisseurs
        @if(!empty($filters['search'])) &nbsp;|&nbsp; Recherche : {{ $filters['search'] }} @endif
    </div>
</div>

{{-- ── Synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb fournisseurs</div>
        <div class="sum-value">{{ $suppliers->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Actifs</div>
        <div class="sum-value">{{ $suppliers->where('is_active', true)->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Inactifs</div>
        <div class="sum-value">{{ $suppliers->where('is_active', false)->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Solde total dû (FCFA)</div>
        <div class="sum-value">{{ number_format((int)$suppliers->sum('balance'), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Délai moyen (j)</div>
        <div class="sum-value">{{ $suppliers->count() > 0 ? round($suppliers->avg('avg_delivery_days'), 0) : '—' }}</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:7%">Code</th>
            <th class="l" style="width:16%">Nom</th>
            <th class="l" style="width:13%">Email</th>
            <th class="c" style="width:9%">Téléphone</th>
            <th class="l" style="width:8%">Ville</th>
            <th class="l" style="width:6%">Pays</th>
            <th class="c" style="width:7%">IFU</th>
            <th class="c" style="width:5%">Note</th>
            <th class="c" style="width:6%">Délai (j)</th>
            <th class="r" style="width:10%">Solde dû (FCFA)</th>
            <th class="c" style="width:5%">Statut</th>
            <th class="c" style="width:6%">Créé le</th>
        </tr>
    </thead>
    <tbody>
        @forelse($suppliers as $supplier)
        <tr>
            <td class="mono" style="color:#92400E; font-weight:bold">{{ $supplier->code ?? '—' }}</td>
            <td style="font-weight:500">{{ $supplier->name }}</td>
            <td style="color:#374151; font-size:6pt">{{ $supplier->email ?? '—' }}</td>
            <td class="c mono">{{ $supplier->phone ?? '—' }}</td>
            <td>{{ $supplier->city ?? '—' }}</td>
            <td>{{ $supplier->country ?? '—' }}</td>
            <td class="c mono" style="font-size:6pt">{{ $supplier->ifu ?? '—' }}</td>
            <td class="c">{{ $supplier->rating ? $supplier->rating.'/5' : '—' }}</td>
            <td class="c">{{ $supplier->avg_delivery_days ? $supplier->avg_delivery_days.'j' : '—' }}</td>
            <td class="r mono" style="{{ (int)($supplier->balance ?? 0) > 0 ? 'font-weight:bold; color:#92400E' : '' }}">
                {{ (int)($supplier->balance ?? 0) > 0 ? number_format((int)$supplier->balance, 0, ',', ' ') : '—' }}
            </td>
            <td class="c">
                <span class="badge {{ $supplier->is_active ? 'b-actif' : 'b-inactif' }}">
                    {{ $supplier->is_active ? 'Actif' : 'Inactif' }}
                </span>
            </td>
            <td class="c">{{ $supplier->created_at->format('d/m/Y') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="12" style="text-align:center; padding:14px; color:#9CA3AF;">Aucun fournisseur trouvé.</td>
        </tr>
        @endforelse
    </tbody>
    @if($suppliers->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="9" class="l">TOTAL — {{ $suppliers->count() }} fournisseur(s)</td>
            <td class="r mono">{{ number_format((int)$suppliers->sum('balance'), 0, ',', ' ') }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Liste des fournisseurs</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
