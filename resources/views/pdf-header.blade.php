{{--
|  Shared PDF letterhead — included at the top of every list/report PDF view.
|
|  Usage:
|      @include('pdf-header')
|
|  The partial resolves $company itself (Company::first()) so it works even
|  when the caller has not passed a $company variable.
--}}
@php
    if (!isset($company)) {
        $company = \App\Models\Company::first();
    }

    /* ── Logo (base64 so DomPDF can render it inline ; jamais bloquant) ── */
    $__logoBase64 = pdf_image_data($company?->logo);

    /* ── Address line ─────────────────────────────────────────────────── */
    $__addressParts = array_filter([
        $company?->address,
        $company?->city ? (($company?->postal_code ? $company->postal_code . ' ' : '') . $company->city) : null,
        $company?->country,
    ]);
    $__addressLine = implode(' — ', $__addressParts);

    /* ── Contact line ─────────────────────────────────────────────────── */
    $__contactParts = [];
    if ($company?->phone)   $__contactParts[] = 'Tél : ' . $company->phone;
    if ($company?->phone2)  $__contactParts[] = $company->phone2;
    if ($company?->fax)     $__contactParts[] = 'Fax : ' . $company->fax;
    if ($company?->email)   $__contactParts[] = $company->email;
    if ($company?->website) $__contactParts[] = $company->website;
    $__contactLine = implode('  ·  ', $__contactParts);

    /* ── Tax / legal line ─────────────────────────────────────────────── */
    $__legalParts = [];
    if ($company?->rccm)       $__legalParts[] = 'RCCM : ' . $company->rccm;
    if ($company?->ifu)        $__legalParts[] = 'IFU : '  . $company->ifu;
    if ($company?->nif)        $__legalParts[] = 'NIF : '  . $company->nif;
    if ($company?->is_vat_subject && $company?->vat_number)
                               $__legalParts[] = 'TVA : '  . $company->vat_number;
    if ($company?->legal_form) $__legalParts[] = $company->legal_form;
    if ($company?->share_capital)
        $__legalParts[] = 'Capital : ' . number_format($company->share_capital, 0, ',', ' ') . ' ' . ($company->share_capital_currency ?? '');
    $__legalLine = implode('  ·  ', $__legalParts);
@endphp

<table style="width:100%; border-collapse:collapse; margin-bottom:6px; padding-bottom:5px; border-bottom:2px solid #d1d5db;">
    <tr>
        {{-- ── Left: logo + company name + address ── --}}
        <td style="vertical-align:top; width:60%; padding:3px 0;">
            @if($__logoBase64)
            <div style="margin-bottom:4px;">
                <img src="{{ $__logoBase64 }}" style="max-height:40px; max-width:140px;">
            </div>
            @endif
            <div style="font-size:11.5pt; font-weight:bold; color:#111827; line-height:1.2;">
                {{ $company?->name }}
            </div>
            @if($company?->trade_name && $company->trade_name !== $company->name)
            <div style="font-size:8pt; color:#6B7280; margin-top:1px;">
                {{ $company->trade_name }}
                @if($company?->slogan) — <em>{{ $company->slogan }}</em>@endif
            </div>
            @elseif($company?->slogan)
            <div style="font-size:7.5pt; color:#6B7280; font-style:italic; margin-top:1px;">{{ $company->slogan }}</div>
            @endif
            @if($__addressLine)
            <div style="font-size:7.5pt; color:#374151; margin-top:3px;">{{ $__addressLine }}</div>
            @endif
            @if($__contactLine)
            <div style="font-size:7pt; color:#374151; margin-top:1px;">{{ $__contactLine }}</div>
            @endif
        </td>

        {{-- ── Right: tax & legal info ── --}}
        @if($__legalLine)
        <td style="vertical-align:top; text-align:right; width:40%; padding:3px 0; font-size:7pt; color:#374151; line-height:1.5;">
            @foreach($__legalParts as $__lp)
            <div>{{ $__lp }}</div>
            @endforeach
        </td>
        @endif
    </tr>
</table>
