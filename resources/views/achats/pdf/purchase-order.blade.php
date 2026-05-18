<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Commande {{ $purchaseOrder->number }}</title>
    @php
        $color = $settings?->primary_color ?? '#0f766e';
        $font  = $settings?->font_family   ?? 'DejaVu Sans';

        $company = \App\Models\Company::with('bankAccounts')->first();

        $logoBase64 = null;
        if ($settings?->show_logo !== false && $company?->logo) {
            $path = storage_path('app/public/' . $company->logo);
            if (file_exists($path)) {
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match($ext) { 'png' => 'image/png', 'svg' => 'image/svg+xml', default => 'image/jpeg' };
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        $statusLabel = match($purchaseOrder->status) {
            'brouillon'  => 'Brouillon',
            'envoye'     => 'Envoyée',
            'confirme'   => 'Confirmée',
            'partiel'    => 'Partiellement reçue',
            'recu'       => 'Reçue',
            'annule'     => 'Annulée',
            default      => $purchaseOrder->status,
        };

        $tvaRecap = [];
        foreach ($purchaseOrder->items as $item) {
            $rate = (float) $item->tax_rate_value;
            if (!isset($tvaRecap[$rate])) $tvaRecap[$rate] = ['base' => 0, 'tva' => 0];
            $tvaRecap[$rate]['base'] += (float) $item->line_total_ht;
            $tvaRecap[$rate]['tva']  += (float) $item->line_tax;
        }
        ksort($tvaRecap);
    @endphp

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: {{ $font }}, 'DejaVu Sans', sans-serif; font-size:9.5px; color:#1f2937; background:#fff; }
        .page { padding:22px 28px 20px; }

        .header { display:table; width:100%; margin-bottom:14px; }
        .header-left  { display:table-cell; width:58%; vertical-align:top; }
        .header-right { display:table-cell; width:42%; vertical-align:top; text-align:right; }
        .logo { max-height:52px; max-width:170px; margin-bottom:5px; }
        .co-name { font-size:15px; font-weight:bold; color:{{ $color }}; margin-bottom:2px; }
        .co-sub  { font-size:8.5px; color:#374151; margin-top:2px; line-height:1.45; }
        .co-ids  { font-size:8px; color:#374151; margin-top:3px; line-height:1.5; }
        .co-ids strong { color:#111827; }

        .doc-badge { display:inline-block; background:{{ $color }}; color:#fff; font-size:16px;
                     font-weight:bold; padding:5px 14px; border-radius:3px; letter-spacing:.04em; margin-bottom:4px; }
        .doc-meta { font-size:9px; line-height:1.7; }
        .doc-meta td:first-child { color:#6b7280; padding-right:8px; text-align:right; }
        .doc-meta td:last-child  { font-weight:600; color:#111827; }

        .sep { border:none; border-top:2.5px solid {{ $color }}; margin:12px 0; }

        .parties { display:table; width:100%; margin-bottom:12px; }
        .party-col { display:table-cell; width:50%; vertical-align:top; padding:8px 10px;
                     border:1px solid #e5e7eb; border-radius:3px; }
        .party-col.right { padding-left:14px; border-left:none; }
        .party-lbl  { font-size:7.5px; font-weight:bold; text-transform:uppercase; letter-spacing:.07em;
                      color:#fff; background:{{ $color }}; padding:2px 6px; border-radius:2px;
                      display:inline-block; margin-bottom:5px; }
        .party-name { font-size:11px; font-weight:bold; color:#111827; margin-bottom:2px; }
        .party-line { font-size:8.5px; color:#374151; line-height:1.5; }
        .party-id   { font-size:8px; color:#374151; line-height:1.6; }
        .party-id strong { color:#111827; }

        .status-badge { display:inline-block; border:1.5px solid; border-radius:3px; padding:2px 8px;
                        font-size:9px; font-weight:bold; margin-top:4px; }
        .status-brouillon { border-color:#9ca3af; color:#6b7280; }
        .status-envoye    { border-color:#3b82f6; color:#1d4ed8; }
        .status-confirme  { border-color:#059669; color:#065f46; }
        .status-recu      { border-color:#059669; color:#065f46; }
        .status-partiel   { border-color:#d97706; color:#92400e; }
        .status-annule    { border-color:#6b7280; color:#6b7280; }

        .items-table { width:100%; border-collapse:collapse; margin-bottom:0; font-size:8.5px; }
        .items-table thead th { background:{{ $color }}; color:#fff; padding:5px 7px;
                                text-align:left; font-size:7.5px; text-transform:uppercase;
                                font-weight:bold; letter-spacing:.03em; }
        .items-table thead th.r { text-align:right; }
        .items-table tbody tr:nth-child(even) { background:#f9fafb; }
        .items-table tbody tr { border-bottom:1px solid #e5e7eb; }
        .items-table tbody td { padding:4.5px 7px; color:#374151; vertical-align:top; }
        .items-table tbody td.r { text-align:right; }
        .items-table tfoot td { padding:4px 7px; font-size:8px; color:#6b7280; }

        .bottom-wrap { display:table; width:100%; margin-top:10px; }
        .tva-col   { display:table-cell; width:46%; vertical-align:top; padding-right:12px; }
        .total-col { display:table-cell; width:54%; vertical-align:top; }

        .tva-box { border:1px solid #e5e7eb; border-radius:3px; overflow:hidden; }
        .tva-box .hd { background:#f3f4f6; padding:4px 8px; font-size:7.5px; font-weight:bold;
                       text-transform:uppercase; color:#374151; letter-spacing:.05em; }
        .tva-table { width:100%; border-collapse:collapse; font-size:8.5px; }
        .tva-table th { background:#f9fafb; padding:4px 8px; font-size:7.5px; color:#6b7280;
                        text-transform:uppercase; text-align:right; border-bottom:1px solid #e5e7eb; }
        .tva-table th:first-child { text-align:left; }
        .tva-table td { padding:4px 8px; color:#374151; text-align:right; border-bottom:1px solid #f3f4f6; }
        .tva-table td:first-child { text-align:left; }

        .tot-table { width:100%; border-collapse:collapse; font-size:9px; }
        .tot-table td { padding:4px 10px; }
        .tot-table .lbl { color:#374151; background:#f9fafb; }
        .tot-table .val { text-align:right; color:#111827; font-weight:600; }
        .tot-table tr { border-bottom:1px solid #e5e7eb; }
        .tot-table .grand { background:{{ $color }} !important; }
        .tot-table .grand td { color:#fff; font-size:11px; font-weight:bold; padding:6px 10px; }

        .notes-box { background:#fffbeb; border:1px solid #fde68a; border-radius:3px;
                     padding:7px 10px; margin-top:10px; }
        .notes-lbl { font-size:7.5px; font-weight:bold; color:#92400e; text-transform:uppercase; margin-bottom:3px; }
        .notes-txt { font-size:8.5px; color:#78350f; }

        .sig-row { display:table; width:100%; margin-top:24px; }
        .sig-cell { display:table-cell; width:50%; text-align:center; padding:0 8px; vertical-align:bottom; }
        .sig-box  { border-top:1px solid #374151; padding-top:5px; font-size:8px; color:#6b7280; }
        .sig-box strong { color:#111827; font-size:8.5px; display:block; margin-bottom:1px; }

        .footer { margin-top:16px; border-top:1px solid #e5e7eb; padding-top:7px;
                  font-size:7.5px; color:#9ca3af; text-align:center; line-height:1.6; }
    </style>
</head>
<body>
<div class="page">

    {{-- En-tête --}}
    <div class="header">
        <div class="header-left">
            @if($logoBase64)
            <img src="{{ $logoBase64 }}" class="logo" alt="Logo">
            @endif
            <div class="co-name">{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</div>
            @if($company?->legal_form)
            <div class="co-sub">
                {{ $company->legal_form }}
                @if($company->share_capital)
                    — Capital : {{ number_format($company->share_capital, 0, ',', ' ') }} FCFA
                @endif
            </div>
            @endif
            <div class="co-sub">
                @if($company?->address)
                    {{ $company->address }}@if($company->city), {{ $company->city }}@endif
                @endif
                @if($company?->phone)<br>Tél. : {{ $company->phone }}@endif
                @if($company?->email)<br>{{ $company->email }}@endif
            </div>
            <div class="co-ids">
                @if($company?->ifu)<strong>IFU :</strong> {{ $company->ifu }}&nbsp;&nbsp;@endif
                @if($company?->rccm)<strong>RCCM :</strong> {{ $company->rccm }}&nbsp;&nbsp;@endif
                @if($company?->nif)<strong>NIF :</strong> {{ $company->nif }}@endif
            </div>
        </div>

        <div class="header-right">
            <div class="doc-badge">BON DE COMMANDE</div>
            <br>
            <table class="doc-meta" style="display:inline-table; margin-top:4px;">
                <tr>
                    <td>N° :</td>
                    <td style="font-size:11px;">{{ $purchaseOrder->number }}</td>
                </tr>
                <tr>
                    <td>Date :</td>
                    <td>{{ $purchaseOrder->ordered_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
                </tr>
                @if($purchaseOrder->expected_at)
                <tr>
                    <td>Livraison attendue :</td>
                    <td>{{ $purchaseOrder->expected_at->format('d/m/Y') }}</td>
                </tr>
                @endif
            </table>
            <br>
            <span class="status-badge status-{{ $purchaseOrder->status }}">{{ $statusLabel }}</span>
        </div>
    </div>

    <hr class="sep">

    {{-- Parties --}}
    <div class="parties">
        <div class="party-col">
            <div class="party-lbl">Acheteur</div>
            <div class="party-name">{{ $company?->name ?? 'A3 ERP' }}</div>
            @if($company?->address || $company?->city)
            <div class="party-line">{{ collect([$company->address, $company->city])->filter()->implode(', ') }}</div>
            @endif
            <div class="party-id" style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6;">
                @if($company?->ifu)  <strong>IFU :</strong> {{ $company->ifu }}<br>@endif
                @if($company?->rccm) <strong>RCCM :</strong> {{ $company->rccm }}<br>@endif
            </div>
        </div>
        <div class="party-col right">
            <div class="party-lbl">Fournisseur</div>
            <div class="party-name">{{ $purchaseOrder->supplier?->name ?? '—' }}</div>
            @if($purchaseOrder->supplier?->address)
            <div class="party-line">{{ $purchaseOrder->supplier->address }}{{ $purchaseOrder->supplier->city ? ', '.$purchaseOrder->supplier->city : '' }}</div>
            @endif
            @if($purchaseOrder->supplier?->phone)
            <div class="party-line">Tél. : {{ $purchaseOrder->supplier->phone }}</div>
            @endif
            <div class="party-id" style="margin-top:3px; padding-top:3px; border-top:1px solid #f3f4f6;">
                @if($purchaseOrder->supplier?->ifu) <strong>IFU :</strong> {{ $purchaseOrder->supplier->ifu }}<br>@endif
                @if($purchaseOrder->supplier?->rccm) <strong>RCCM :</strong> {{ $purchaseOrder->supplier->rccm }}<br>@endif
            </div>
        </div>
    </div>

    @if($purchaseOrder->delivery_address)
    <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:3px; padding:5px 10px; font-size:8.5px; margin-bottom:10px;">
        <strong>Adresse de livraison :</strong> {{ $purchaseOrder->delivery_address }}
    </div>
    @endif

    {{-- Tableau des articles --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:22px">#</th>
                <th>Désignation</th>
                <th class="r" style="width:45px">Qté</th>
                <th style="width:28px">Unité</th>
                <th class="r" style="width:80px">P.U. HT</th>
                <th class="r" style="width:38px">Rem.%</th>
                <th class="r" style="width:80px">Montant HT</th>
                <th class="r" style="width:35px">TVA%</th>
                <th class="r" style="width:80px">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @forelse($purchaseOrder->items as $item)
            <tr>
                <td style="color:#9ca3af;">{{ $loop->iteration }}</td>
                <td>
                    <strong>{{ $item->description }}</strong>
                    @if($item->product?->reference)
                    <br><span style="color:#9ca3af; font-size:7.5px;">Réf. {{ $item->product->reference }}</span>
                    @endif
                </td>
                <td class="r" style="white-space:nowrap;">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                <td style="color:#6b7280;">{{ $item->unit?->abbreviation ?? '' }}</td>
                <td class="r" style="white-space:nowrap;">{{ number_format($item->unit_price, 0, ',', ' ') }}</td>
                <td class="r">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 1, ',', '').'%' : '—' }}</td>
                <td class="r" style="white-space:nowrap;">{{ number_format($item->line_total_ht, 0, ',', ' ') }}</td>
                <td class="r">{{ number_format($item->tax_rate_value, 0, ',', '') }}%</td>
                <td class="r" style="white-space:nowrap; font-weight:600;">{{ number_format($item->line_total_ttc, 0, ',', ' ') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center; color:#9ca3af; padding:14px;">Aucune ligne</td>
            </tr>
            @endforelse
        </tbody>
        @if($purchaseOrder->items->count() > 0)
        <tfoot>
            <tr style="border-top:2px solid #e5e7eb;">
                <td colspan="6" style="text-align:right; color:#6b7280; font-style:italic;">
                    {{ $purchaseOrder->items->count() }} article(s)
                </td>
                <td class="r" style="font-weight:600; color:#111827;">
                    {{ number_format($purchaseOrder->subtotal_ht, 0, ',', ' ') }}
                </td>
                <td></td>
                <td class="r" style="font-weight:bold; color:#111827;">
                    {{ number_format($purchaseOrder->total_ttc, 0, ',', ' ') }}
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Récap TVA + Totaux --}}
    <div class="bottom-wrap">
        <div class="tva-col">
            <div class="tva-box">
                <div class="hd">Récapitulatif de TVA</div>
                <table class="tva-table">
                    <thead><tr><th>Taux</th><th>Base HT</th><th>TVA</th></tr></thead>
                    <tbody>
                        @forelse($tvaRecap as $rate => $data)
                        <tr>
                            <td>{{ number_format($rate, 0) }}%</td>
                            <td>{{ number_format($data['base'], 0, ',', ' ') }} F</td>
                            <td>{{ number_format($data['tva'], 0, ',', ' ') }} F</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="text-align:center; color:#9ca3af;">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($purchaseOrder->notes)
            <div class="notes-box" style="margin-top:8px;">
                <div class="notes-lbl">Notes</div>
                <div class="notes-txt">{{ $purchaseOrder->notes }}</div>
            </div>
            @endif
        </div>

        <div class="total-col">
            <table class="tot-table" style="border:1px solid #e5e7eb; border-radius:3px; overflow:hidden;">
                <tr>
                    <td class="lbl">Total HT</td>
                    <td class="val">{{ number_format($purchaseOrder->subtotal_ht, 0, ',', ' ') }} FCFA</td>
                </tr>
                @foreach($tvaRecap as $rate => $data)
                <tr>
                    <td class="lbl">TVA {{ number_format($rate, 0) }}%</td>
                    <td class="val">{{ number_format($data['tva'], 0, ',', ' ') }} FCFA</td>
                </tr>
                @endforeach
                <tr class="grand">
                    <td class="lbl">TOTAL TTC</td>
                    <td class="val">{{ number_format($purchaseOrder->total_ttc, 0, ',', ' ') }} FCFA</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Signatures --}}
    <div class="sig-row" style="margin-top:20px;">
        <div class="sig-cell" style="text-align:left; padding-left:0;">
            <div style="border:1px dashed #d1d5db; border-radius:3px; padding:10px; text-align:center;">
                <div style="font-size:8px; color:#9ca3af; margin-bottom:16px;">Signature et cachet Fournisseur<br>(Bon pour accord)</div>
                <br>
            </div>
            <div style="font-size:7.5px; color:#9ca3af; text-align:center; margin-top:4px;">
                {{ $purchaseOrder->supplier?->name ?? '' }}
            </div>
        </div>
        <div class="sig-cell" style="padding-right:0;">
            <div class="sig-box">
                <strong>{{ $settings?->signature_name ?? ($company?->trade_name ?? $company?->name ?? '') }}</strong>
                {{ $settings?->signature_title ?? 'Le Responsable Achats' }}
            </div>
        </div>
    </div>

    {{-- CGV --}}
    @if($settings?->terms_conditions)
    <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:3px; padding:7px 10px; margin-top:12px;">
        <div style="font-size:7.5px; font-weight:bold; color:#374151; text-transform:uppercase; margin-bottom:3px;">Conditions générales</div>
        <div style="font-size:7.5px; color:#6b7280; line-height:1.5;">{{ $settings->terms_conditions }}</div>
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        @if($settings?->footer_text)
            {{ $settings->footer_text }}
        @else
            <strong>{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</strong>
            @if($company?->address) | {{ $company->address }}@if($company->city), {{ $company->city }}@endif
            @endif
            @if($company?->ifu) | IFU : {{ $company->ifu }} @endif
            @if($company?->rccm) | RCCM : {{ $company->rccm }} @endif
        @endif
        <br><span style="color:#d1d5db;">Document généré le {{ now()->format('d/m/Y à H:i') }}</span>
    </div>

</div>
</body>
</html>
