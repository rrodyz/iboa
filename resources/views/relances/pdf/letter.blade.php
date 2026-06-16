<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11pt; color: #1f2937; line-height: 1.5; margin: 0; padding: 40px 48px; }
        .header { overflow: hidden; border-bottom: 2px solid #4f46e5; padding-bottom: 12px; margin-bottom: 8px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #4f46e5; }
        .company-sub { font-size: 9pt; color: #6b7280; }
        .meta { text-align: right; margin: 18px 0 6px; font-size: 10pt; }
        .recipient { margin: 0 0 24px auto; width: 55%; font-size: 10.5pt; }
        .recipient .name { font-weight: bold; }
        .object { font-weight: bold; margin: 18px 0; }
        .object .ref { color: #4f46e5; }
        table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 9.5pt; }
        th { background: #f3f4f6; text-align: left; padding: 6px 8px; border-bottom: 1px solid #d1d5db; color: #374151; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        td.num, th.num { text-align: right; }
        .total-row td { font-weight: bold; border-top: 2px solid #4f46e5; background: #eef2ff; }
        .late { color: #dc2626; font-weight: bold; }
        .body-text { margin: 14px 0; text-align: justify; }
        .signature { margin-top: 36px; }
        .footer { position: fixed; bottom: 18px; left: 48px; right: 48px; font-size: 8pt; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-name">{{ $company->name ?? 'Société' }}</div>
        @if($company?->address)<div class="company-sub">{{ $company->address }}{{ $company->city ? ', '.$company->city : '' }}</div>@endif
        @if($company?->phone)<div class="company-sub">Tél : {{ $company->phone }}</div>@endif
        @if($company?->ifu)<div class="company-sub">IFU : {{ $company->ifu }}</div>@endif
    </div>

    <div class="meta">
        {{ $company->city ?? '' }}{{ $company->city ? ', le ' : 'Le ' }}{{ $today->translatedFormat('d F Y') }}
    </div>

    <div class="recipient">
        <div class="name">{{ $client->trade_name ?? $client->name }}</div>
        @if($client->address)<div>{{ $client->address }}</div>@endif
        @if($client->phone)<div>Tél : {{ $client->phone }}</div>@endif
    </div>

    <div class="object">Objet : {{ $typeLabel }} — <span class="ref">Factures impayées</span></div>

    <p>Madame, Monsieur,</p>

    @if($message)
        <div class="body-text">{!! nl2br(e($message)) !!}</div>
    @else
        @switch($typeLabel)
            @case('Mise en demeure')
                <div class="body-text">
                    Malgré nos précédentes relances, nous constatons que les factures listées ci-dessous
                    demeurent impayées à ce jour. En conséquence, nous vous mettons en demeure de régler
                    le montant total dû sous huitaine à compter de la réception de ce courrier.
                    À défaut de règlement dans ce délai, nous nous réservons le droit d'engager
                    toute procédure de recouvrement, sans autre avis.
                </div>
                @break
            @case('2ème relance (formelle)')
                <div class="body-text">
                    Sauf erreur ou omission de notre part, les factures détaillées ci-dessous restent
                    impayées malgré notre première relance. Nous vous prions de bien vouloir procéder
                    à leur règlement dans les meilleurs délais.
                </div>
                @break
            @default
                <div class="body-text">
                    Sauf erreur ou omission de notre part, nous constatons que les factures suivantes
                    ne sont pas encore réglées. Nous vous serions reconnaissants de bien vouloir
                    procéder à leur règlement dans les meilleurs délais.
                </div>
        @endswitch
    @endif

    <table>
        <thead>
            <tr>
                <th>N° Facture</th>
                <th>Date</th>
                <th>Échéance</th>
                <th class="num">Reste à payer</th>
                <th class="num">Retard</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
            <tr>
                <td>{{ $inv->number }}</td>
                <td>{{ $inv->issued_at?->format('d/m/Y') ?? $inv->created_at?->format('d/m/Y') }}</td>
                <td>{{ $inv->due_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="num">{{ number_format($inv->remaining_amount, 0, ',', ' ') }} FCFA</td>
                <td class="num">
                    @if($inv->days_overdue !== null && $inv->days_overdue > 0)
                        <span class="late">{{ $inv->days_overdue }} j</span>
                    @else
                        —
                    @endif
                </td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">TOTAL DÛ</td>
                <td class="num">{{ number_format($totalDu, 0, ',', ' ') }} FCFA</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="body-text">
        Nous restons à votre disposition pour tout renseignement complémentaire et vous prions
        d'agréer, Madame, Monsieur, l'expression de nos salutations distinguées.
    </div>

    <div class="signature">
        La Direction<br>
        <strong>{{ $company->name ?? '' }}</strong>
    </div>

    <div class="footer">
        {{ $company->name ?? '' }}
        @if($company?->phone) · Tél : {{ $company->phone }} @endif
        @if($company?->ifu) · IFU : {{ $company->ifu }} @endif
    </div>

</body>
</html>
