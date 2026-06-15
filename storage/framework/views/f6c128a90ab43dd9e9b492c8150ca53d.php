
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'status' => 'brouillon',
    'label'  => null,
    'size'   => 'md',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'status' => 'brouillon',
    'label'  => null,
    'size'   => 'md',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $colorMap = [
        'brouillon'             => 'bg-gray-100   text-gray-700   ring-gray-300',
        'en_attente_validation' => 'bg-yellow-100 text-yellow-800 ring-yellow-400 animate-pulse',
        'envoye'                => 'bg-blue-100   text-blue-700   ring-blue-300',
        'confirme'              => 'bg-green-100  text-green-700  ring-green-300',
        'valide'                => 'bg-green-100  text-green-700  ring-green-300',
        'validee'               => 'bg-green-100  text-green-700  ring-green-300',
        'emise'                 => 'bg-blue-100   text-blue-700   ring-blue-300',
        'envoyee'               => 'bg-indigo-100 text-indigo-700 ring-indigo-300',
        'accepte'               => 'bg-green-100  text-green-700  ring-green-300',
        'partiellement_payee'   => 'bg-amber-100  text-amber-700  ring-amber-300',
        'payee'                 => 'bg-green-200  text-green-800  ring-green-400',
        'en_retard'             => 'bg-red-100    text-red-700    ring-red-300',
        'partiellement_livre'   => 'bg-indigo-100 text-indigo-700 ring-indigo-300',
        'livre'                 => 'bg-teal-100   text-teal-700   ring-teal-300',
        'facture'               => 'bg-purple-100 text-purple-700 ring-purple-300',
        'applique'              => 'bg-teal-100   text-teal-700   ring-teal-300',
        'converti'              => 'bg-green-100  text-green-700  ring-green-300',
        'annule'                => 'bg-red-100    text-red-600    ring-red-300',
        'annulee'               => 'bg-red-100    text-red-600    ring-red-300',
        'refuse'                => 'bg-red-100    text-red-600    ring-red-300',
        'expire'                => 'bg-orange-100 text-orange-700 ring-orange-300',
    ];

    $defaultLabels = [
        'brouillon'             => 'Brouillon',
        'en_attente_validation' => 'En attente',
        'envoye'                => 'Envoyé',
        'confirme'              => 'Confirmé',
        'valide'                => 'Validé',
        'validee'               => 'Validée',
        'emise'                 => 'Émise',
        'envoyee'               => 'Envoyée',
        'accepte'               => 'Accepté',
        'partiellement_payee'   => 'Part. payée',
        'payee'                 => 'Payée',
        'en_retard'             => 'En retard',
        'partiellement_livre'   => 'Part. livré',
        'livre'                 => 'Livré',
        'facture'               => 'Facturé',
        'applique'              => 'Appliqué',
        'converti'              => 'Converti',
        'annule'                => 'Annulé',
        'annulee'               => 'Annulée',
        'refuse'                => 'Refusé',
        'expire'                => 'Expiré',
    ];

    $colors     = $colorMap[$status]    ?? 'bg-gray-100 text-gray-600 ring-gray-200';
    $text       = $label                ?? $defaultLabels[$status] ?? ucfirst($status);
    $sizeClass  = $size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs font-medium';
?>

<span class="inline-flex items-center rounded-full ring-1 ring-inset <?php echo e($colors); ?> <?php echo e($sizeClass); ?> gap-1">
    <?php if($status === 'en_attente_validation'): ?>
        <span class="size-1.5 rounded-full bg-yellow-500 inline-block"></span>
    <?php elseif(in_array($status, ['payee', 'valide', 'validee', 'confirme', 'accepte', 'converti'])): ?>
        <span class="size-1.5 rounded-full bg-green-500 inline-block"></span>
    <?php elseif(in_array($status, ['annule', 'annulee', 'en_retard', 'refuse'])): ?>
        <span class="size-1.5 rounded-full bg-red-500 inline-block"></span>
    <?php endif; ?>
    <?php echo e($text); ?>

</span>
<?php /**PATH C:\laragon\www\iboa\resources\views/components/workflow/status-badge.blade.php ENDPATH**/ ?>