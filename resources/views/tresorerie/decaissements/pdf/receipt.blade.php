<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
.page { padding: 10mm 12mm; max-width: 148mm; margin: 0 auto; }
.header { display: table; width: 100%; margin-bottom: 6mm; }
.h-logo { display: table-cell; vertical-align: top; width: 55%; }
.h-logo .company-name { font-size: 13pt; font-weight: bold; color: #92400E; }
.h-logo .company-sub  { font-size: 8pt; color: #6B7280; margin-top: 2px; }
.h-right { display: table-cell; vertical-align: top; text-align: right; font-size: 8pt; color: #374151; }
.receipt-title { background: #92400E; color: #fff; text-align: center; padding: 5px 0; font-size: 13pt; font-weight: bold; letter-spacing: 1px; margin-bottom: 5mm; border-radius: 3px; }
.info-grid { display: table; width: 100%; margin-bottom: 5mm; }
.info-col  { display: table-cell; width: 50%; vertical-align: top; padding-right: 5mm; }
.info-col:last-child { padding-right: 0; padding-left: 5mm; }
.info-label { font-size: 7.5pt; color: #6B7280; margin-bottom: 1px; }
.info-value { font-size: 9pt; font-weight: bold; color: #111827; }
.amount-block { background: #FEF3C7; border: 2px solid #F59E0B; border-radius: 5px; text-align: center; padding: 6mm 4mm; margin-bottom: 5mm; }
.amount-label { font-size: 8pt; color: #92400E; margin-bottom: 2px; }
.amount-value { font-size: 22pt; font-weight: bold; color: #92400E; }
.amount-letters { font-size: 8pt; color: #B45309; font-style: italic; margin-top: 2px; }
table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 5mm; }
thead tr { background: #FEF3C7; }
th { padding: 3px 5px; font-weight: bold; font-size: 7.5pt; text-align: left; }
th.r { text-align: right; }
tbody tr { border-bottom: 0.5px solid #FEF3C7; }
td { padding: 2.5px 5px; }
td.r { text-align: right; font-variant-numeric: tabular-nums; }
tfoot td { font-weight: bold; padding: 3px 5px; border-top: 1px solid #92400E; }
tfoot td.r { text-align: right; }
.sig-block { display: table; width: 100%; margin-top: 6mm; }
.sig-cell  { display: table-cell; width: 50%; }
.sig-label { font-size: 7.5pt; color: #6B7280; margin-bottom: 8mm; }
.sig-line  { border-top: 1px solid #9CA3AF; width: 80%; }
.footer { border-top: 1px solid #FEF3C7; margin-top: 5mm; padding-top: 2mm; font-size: 7pt; color: #9CA3AF; text-align: center; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="h-logo">
            <div class="company-name">{{ $company?->name ?? 'ERP' }}</div>
            @if($company?->address)<div class="company-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}</div>@endif
            @if($company?->phone)<div class="company-sub">Tél : {{ $company->phone }}</div>@endif
            @if($company?->ifu)<div class="company-sub">IFU : {{ $company->ifu }}</div>@endif
        </div>
        <div class="h-right">
            <div style="font-weight:bold; font-size:10pt; color:#92400E;">N° {{ $payment->number }}</div>
            <div>Le {{ $payment->payment_date?->format('d/m/Y') }}</div>
            <div style="margin-top:3px; color:#9CA3AF;">Édité le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="receipt-title">REÇU DE DÉCAISSEMENT</div>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-label">Payé à :</div>
            <div class="info-value">{{ $payment->supplier?->name ?? '—' }}</div>
            @if($payment->supplier?->phone)<div style="font-size:8pt; color:#6B7280; margin-top:1px;">{{ $payment->supplier->phone }}</div>@endif
        </div>
        <div class="info-col">
            <div class="info-label">Date du paiement :</div>
            <div class="info-value">{{ $payment->payment_date?->format('d/m/Y') }}</div>
            @if($payment->reference)
            <div class="info-label" style="margin-top:3px;">Référence :</div>
            <div class="info-value">{{ $payment->reference }}</div>
            @endif
        </div>
    </div>

    <div class="amount-block">
        <div class="amount-label">MONTANT DÉCAISSÉ</div>
        <div class="amount-value">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</div>
        @if($amountInWords)<div class="amount-letters">{{ $amountInWords }}</div>@endif
    </div>

    @if($payment->paymentMethod)
    <div style="text-align:center; margin-bottom:4mm; font-size:9pt;">
        Payé par : <strong>{{ $payment->paymentMethod->name }}</strong>
        @if($payment->phone_number) · {{ $payment->phone_number }}@endif
    </div>
    @endif

    @if(isset($payment->allocations) && $payment->allocations->count() > 0)
    <div style="font-size:8.5pt; font-weight:bold; color:#92400E; margin-bottom:2mm; border-bottom:1px solid #FEF3C7; padding-bottom:1mm;">Factures fournisseur réglées</div>
    <table>
        <thead><tr><th>N° Facture</th><th>Date</th><th class="r">Montant</th><th class="r">Imputé</th></tr></thead>
        <tbody>
            @foreach($payment->allocations as $alloc)
            <tr>
                <td>{{ $alloc->supplierInvoice?->number ?? '—' }}</td>
                <td>{{ $alloc->supplierInvoice?->received_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="r">{{ number_format($alloc->supplierInvoice?->total_ttc ?? 0, 0, ',', ' ') }}</td>
                <td class="r" style="font-weight:bold;">{{ number_format($alloc->amount, 0, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">Total imputé</td><td class="r">{{ number_format($payment->allocated_amount, 0, ',', ' ') }} FCFA</td></tr></tfoot>
    </table>
    @endif

    @if($payment->notes)
    <div style="font-size:8pt; color:#374151; margin-bottom:4mm; padding: 2mm 3mm; background:#FFFBEB; border-radius:3px; border-left:2px solid #FCD34D;">
        <strong>Notes :</strong> {{ $payment->notes }}
    </div>
    @endif

    <div class="sig-block">
        <div class="sig-cell">
            <div class="sig-label">Signature du fournisseur</div>
            <div class="sig-line"></div>
        </div>
        <div class="sig-cell" style="text-align:right;">
            <div class="sig-label">Cachet & signature de la société</div>
            <div class="sig-line" style="margin-left:auto;"></div>
        </div>
    </div>

    <div class="footer">
        {{ $company?->name }} · Ce document est un justificatif de décaissement · N° {{ $payment->number }}
    </div>
</div>
</body>
</html>
