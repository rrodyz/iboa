{{--
    x-ui.btn — Bouton universel ERP
    Props:
      variant  : primary|secondary|danger|success|ghost|amber|sky (default: secondary)
      size     : sm|md|lg (default: md)
      href     : si fourni, génère un <a>; sinon <button>
      type     : submit|button|reset (default: button)
      icon     : SVG HTML (optionnel)
      loading  : afficher spinner
      disabled : désactiver
      class    : classes additionnelles
--}}
@props([
    'variant'  => 'secondary',
    'size'     => 'md',
    'href'     => null,
    'type'     => 'button',
    'icon'     => null,
    'loading'  => false,
    'disabled' => false,
])

@php
$variantClass = match($variant) {
    'primary'   => 'btn-primary',
    'danger'    => 'btn-danger',
    'success'   => 'btn-success',
    'ghost'     => 'btn-ghost',
    'amber'     => 'btn-amber',
    'sky'       => 'btn-sky',
    'purple'    => 'btn-purple',
    'teal'      => 'btn-teal',
    default     => 'btn-secondary',
};
$sizeClass = match($size) {
    'sm' => 'btn-sm',
    'lg' => 'btn-lg',
    default => '',
};
$base = 'btn ' . $variantClass . ' ' . $sizeClass;
@endphp

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $base]) }}>
    @if($icon) {!! $icon !!} @endif
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $disabled || $loading ? 'disabled' : '' }} {{ $attributes->merge(['class' => $base]) }}>
    @if($loading)
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    @elseif($icon)
        {!! $icon !!}
    @endif
    {{ $slot }}
</button>
@endif
