<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avoir {{ $creditNote->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #374151; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #7c3aed; padding: 28px 32px; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0 0 4px; }
        .header p { color: #ddd6fe; font-size: 13px; margin: 0; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #111827; margin-bottom: 16px; }
        .summary-box { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
        .summary-row:last-child { margin-bottom: 0; font-weight: bold; font-size: 15px; color: #7c3aed; border-top: 1px solid #ddd6fe; padding-top: 8px; margin-top: 8px; }
        .summary-label { color: #374151; }
        .summary-value { font-weight: 600; color: #111827; }
        .info-text { font-size: 13px; color: #6b7280; margin-top: 20px; line-height: 1.6; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Avoir {{ $creditNote->number }}</h1>
        <p>{{ $creditNote->issued_at?->format('d/m/Y') }}</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour {{ $creditNote->client?->name ?? 'Client' }},</p>
        <p>
            Veuillez trouver ci-joint l'avoir <strong>{{ $creditNote->number }}</strong>
            @if($creditNote->invoice)
                en déduction de la facture <strong>{{ $creditNote->invoice->number }}</strong>
            @endif
            émis à votre nom.
        </p>

        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Numéro d'avoir</span>
                <span class="summary-value">{{ $creditNote->number }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Date d'émission</span>
                <span class="summary-value">{{ $creditNote->issued_at?->format('d/m/Y') ?? '—' }}</span>
            </div>
            @if($creditNote->invoice)
            <div class="summary-row">
                <span class="summary-label">Facture concernée</span>
                <span class="summary-value">{{ $creditNote->invoice->number }}</span>
            </div>
            @endif
            @if($creditNote->reason)
            <div class="summary-row">
                <span class="summary-label">Motif</span>
                <span class="summary-value">{{ $creditNote->reason }}</span>
            </div>
            @endif
            <div class="summary-row">
                <span class="summary-label">Montant TTC</span>
                <span class="summary-value">{{ number_format($creditNote->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        <p class="info-text">
            Cet avoir sera déduit de vos prochaines factures ou remboursé selon les modalités convenues.<br>
            Pour toute question, n'hésitez pas à nous contacter.
        </p>
    </div>
    <div class="footer">
        @php $company = \App\Models\Company::first(); @endphp
        {{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}
        @if($company?->phone) &mdash; {{ $company->phone }}@endif
        @if($company?->email) &mdash; {{ $company->email }}@endif
    </div>
</div>
</body>
</html>
