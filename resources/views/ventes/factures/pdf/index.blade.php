<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
.page { padding: 12mm 10mm; }

/* ── En-tête ── */
.header { background:#312E81; color:#fff; padding:8px 14px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:11pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:8pt; vertical-align:middle; width:20%; }

.subheader { background:#4338CA; color:#C7D2FE; padding:3px 14px; display:table; width:100%; font-size:7.5pt; font-style:italic; }
.s-left  { display:table-cell; }
.s-right { display:table-cell; text-align:right; }

/* ── Barre de synthèse ── */
.summary { background:#EEF2FF; border:1px solid #A5B4FC; padding:6px 14px; margin-top:6px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 12px; border-right:1px solid #C7D2FE; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:7pt; color:#3730A3; }
.sum-value { font-size:11pt; font-weight:bold; color:#312E81; }
.sum-value.dim { color:#4338CA; font-size:9pt; }
.sum-value.danger { color:#B91C1C; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:8px; }
thead tr { background:#4338CA; color:#fff; }
th { padding:3.5px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #E0E7FF; }
tbody tr:nth-child(even) { background:#F5F3FF; }
td { padding:3px 5px; vertical-align:middle; }
tfoot td { padding:4px 5px; border-top:1.5px solid #312E81; font-weight:bold; background:#EEF2FF; color:#312E81; }

.mono { font-family: DejaVu Sans Mono, monospace; }

/* ── Badges statut ── */
.badge { padding:1px 5px; border-radius:3px; font-size:6.5pt; font-weight:bold; }
.b-brouillon           { background:#F3F4F6; color:#374151; }
.b-emise               { background:#DBEAFE; color:#1E40AF; }
.b-envoyee             { background:#E0E7FF; color:#3730A3; }
.b-partiellement_payee { background:#FED7AA; color:#92400E; }
.b-payee               { background:#D1FAE5; color:#065F46; }
.b-en_retard           { background:#FEE2E2; color:#991B1B; }
.b-annulee             { background:#F3F4F6; color:#6B7280; }

/* ── Ligne en retard ── */
.overdue { background:#FFF5F5 !important; }

/* ── Pied de page ── */
.footer { margin-top:10px; border-top:1px solid #C7D2FE; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
.f-left  { display:table-cell; }
.f-right { display:table-cell; text-align:right; }
</style>
</head>
<body>
<div class="page">

{{-- ── En-tête ── --}}
<div class="header">
    <div class="header-row">
        <div class="h-left">{{ $company?->name }}</div>
        <div class="h-center">LISTE DES FACTURES</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="s-left">
        Factures de vente
        @if(!empty($filters['client_id'])) &nbsp;|&nbsp; Client filtré @endif
        @if(!empty($filters['status']))    &nbsp;|&nbsp; Statut : {{ $statusLabels[$filters['status']] ?? $filters['status'] }} @endif
        @if(!empty($filters['overdue']))   &nbsp;|&nbsp; En retard seulement @endif
        @if(!empty($filters['search']))    &nbsp;|&nbsp; Recherche : {{ $filters['search'] }} @endif
    </div>
    <div class="s-right">
        Période :
        {{ !empty($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') : '—' }}
        →
        {{ !empty($filters['date_to'])   ? \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y')   : "aujourd'hui" }}
    </div>
</div>

{{-- ── Barre de synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb factures</div>
        <div class="sum-value">{{ $invoices->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Total TTC (FCFA)</div>
        <div class="sum-value">{{ number_format($totalTtc, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Total HT (FCFA)</div>
        <div class="sum-value dim">{{ number_format($totalHt, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Total TVA (FCFA)</div>
        <div class="sum-value dim">{{ number_format($totalTva, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Reste à payer (FCFA)</div>
        <div class="sum-value {{ $totalRemaining > 0 ? 'danger' : 'dim' }}">
            {{ number_format($totalRemaining, 0, ',', ' ') }}
        </div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">En retard</div>
        <div class="sum-value {{ $countOverdue > 0 ? 'danger' : 'dim' }}">{{ $countOverdue }}</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l"  style="width:12%">Numéro</th>
            <th class="l"  style="width:20%">Client</th>
            <th class="c"  style="width:9%">Émission</th>
            <th class="c"  style="width:9%">Échéance</th>
            <th class="r"  style="width:12%">Total HT (FCFA)</th>
            <th class="r"  style="width:12%">Total TTC (FCFA)</th>
            <th class="r"  style="width:12%">Reste dû (FCFA)</th>
            <th class="c"  style="width:10%">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoices as $invoice)
        @php
            $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                && !in_array($invoice->status, ['payee', 'annulee']);
        @endphp
        <tr {{ $isOverdue ? 'class="overdue"' : '' }}>
            <td class="mono" style="color:#3730A3; font-weight:bold; font-size:7.5pt">{{ $invoice->number }}</td>
            <td style="font-weight:500">{{ $invoice->client?->trade_name ?? $invoice->client?->name ?? '—' }}</td>
            <td class="c">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
            <td class="c" style="{{ $isOverdue ? 'color:#B91C1C; font-weight:bold;' : '' }}">
                {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                @if($isOverdue) <br/><span style="font-size:6pt">(RETARD)</span> @endif
            </td>
            <td class="r mono">{{ number_format((int)$invoice->subtotal_ht,  0, ',', ' ') }}</td>
            <td class="r mono" style="font-weight:bold; color:#312E81">{{ number_format((int)$invoice->total_ttc, 0, ',', ' ') }}</td>
            <td class="r mono" style="{{ $invoice->remaining_amount > 0 ? 'color:#B91C1C; font-weight:bold;' : 'color:#9CA3AF;' }}">
                {{ $invoice->remaining_amount > 0 ? number_format((int)$invoice->remaining_amount, 0, ',', ' ') : '—' }}
            </td>
            <td class="c">
                <span class="badge b-{{ $invoice->status }}">{{ $statusLabels[$invoice->status] ?? $invoice->status }}</span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align:center; padding:16px; color:#9CA3AF;">
                Aucune facture trouvée pour les filtres sélectionnés.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($invoices->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="4" class="l">TOTAL — {{ $invoices->count() }} facture(s)</td>
            <td class="r mono">{{ number_format($totalHt,        0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format($totalTtc,       0, ',', ' ') }}</td>
            <td class="r mono" style="{{ $totalRemaining > 0 ? 'color:#B91C1C' : '' }}">
                {{ number_format($totalRemaining, 0, ',', ' ') }}
            </td>
            <td></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Liste des factures</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
