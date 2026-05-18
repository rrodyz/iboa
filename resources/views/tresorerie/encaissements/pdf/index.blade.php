<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111827; }
.page { padding: 12mm 10mm; }

/* ── En-tête ── */
.header { background:#065F46; color:#fff; padding:8px 14px; }
.header-row { display:table; width:100%; }
.h-left   { display:table-cell; font-size:11pt; font-weight:bold; vertical-align:middle; width:35%; }
.h-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; vertical-align:middle; }
.h-right  { display:table-cell; text-align:right; font-size:8pt; vertical-align:middle; width:20%; }

.subheader { background:#059669; color:#D1FAE5; padding:3px 14px; display:table; width:100%; font-size:7.5pt; font-style:italic; }
.s-left  { display:table-cell; }
.s-right { display:table-cell; text-align:right; }

/* ── Barre de synthèse ── */
.summary { background:#ECFDF5; border:1px solid #6EE7B7; padding:6px 14px; margin-top:6px; display:table; width:100%; }
.sum-cell { display:table-cell; text-align:center; padding:0 12px; border-right:1px solid #A7F3D0; }
.sum-cell:last-child { border-right:none; }
.sum-label { font-size:7pt; color:#065F46; }
.sum-value { font-size:11pt; font-weight:bold; color:#065F46; }
.sum-value.dim { color:#047857; font-size:9pt; }

/* ── Tableau ── */
table { width:100%; border-collapse:collapse; font-size:7.5pt; margin-top:8px; }
thead tr { background:#059669; color:#fff; }
th { padding:3.5px 5px; font-size:7pt; font-weight:bold; }
th.l, td.l { text-align:left; }
th.r, td.r { text-align:right; }
th.c, td.c { text-align:center; }
tbody tr { border-bottom:0.4px solid #D1FAE5; }
tbody tr:nth-child(even) { background:#F0FDF4; }
td { padding:3px 5px; vertical-align:middle; }
tfoot td { padding:4px 5px; border-top:1.5px solid #065F46; font-weight:bold; background:#ECFDF5; color:#065F46; }

.mono { font-family: DejaVu Sans Mono, monospace; }

/* ── Badges statut ── */
.badge { padding:1px 5px; border-radius:3px; font-size:7pt; font-weight:bold; }
.b-confirme   { background:#DCFCE7; color:#166534; }
.b-en_attente { background:#FEF9C3; color:#854D0E; }
.b-rejete     { background:#FEE2E2; color:#991B1B; }
.b-annule     { background:#F3F4F6; color:#6B7280; }

/* ── Badges mode paiement ── */
.pm-especes      { background:#F3F4F6; color:#374151; }
.pm-virement     { background:#DBEAFE; color:#1E40AF; }
.pm-cheque       { background:#EDE9FE; color:#4C1D95; }
.pm-mobile_money { background:#F5D0FE; color:#6B21A8; }
.pm-default      { background:#F3F4F6; color:#374151; }

/* ── Pied de page ── */
.footer { margin-top:10px; border-top:1px solid #D1FAE5; padding-top:4px; font-size:7pt; color:#6B7280; display:table; width:100%; }
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
        <div class="h-center">ENCAISSEMENTS CLIENTS</div>
        <div class="h-right">Édition du {{ now()->format('d/m/Y à H:i') }}</div>
    </div>
</div>
<div class="subheader">
    <div class="s-left">
        Règlements reçus — écritures de trésorerie
        @if(!empty($filters['client_id'])) &nbsp;|&nbsp; Client filtré @endif
        @if(!empty($filters['payment_method_id'])) &nbsp;|&nbsp; Mode filtré @endif
    </div>
    <div class="s-right">
        Période :
        {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '—' }}
        →
        {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : "aujourd'hui" }}
    </div>
</div>

{{-- ── Barre de synthèse ── --}}
<div class="summary">
    <div class="sum-cell">
        <div class="sum-label">Nb encaissements</div>
        <div class="sum-value">{{ $payments->count() }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Total encaissé (FCFA)</div>
        <div class="sum-value">{{ number_format($totalAmount, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Total alloué (FCFA)</div>
        <div class="sum-value dim">{{ number_format($totalAllocated, 0, ',', ' ') }}</div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Non alloué (FCFA)</div>
        <div class="sum-value {{ $totalUnallocated > 0 ? '' : 'dim' }}" style="{{ $totalUnallocated > 0 ? 'color:#B45309' : '' }}">
            {{ number_format($totalUnallocated, 0, ',', ' ') }}
        </div>
    </div>
    <div class="sum-cell">
        <div class="sum-label">Factures imputées</div>
        <div class="sum-value dim">{{ $payments->sum(fn($p) => $p->allocations->count()) }}</div>
    </div>
</div>

{{-- ── Tableau ── --}}
<table>
    <thead>
        <tr>
            <th class="l" style="width:12%">N° Paiement</th>
            <th class="l" style="width:20%">Client</th>
            <th class="c" style="width:9%">Date</th>
            <th class="c" style="width:13%">Mode paiement</th>
            <th class="l" style="width:14%">Compte</th>
            <th class="r" style="width:11%">Montant (FCFA)</th>
            <th class="r" style="width:11%">Alloué (FCFA)</th>
            <th class="r" style="width:6%">Imputé</th>
            <th class="c" style="width:4%">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($payments as $payment)
        @php
            $pmType  = $payment->paymentMethod?->type ?? 'default';
            $pmClass = 'pm-'.($pmType === 'mobile_money' ? 'mobile_money' : (in_array($pmType, ['especes','virement','cheque']) ? $pmType : 'default'));
        @endphp
        <tr>
            <td class="mono" style="color:#065F46; font-weight:bold; font-size:7.5pt">{{ $payment->number }}</td>
            <td style="font-weight:500">{{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}</td>
            <td class="c">{{ $payment->payment_date?->format('d/m/Y') }}</td>
            <td class="c">
                @if($payment->paymentMethod)
                <span class="badge {{ $pmClass }}">{{ $payment->paymentMethod->name }}</span>
                @else<span style="color:#9CA3AF">—</span>@endif
            </td>
            <td style="font-size:7pt; color:#374151">{{ $payment->cashAccount?->name ?? '—' }}</td>
            <td class="r mono" style="font-weight:bold; color:#065F46">{{ number_format((int)$payment->amount, 0, ',', ' ') }}</td>
            <td class="r mono" style="color:#374151">{{ (int)$payment->allocated_amount > 0 ? number_format((int)$payment->allocated_amount, 0, ',', ' ') : '—' }}</td>
            <td class="c">
                @if($payment->allocations->count() > 0)
                <span style="font-size:7pt; font-weight:600; color:#166534">{{ $payment->allocations->count() }} fct</span>
                @else<span style="font-size:7pt; color:#9CA3AF">—</span>@endif
            </td>
            <td class="c">
                @php
                    $bc = match($payment->status) {
                        'confirme'   => 'confirme',
                        'en_attente' => 'en_attente',
                        'rejete'     => 'rejete',
                        'annule'     => 'annule',
                        default      => 'annule',
                    };
                    $bl = match($payment->status) {
                        'confirme'   => 'Confirmé',
                        'en_attente' => 'En attente',
                        'rejete'     => 'Rejeté',
                        'annule'     => 'Annulé',
                        default      => $payment->status,
                    };
                @endphp
                <span class="badge b-{{ $bc }}">{{ $bl }}</span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9" style="text-align:center; padding:16px; color:#9CA3AF;">
                Aucun encaissement trouvé pour les filtres sélectionnés.
            </td>
        </tr>
        @endforelse
    </tbody>
    @if($payments->isNotEmpty())
    <tfoot>
        <tr>
            <td colspan="5" class="l">TOTAL — {{ $payments->count() }} encaissement(s)</td>
            <td class="r mono">{{ number_format((int)$totalAmount, 0, ',', ' ') }}</td>
            <td class="r mono">{{ number_format((int)$totalAllocated, 0, ',', ' ') }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- ── Pied de page ── --}}
<div class="footer">
    <div class="f-left">{{ $company?->name }} — Encaissements clients</div>
    <div class="f-right">Document généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

</div>
</body>
</html>
