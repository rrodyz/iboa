<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $invoice->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #374151; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #065f46; padding: 28px 32px; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0 0 4px; }
        .header p { color: #a7f3d0; font-size: 13px; margin: 0; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #111827; margin-bottom: 16px; }
        .summary-box { background: #f0fdf4; border: 1px solid #a7f3d0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
        .summary-row:last-child { margin-bottom: 0; font-weight: bold; font-size: 15px; color: #065f46; border-top: 1px solid #a7f3d0; padding-top: 8px; margin-top: 8px; }
        .summary-label { color: #374151; }
        .summary-value { font-weight: 600; color: #111827; }
        .btn { display: inline-block; background: #065f46; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 600; margin: 16px 0; }
        .info-text { font-size: 13px; color: #6b7280; margin-top: 20px; line-height: 1.6; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Facture {{ $invoice->number }}</h1>
        <p>{{ $invoice->issued_at?->format('d/m/Y') }}</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour {{ $invoice->client?->name ?? 'Client' }},</p>
        <p>Veuillez trouver ci-joint votre facture <strong>{{ $invoice->number }}</strong>.</p>

        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Numéro de facture</span>
                <span class="summary-value">{{ $invoice->number }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Date d'émission</span>
                <span class="summary-value">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</span>
            </div>
            @if($invoice->due_at)
            <div class="summary-row">
                <span class="summary-label">Date d'échéance</span>
                <span class="summary-value">{{ $invoice->due_at->format('d/m/Y') }}</span>
            </div>
            @endif
            <div class="summary-row">
                <span class="summary-label">Montant TTC</span>
                <span class="summary-value">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        @if($invoice->payment_terms)
        <p class="info-text">
            <strong>Conditions de paiement :</strong> {{ $invoice->payment_terms }}
        </p>
        @endif

        <p class="info-text">
            Pour toute question concernant cette facture, n'hésitez pas à nous contacter.
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
