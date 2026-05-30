<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }

.page { padding: 10mm 12mm; max-width: 148mm; margin: 0 auto; }

/* ── En-tête société ── */
.header { display: table; width: 100%; margin-bottom: 6mm; }
.h-logo { display: table-cell; vertical-align: top; width: 55%; }
.h-logo .company-name { font-size: 13pt; font-weight: bold; color: #065F46; }
.h-logo .company-sub  { font-size: 8pt; color: #6B7280; margin-top: 2px; }
.h-right { display: table-cell; vertical-align: top; text-align: right; font-size: 8pt; color: #374151; }

/* ── Titre reçu ── */
.receipt-title {
    background: #065F46;
    color: #fff;
    text-align: center;
    padding: 5px 0;
    font-size: 13pt;
    font-weight: bold;
    letter-spacing: 1px;
    margin-bottom: 5mm;
    border-radius: 3px;
}
.receipt-subtitle {
    text-align: center;
    font-size: 9pt;
    color: #6B7280;
    margin-bottom: 5mm;
}

/* ── Bloc principal ── */
.info-grid { display: table; width: 100%; margin-bottom: 5mm; }
.info-col  { display: table-cell; width: 50%; vertical-align: top; padding-right: 5mm; }
.info-col:last-child { padding-right: 0; padding-left: 5mm; }
.info-label { font-size: 7.5pt; color: #6B7280; margin-bottom: 1px; }
.info-value { font-size: 9pt; font-weight: bold; color: #111827; }

/* ── Montant principal ── */
.amount-block {
    background: #ECFDF5;
    border: 2px solid #34D399;
    border-radius: 5px;
    text-align: center;
    padding: 6mm 4mm;
    margin-bottom: 5mm;
}
.amount-label { font-size: 8pt; color: #065F46; margin-bottom: 2px; }
.amount-value { font-size: 22pt; font-weight: bold; color: #065F46; }
.amount-letters { font-size: 8pt; color: #047857; font-style: italic; margin-top: 2px; }

/* ── Mode de paiement ── */
.pm-block { text-align: center; margin-bottom: 5mm; }
.pm-badge {
    display: inline-block; padding: 3px 10px; border-radius: 20px;
    font-size: 9pt; font-weight: bold;
}
.pm-especes      { background: #F3F4F6; color: #374151; }
.pm-virement     { background: #DBEAFE; color: #1E40AF; }
.pm-cheque       { background: #EDE9FE; color: #4C1D95; }
.pm-mobile_money { background: #F5D0FE; color: #6B21A8; }
.pm-default      { background: #E5E7EB; color: #374151; }

/* ── Tableau des factures lettrées ── */
.alloc-title { font-size: 8.5pt; font-weight: bold; color: #065F46; margin-bottom: 2mm; border-bottom: 1px solid #D1FAE5; padding-bottom: 1mm; }
table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 5mm; }
thead tr { background: #D1FAE5; }
th { padding: 3px 5px; font-weight: bold; font-size: 7.5pt; text-align: left; }
th.r { text-align: right; }
tbody tr { border-bottom: 0.5px solid #D1FAE5; }
td { padding: 2.5px 5px; }
td.r { text-align: right; font-variant-numeric: tabular-nums; }
tfoot td { font-weight: bold; padding: 3px 5px; border-top: 1px solid #065F46; }
tfoot td.r { text-align: right; }

/* ── Solde non imputé ── */
.unalloc { background: #FEF9C3; border: 1px solid #FCD34D; border-radius: 3px; padding: 3mm 4mm; font-size: 8pt; margin-bottom: 5mm; color: #92400E; }

/* ── Signature ── */
.sig-block { display: table; width: 100%; margin-top: 6mm; }
.sig-cell  { display: table-cell; width: 50%; }
.sig-label { font-size: 7.5pt; color: #6B7280; margin-bottom: 8mm; }
.sig-line  { border-top: 1px solid #9CA3AF; width: 80%; }

/* ── Pied de page ── */
.footer { border-top: 1px solid #D1FAE5; margin-top: 5mm; padding-top: 2mm; font-size: 7pt; color: #9CA3AF; text-align: center; }

.acompte-tag { background: #FEF3C7; color: #92400E; font-size: 8pt; font-weight: bold; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-bottom: 3mm; }
</style>
</head>
<body>
<div class="page">

    {{-- En-tête société --}}
    <div class="header">
        <div class="h-logo">
            <div class="company-name">{{ $company?->name ?? 'ERP' }}</div>
            @if($company?->address)
            <div class="company-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}</div>
            @endif
            @if($company?->phone)
            <div class="company-sub">Tél : {{ $company->phone }}</div>
            @endif
            @if($company?->ifu)
            <div class="company-sub">IFU : {{ $company->ifu }}</div>
            @endif
        </div>
        <div class="h-right">
            <div style="font-weight:bold; font-size:10pt; color:#065F46;">N° {{ $payment->number }}</div>
            <div>Le {{ $payment->payment_date?->format('d/m/Y') }}</div>
            <div style="margin-top:3px; color:#9CA3AF;">Édité le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    {{-- Titre --}}
    <div class="receipt-title">
        {{ $payment->is_acompte ? 'REÇU D\'ACOMPTE' : 'REÇU DE PAIEMENT' }}
    </div>

    @if($payment->is_acompte)
    <div style="text-align:center; margin-bottom:3mm;">
        <span class="acompte-tag">Avance / Acompte client</span>
    </div>
    @endif

    {{-- Infos client + paiement --}}
    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Reçu de :</div>
            <div class="info-value">{{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}</div>
            @if($payment->client?->phone)
            <div style="font-size:8pt; color:#6B7280; margin-top:1px;">{{ $payment->client->phone }}</div>
            @endif
            @if($payment->client?->address)
            <div style="font-size:8pt; color:#6B7280;">{{ $payment->client->address }}</div>
            @endif
        </div>
        <div class="info-col">
            <div class="info-label">Date du paiement :</div>
            <div class="info-value">{{ $payment->payment_date?->format('d/m/Y') }}</div>
            @if($payment->reference)
            <div class="info-label" style="margin-top:3px;">Référence :</div>
            <div class="info-value">{{ $payment->reference }}</div>
            @endif
            @if($payment->cashAccount)
            <div class="info-label" style="margin-top:3px;">Compte :</div>
            <div class="info-value">{{ $payment->cashAccount->name }}</div>
            @endif
        </div>
    </div>

    {{-- Montant --}}
    <div class="amount-block">
        <div class="amount-label">MONTANT REÇU</div>
        <div class="amount-value">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</div>
        @if($amountInWords)
        <div class="amount-letters">{{ $amountInWords }}</div>
        @endif
    </div>

    {{-- Mode de paiement --}}
    <div class="pm-block">
        @if($payment->paymentMethod)
        @php $pmClass = match($payment->paymentMethod->type ?? '') {
            'especes'      => 'pm-especes',
            'virement'     => 'pm-virement',
            'cheque'       => 'pm-cheque',
            'mobile_money' => 'pm-mobile_money',
            default        => 'pm-default',
        }; @endphp
        Payé par : <span class="pm-badge {{ $pmClass }}">{{ $payment->paymentMethod->name }}</span>
        @if($payment->phone_number)
        <span style="font-size:8pt; color:#6B7280;"> · {{ $payment->phone_number }}</span>
        @endif
        @endif
    </div>

    {{-- Factures imputées --}}
    @if($payment->allocations->count() > 0)
    <div class="alloc-title">Factures réglées</div>
    <table>
        <thead>
            <tr>
                <th>N° Facture</th>
                <th>Date</th>
                <th>Échéance</th>
                <th class="r">Montant facture</th>
                <th class="r">Montant imputé</th>
                <th class="r">Reste à payer</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payment->allocations as $alloc)
            @php
                $resteApayer = max(0, ($alloc->invoice?->remaining_amount ?? 0));
            @endphp
            <tr>
                <td>{{ $alloc->invoice?->number ?? '—' }}</td>
                <td>{{ $alloc->invoice?->issued_at?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $alloc->invoice?->due_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="r">{{ number_format($alloc->invoice?->total_ttc ?? 0, 0, ',', ' ') }}</td>
                <td class="r" style="font-weight:bold; color:#065F46;">{{ number_format($alloc->amount, 0, ',', ' ') }}</td>
                <td class="r" style="font-weight:bold; color:{{ $resteApayer > 0 ? '#DC2626' : '#059669' }};">
                    {{ $resteApayer > 0 ? number_format($resteApayer, 0, ',', ' ') : 'Soldée' }}
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total imputé</td>
                <td class="r">{{ number_format($payment->allocated_amount, 0, ',', ' ') }} FCFA</td>
                @php $totalReste = $payment->allocations->sum(fn($a) => max(0, $a->invoice?->remaining_amount ?? 0)); @endphp
                <td class="r" style="color:{{ $totalReste > 0 ? '#DC2626' : '#059669' }};">
                    {{ $totalReste > 0 ? number_format($totalReste, 0, ',', ' ').' FCFA' : 'Tout soldé' }}
                </td>
            </tr>
        </tfoot>
    </table>
    @endif

    {{-- Solde non imputé (acompte) --}}
    @if($payment->unallocated_amount > 0)
    <div class="unalloc">
        ⚠ Solde créditeur non imputé : <strong>{{ number_format($payment->unallocated_amount, 0, ',', ' ') }} FCFA</strong>
        — Ce montant sera déduit des prochaines factures du client.
    </div>
    @endif

    {{-- Notes --}}
    @if($payment->notes)
    <div style="font-size:8pt; color:#374151; margin-bottom:4mm; padding: 2mm 3mm; background:#F9FAFB; border-radius:3px; border-left:2px solid #D1FAE5;">
        <strong>Notes :</strong> {{ $payment->notes }}
    </div>
    @endif

    {{-- Signatures --}}
    <div class="sig-block">
        <div class="sig-cell">
            <div class="sig-label">Signature du client</div>
            <div class="sig-line"></div>
        </div>
        <div class="sig-cell" style="text-align:right;">
            <div class="sig-label">Cachet & signature de la société</div>
            <div class="sig-line" style="margin-left:auto;"></div>
        </div>
    </div>

    {{-- Pied de page --}}
    <div class="footer">
        {{ $company?->name }} · {{ $company?->address ?? '' }}
        @if($company?->phone) · Tél : {{ $company->phone }} @endif
        @if($company?->ifu) · IFU : {{ $company->ifu }} @endif
        <br>Ce reçu est valable comme justificatif de paiement · N° {{ $payment->number }}
    </div>

</div>
</body>
</html>
