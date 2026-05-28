{{--
    x-ui.confirm-btn — Bouton avec confirmation (dans un formulaire)
    Props:
      action    : URL de l'action (requis)
      method    : POST|DELETE|PATCH (default: POST)
      confirm   : message de confirmation (requis)
      variant   : primary|danger|ghost... (default: danger)
      size      : sm|md|lg (default: sm)
      icon      : SVG/emoji (optionnel)
--}}
@props([
    'action'  => '',
    'method'  => 'POST',
    'confirm' => 'Êtes-vous sûr ?',
    'variant' => 'danger',
    'size'    => 'sm',
    'icon'    => null,
])

@php
$variantClass = match($variant) {
    'primary'   => 'btn-primary',
    'success'   => 'btn-success',
    'ghost'     => 'btn-ghost',
    'secondary' => 'btn-secondary',
    default     => 'btn-danger',
};
$sizeClass = match($size) {
    'sm' => 'btn-sm',
    'lg' => 'btn-lg',
    default => '',
};
@endphp

<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ addslashes($confirm) }}')">
    @csrf
    @if(!in_array(strtoupper($method), ['GET','POST']))
        @method($method)
    @endif
    <button type="submit" {{ $attributes->merge(['class' => "btn $variantClass $sizeClass"]) }}>
        @if($icon) {!! $icon !!} @endif
        {{ $slot }}
    </button>
</form>
