{{--
    x-ui.table-wrapper — Conteneur de tableau avec overflow et style harmonisé
    Props:
      sticky : en-tête sticky (default: true)
      class  : classes additionnelles
--}}
@props([
    'sticky' => true,
])
<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="tbl {{ $sticky ? 'tbl-sticky' : '' }}">
            {{ $slot }}
        </table>
    </div>
</div>
