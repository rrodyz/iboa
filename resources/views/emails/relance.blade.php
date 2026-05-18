<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relance — {{ $typeLabel }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #374151; background: #f9fafb; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background: #b91c1c; padding: 28px 32px; }
        .header h1 { color: #fff; font-size: 20px; margin: 0 0 4px; }
        .header p { color: #fecaca; font-size: 13px; margin: 0; }
        .urgency-badge { display: inline-block; background: #fee2e2; color: #991b1b; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #111827; margin-bottom: 16px; }
        .summary-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .summary-box h3 { margin: 0 0 12px; font-size: 14px; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px; }
        table.invoices { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.invoices th { background: #fee2e2; color: #7f1d1d; font-weight: 600; padding: 8px 10px; text-align: left; border-bottom: 2px solid #fecaca; }
        table.invoices td { padding: 8px 10px; border-bottom: 1px solid #fce7f3; vertical-align: middle; }
        table.invoices tr:last-child td { border-bottom: none; }
        .total-row { background: #fef2f2; }
        .total-row td { font-weight: 700; font-size: 15px; color: #991b1b; border-top: 2px solid #fecaca !important; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        .overdue-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 4px; }
        .overdue-red { background: #fee2e2; color: #991b1b; }
        .overdue-orange { background: #ffedd5; color: #c2410c; }
        .overdue-yellow { background: #fef9c3; color: #854d0e; }
        .message-box { background: #f8fafc; border-left: 4px solid #b91c1c; padding: 12px 16px; margin: 16px 0; font-size: 13px; color: #374151; border-radius: 0 4px 4px 0; }
        .cta { background: #b91c1c; color: #fff !important; display: inline-block; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; margin: 16px 0; }
        .footer { background: #f9fafb; border-top: 1px solid #e5e7eb; padding: 18px 32px; font-size: 12px; color: #9ca3af; text-align: center; }
        .footer strong { color: #6b7280; }
    </style>
</head>
<body>
<div class="container">

    {{-- Header --}}
    <div class="header">
        <h1>{{ $typeLabel }}</h1>
        <p>Facture(s) en attente de règlement</p>
    </div>

    <div class="body">
        <p class="greeting">
            Bonjour {{ $client->displayName() }},
        </p>

        <p style="color:#374151; line-height:1.6;">
            Sauf erreur de notre part, nous constatons que
            @if($invoices->count() === 1)
                la facture mentionnée ci-dessous reste impayée à ce jour.
            @else
                {{ $invoices->count() }} factures restent impayées à ce jour.
            @endif
            Nous vous remercions de bien vouloir régulariser cette situation dans les meilleurs délais.
        </p>

        @if($message)
        <div class="message-box">{{ $message }}</div>
        @endif

        {{-- Tableau des factures --}}
        <div class="summary-box">
            <h3>Factures impayées</h3>
            <table class="invoices">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Date</th>
                        <th>Échéance</th>
                        <th class="amount">Reste dû</th>
                        <th>Retard</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                    @php
                        $daysOverdue = $invoice->due_at ? (int) $invoice->due_at->diffInDays(now(), false) : null;
                        $badgeClass = $daysOverdue >= 60 ? 'overdue-red' : ($daysOverdue >= 30 ? 'overdue-orange' : 'overdue-yellow');
                    @endphp
                    <tr>
                        <td><strong>{{ $invoice->number }}</strong></td>
                        <td>{{ $invoice->issued_at?->format('d/m/Y') }}</td>
                        <td>{{ $invoice->due_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="amount"><strong>{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</strong></td>
                        <td>
                            @if($daysOverdue !== null && $daysOverdue > 0)
                            <span class="overdue-badge {{ $badgeClass }}">{{ $daysOverdue }}j</span>
                            @else
                            <span style="color:#9ca3af">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3"><strong>TOTAL DÛ</strong></td>
                        <td class="amount"><strong>{{ number_format($totalDu, 0, ',', ' ') }} FCFA</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($type === 'mise_en_demeure')
        <p style="color:#991b1b; font-weight:600; font-size:14px; border:2px solid #fecaca; padding:12px 16px; border-radius:6px; background:#fef2f2;">
            ⚠️ Sans règlement sous 72 heures, nous nous verrons dans l'obligation d'engager les procédures de recouvrement prévues par la loi.
        </p>
        @endif

        <p style="color:#374151; line-height:1.6; margin-top: 16px;">
            Si vous avez déjà effectué le règlement, veuillez ignorer ce message ou nous contacter pour toute question.
        </p>

        <p style="margin-top:24px; color:#374151;">
            Cordialement,<br>
            <strong>{{ config('app.name') }}</strong>
        </p>
    </div>

    <div class="footer">
        <strong>{{ config('app.name') }}</strong><br>
        Ce message est généré automatiquement. Merci de ne pas y répondre directement.
    </div>
</div>
</body>
</html>
