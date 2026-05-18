<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture fournisseur validée — {{ $invoice->number }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #374151; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #1e3a5f; padding: 28px 32px; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0 0 4px; }
        .header p { color: #93c5fd; font-size: 13px; margin: 0; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #111827; margin-bottom: 16px; }
        .summary-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
        .summary-row.total { font-weight: bold; font-size: 15px; color: #1e3a5f; border-top: 1px solid #bfdbfe; padding-top: 8px; margin-top: 8px; }
        .summary-label { color: #374151; }
        .summary-value { font-weight: 600; color: #111827; }
        .badge { display: inline-block; background: #dcfce7; color: #15803d; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; margin-left: 8px; }
        .info-text { font-size: 13px; color: #6b7280; margin-top: 20px; line-height: 1.6; }
        .alert { background: #fef9c3; border: 1px solid #fde047; border-radius: 6px; padding: 12px 16px; margin: 16px 0; font-size: 13px; color: #854d0e; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 16px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Facture fournisseur validée</h1>
        <p>{{ now()->format('d/m/Y à H:i') }}</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour,</p>
        <p>
            La facture fournisseur <strong>{{ $invoice->number }}</strong>
            vient d'être <strong>validée</strong> et enregistrée en comptabilité.
        </p>

        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">N° Facture interne</span>
                <span class="summary-value">{{ $invoice->number }}</span>
            </div>
            @if($invoice->supplier_invoice_number)
            <div class="summary-row">
                <span class="summary-label">Réf. fournisseur</span>
                <span class="summary-value">{{ $invoice->supplier_invoice_number }}</span>
            </div>
            @endif
            <div class="summary-row">
                <span class="summary-label">Fournisseur</span>
                <span class="summary-value">{{ $invoice->supplier?->name ?? '—' }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Date de réception</span>
                <span class="summary-value">{{ $invoice->received_at?->format('d/m/Y') ?? '—' }}</span>
            </div>
            @if($invoice->due_at)
            <div class="summary-row">
                <span class="summary-label">Échéance</span>
                <span class="summary-value">
                    {{ $invoice->due_at->format('d/m/Y') }}
                    @if($invoice->due_at->isPast())
                        <span class="badge" style="background:#fee2e2;color:#991b1b;">En retard</span>
                    @elseif($invoice->due_at->diffInDays(now()) <= 7)
                        <span class="badge" style="background:#fef9c3;color:#854d0e;">Bientôt</span>
                    @endif
                </span>
            </div>
            @endif
            <div class="summary-row">
                <span class="summary-label">Montant HT</span>
                <span class="summary-value">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">TVA</span>
                <span class="summary-value">{{ number_format($invoice->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="summary-row total">
                <span class="summary-label">Total TTC</span>
                <span class="summary-value">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        @if($invoice->due_at && $invoice->due_at->isPast())
        <div class="alert">
            ⚠️ Cette facture est déjà en retard de paiement. Veuillez traiter le décaissement dès que possible.
        </div>
        @elseif($invoice->due_at && $invoice->due_at->diffInDays(now()) <= 7)
        <div class="alert">
            ℹ️ Cette facture arrive à échéance dans {{ ceil($invoice->due_at->diffInDays(now())) }} jours. Pensez à planifier le décaissement.
        </div>
        @endif

        <p class="info-text">
            Veuillez vérifier l'écriture comptable associée et procéder au règlement dans les délais convenus.
        </p>
    </div>
    <div class="footer">
        @php $company = \App\Models\Company::first(); @endphp
        {{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }} — Module Comptabilité
    </div>
</div>
</body>
</html>
