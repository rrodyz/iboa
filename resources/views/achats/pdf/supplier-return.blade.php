<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Avoir {{ $return->number }}</title>
    @php
        $company = \App\Models\Company::first();

        $logoBase64 = null;
        if ($company?->logo) {
            $path = storage_path('app/public/' . $company->logo);
            if (file_exists($path)) {
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = match($ext) { 'png' => 'image/png', 'svg' => 'image/svg+xml', default => 'image/jpeg' };
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }
    @endphp
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size:9.5px; color:#1f2937; background:#fff; }
        .page { padding:22px 28px 20px; }

        .header { display:table; width:100%; margin-bottom:16px; }
        .header-left  { display:table-cell; width:58%; vertical-align:top; }
        .header-right { display:table-cell; width:42%; vertical-align:top; text-align:right; }

        .doc-title { font-size:22px; font-weight:bold; color:#b45309; margin-bottom:4px; }
        .doc-number { font-size:12px; color:#6b7280; margin-bottom:2px; }
        .doc-badge { display:inline-block; background:#fef3c7; color:#92400e; border:1px solid #f59e0b; border-radius:4px; padding:2px 8px; font-size:8px; font-weight:bold; text-transform:uppercase; }

        .company-name { font-size:13px; font-weight:bold; color:#111827; margin-bottom:2px; }
        .company-info { font-size:8.5px; color:#6b7280; line-height:1.5; }

        .meta-table { width:100%; border-collapse:collapse; margin-bottom:14px; }
        .meta-table td { padding:5px 8px; font-size:8.5px; vertical-align:top; }
        .meta-table .label { color:#6b7280; font-weight:bold; width:38%; }
        .meta-table .val { color:#111827; }

        .section-box { border:1px solid #e5e7eb; border-radius:4px; margin-bottom:14px; overflow:hidden; }
        .section-head { background:#f9fafb; padding:6px 10px; border-bottom:1px solid #e5e7eb; font-size:8.5px; font-weight:bold; color:#374151; text-transform:uppercase; letter-spacing:.5px; }

        .addr-box { display:table-cell; width:50%; padding:8px 10px; vertical-align:top; font-size:8.5px; line-height:1.5; }
        .addr-label { font-size:7.5px; font-weight:bold; color:#6b7280; text-transform:uppercase; margin-bottom:3px; letter-spacing:.5px; }

        table.items { width:100%; border-collapse:collapse; font-size:8.5px; }
        table.items thead tr { background:#b45309; color:#fff; }
        table.items th { padding:5px 8px; text-align:left; font-weight:600; font-size:8px; text-transform:uppercase; letter-spacing:.3px; }
        table.items th.right { text-align:right; }
        table.items tbody tr { border-bottom:1px solid #f3f4f6; }
        table.items tbody tr:last-child { border-bottom:none; }
        table.items td { padding:5px 8px; vertical-align:top; }
        table.items td.right { text-align:right; }
        table.items td.num { font-variant-numeric:tabular-nums; }

        .totals-wrap { display:table; width:100%; margin-top:10px; }
        .totals-spacer { display:table-cell; width:60%; }
        .totals-box { display:table-cell; width:40%; vertical-align:top; }
        .totals-row { display:table; width:100%; padding:4px 0; border-bottom:1px solid #f3f4f6; }
        .totals-row .t-label { display:table-cell; font-size:8.5px; color:#6b7280; }
        .totals-row .t-val { display:table-cell; text-align:right; font-size:8.5px; color:#1f2937; font-variant-numeric:tabular-nums; }
        .totals-row.total-ttc { border-top:2px solid #b45309; border-bottom:none; margin-top:2px; padding-top:6px; }
        .totals-row.total-ttc .t-label,
        .totals-row.total-ttc .t-val { font-size:11px; font-weight:bold; color:#b45309; }

        .notes-box { background:#fffbeb; border:1px solid #fde68a; border-radius:4px; padding:8px 10px; margin-top:10px; }
        .notes-box .notes-label { font-size:8px; font-weight:bold; color:#92400e; text-transform:uppercase; margin-bottom:4px; }
        .notes-box .notes-text { font-size:8.5px; color:#78350f; line-height:1.5; }

        .footer { margin-top:18px; border-top:1px solid #e5e7eb; padding-top:8px; text-align:center; font-size:7.5px; color:#9ca3af; }

        .reason-box { background:#fef2f2; border:1px solid #fecaca; border-radius:4px; padding:6px 10px; margin-bottom:10px; }
        .reason-label { font-size:8px; font-weight:bold; color:#dc2626; text-transform:uppercase; margin-bottom:2px; }
        .reason-text { font-size:9px; color:#991b1b; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" style="max-height:55px; max-width:160px; margin-bottom:8px;">
            @endif
            <div class="company-name">{{ $company?->name ?? 'Votre entreprise' }}</div>
            <div class="company-info">
                @if($company?->address){{ $company->address }}<br>@endif
                @if($company?->phone)Tél : {{ $company->phone }}  @endif
                @if($company?->email){{ $company->email }}<br>@endif
                @if($company?->tax_id)NIF : {{ $company->tax_id }}@endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-title">AVOIR FOURNISSEUR</div>
            <div class="doc-number">{{ $return->number }}</div>
            <div style="margin-top:6px;">
                @php
                    $statusLabel = match($return->status) {
                        'brouillon'        => 'Brouillon',
                        'valide'           => 'Validé',
                        'envoye'           => 'Envoyé',
                        'recu_fournisseur' => 'Reçu fournisseur',
                        'annule'           => 'Annulé',
                        default            => $return->status,
                    };
                @endphp
                <span class="doc-badge">{{ $statusLabel }}</span>
            </div>
            <div style="margin-top:8px; font-size:8.5px; color:#6b7280;">
                Date de retour : <strong>{{ $return->returned_at?->format('d/m/Y') ?? '—' }}</strong><br>
                Généré le : {{ now()->format('d/m/Y à H:i') }}
            </div>
        </div>
    </div>

    {{-- Supplier info --}}
    <div class="section-box" style="margin-bottom:12px;">
        <div style="display:table; width:100%;">
            <div class="addr-box">
                <div class="addr-label">Fournisseur</div>
                <div style="font-size:10px; font-weight:bold; color:#111827;">{{ $return->supplier?->name ?? '—' }}</div>
                @if($return->supplier?->address)
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ $return->supplier->address }}</div>
                @endif
                @if($return->supplier?->email)
                <div style="font-size:8.5px; color:#6b7280;">{{ $return->supplier->email }}</div>
                @endif
            </div>
            <div class="addr-box">
                <div class="addr-label">Références</div>
                @if($return->purchaseOrder)
                <div style="font-size:8.5px; color:#374151;">BC lié : <strong>{{ $return->purchaseOrder->number }}</strong></div>
                @endif
                @if($return->supplierInvoice)
                <div style="font-size:8.5px; color:#374151;">Facture liée : <strong>{{ $return->supplierInvoice->number }}</strong></div>
                @endif
                @if($return->reception)
                <div style="font-size:8.5px; color:#374151;">Réception : <strong>{{ $return->reception->number }}</strong></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Reason --}}
    @if($return->reason)
    <div class="reason-box">
        <div class="reason-label">Motif du retour</div>
        <div class="reason-text">{{ $return->reason }}</div>
    </div>
    @endif

    {{-- Items table --}}
    <div class="section-box">
        <div class="section-head">Articles retournés</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width:40%;">Article / Description</th>
                    <th class="right" style="width:10%;">Qté</th>
                    <th class="right" style="width:18%;">P.U. HT</th>
                    <th class="right" style="width:10%;">Rem.</th>
                    <th class="right" style="width:22%;">Total HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($return->items as $item)
                <tr>
                    <td>
                        @if($item->product)
                            <strong>{{ $item->product->name }}</strong>
                            @if($item->product->reference)
                            <br><span style="color:#9ca3af;font-size:8px;">{{ $item->product->reference }}</span>
                            @endif
                        @else
                            {{ $item->description ?: '—' }}
                        @endif
                    </td>
                    <td class="right num">{{ number_format($item->quantity, 0, ',', ' ') }}</td>
                    <td class="right num">{{ number_format($item->unit_price, 0, ',', ' ') }}</td>
                    <td class="right num">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 1).'%' : '—' }}</td>
                    <td class="right num">{{ number_format($item->line_total_ht, 0, ',', ' ') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Totals --}}
    <div class="totals-wrap">
        <div class="totals-spacer"></div>
        <div class="totals-box">
            <div class="totals-row">
                <div class="t-label">Sous-total HT</div>
                <div class="t-val">{{ number_format($return->subtotal_ht, 0, ',', ' ') }} FCFA</div>
            </div>
            @if($return->total_tax > 0)
            <div class="totals-row">
                <div class="t-label">Taxes</div>
                <div class="t-val">{{ number_format($return->total_tax, 0, ',', ' ') }} FCFA</div>
            </div>
            @endif
            <div class="totals-row total-ttc">
                <div class="t-label">Total TTC</div>
                <div class="t-val">{{ number_format($return->total_ttc, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>

    {{-- Notes --}}
    @if($return->notes)
    <div class="notes-box" style="margin-top:14px;">
        <div class="notes-label">Notes</div>
        <div class="notes-text">{{ $return->notes }}</div>
    </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        {{ $company?->name ?? '' }}
        @if($company?->tax_id) — NIF : {{ $company->tax_id }} @endif
        @if($company?->address) — {{ $company->address }} @endif
        @if($company?->phone) — Tél : {{ $company->phone }} @endif
        <br>
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>

</div>
</body>
</html>
