<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #111827; }
.page { padding: 10mm 8mm; }

/* ── En-tête ── */
.header { background:#1E3A8A; color:#fff; padding:7px 12px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:10pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:10pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:7pt; vertical-align:middle; width:20%; }
.subheader { background:#2563EB; color:#DBEAFE; padding:3px 12px; display:table; width:100%; font-size:6.5pt; font-style:italic; }

/* ── Synthèse ── */
.summary { background:#EFF6FF; border:1px solid #BFDBFE; padding:5px 12px; margin-top:5px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 10px; border-right:1px solid #BFDBFE; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:6.5pt; color:#1E3A8A; }
.sum-value { font-size:10pt; font-weight:bold; color:#1E3A8A; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:6.5pt; margin-top:7px; }
thead tr { background:#2563EB; color:#fff; }
th { padding:3px 3px; font-size:6pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.3px solid #DBEAFE; }
tbody tr:nth-child(even) { background:#EFF6FF; }
td { padding:2.5px 3px; vertical-align:middle; }
tfoot td { padding:4px 3px; border-top:1.5px solid #1E3A8A; font-weight:bold; background:#EFF6FF; color:#1E3A8A; }

.mono { font-family: DejaVu Sans Mono, monospace; font-size:6pt; }

/* ── Badges ── */
.badge { padding:1px 4px; border-radius:3px; font-size:5.5pt; font-weight:bold; }
.b-entreprise { background:#DBEAFE; color:#1E40AF; }
.b-particulier { background:#F3F4F6; color:#374151; }
.b-actif      { background:#DCFCE7; color:#166534; }
.b-inactif    { background:#F3F4F6; color:#6B7280; }

/* ── Pied de page ── */
.footer { margin-top:8px; border-top:1px solid #BFDBFE; padding-top:3px; font-size:6pt; color:#6B7280; display:table; width:100%; }
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
        <div class="h-center">LISTE DES CLIENTS</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div>
        Répertoire clients
        @if(!empty($filters['search'])) &nbsp;|&nbsp; Recherche : {{ $filters['search'] }} @endif
        @if(isset($filters['type'])) &nbsp;|&nbsp; Type : {{ ucfirst($filters['type']) }} @endif
    </div>
</div>

{{-- ── Synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb clients</div>
        <div class="sum-value">{{ $clients->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Entreprises</div>
        <div class="sum-value">{{ $clients->where('type', 'entreprise')->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Particuliers</div>
        <div class="sum-value">{{ $clients->where('type', 'particulier')->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Solde total dû (FCFA)</div>
        <div class="sum-value">{{ number_format((int)$clients->sum('balance'), 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Actifs</div>
        <div class="sum-value">{{ $clients->where('is_active', true)->count() }}</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:6%">Code</th>
            <th class="c" style="width:6%">Type</th>
            <th class="l" style="width:14%">Nom</th>
            <th class="l" style="width:12%">Nom commercial</th>
            <th class="l" style="width:12%">Email</th>
            <th class="c" style="width:8%">Téléphone</th>
            <th class="l" style="width:10%">Ville</th>
            <th class="r" style="width:9%">Limite crédit</th>
            <th class="c" style="width:5%">Délai (j)</th>
            <th class="r" style="width:9%">Solde dû</th>
            <th class="c" style="width:5%">Statut</th>
            <th class="c" style="width:6%">Créé le</th>
        </tr>
    </thead>
    <tbody>
        @forelse($clients as $client)
        <tr>
            <td class="mono" style="color:#1E3A8A; font-weight:bold">{{ $client->code }}</td>
            <td class="c">
                <span class="badge {{ $client->type === 'entreprise' ? 'b-entreprise' : 'b-particulier' }}">
                    {{ $client->type === 'entreprise' ? 'Ent.' : 'Part.' }}
                </span>
            </td>
            <td style="font-weight:500">{{ $client->name }}</td>
            <td style="color:#374151">{{ $client->trade_name ?? '—' }}</td>
            <td style="color:#374151; font-size:6pt">{{ $client->email ?? '—' }}</td>
            <td class="c mono">{{ $client->phone ?? '—' }}</td>
            <td>{{ $client->city ?? '—' }}</td>
            <td class="r mono">{{ (int)$client->credit_limit > 0 ? number_format((int)$client->credit_limit, 0, ',', ' ') : '—' }}</td>
            <td class="c">{{ (int)$client->payment_days > 0 ? $client->payment_days.'j' : '—' }}</td>
            <td class="r mono" style="{{ (int)$client->balance > 0 ? 'font-weight:bold; color:#1E3A8A' : '' }}">
                {{ (int)$client->balance > 0 ? number_format((int)$client->balance, 0, ',', ' ') : '—' }}
            </td>
            <td class="c">
                <span class="badge {{ $client->is_active ? 'b-actif' : 'b-inactif' }}">
                    {{ $client->is_active ? 'Actif' : 'Inactif' }}
                </span>
            </td>
            <td class="c">{{ $client->created_at->format('d/m/Y') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="12" style="text-align:center; padding:14px; color:#9CA3AF;">Aucun client trouvé.</td>
        </tr>
        @endforelse
    </tbody>
    @if($clients->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="9" class="l">TOTAL — {{ $clients->count() }} client(s)</td>
            <td class="r mono">{{ number_format((int)$clients->sum('balance'), 0, ',', ' ') }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Liste des clients</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
