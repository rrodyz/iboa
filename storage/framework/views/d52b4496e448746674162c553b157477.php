<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'links' => [],   // tableau de ['label' => 'PO BC-2026-001', 'href' => '/...', 'badge' => 'Reçu', 'badgeColor' => 'emerald']
    'title' => 'Documents liés',
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
    'links' => [],   // tableau de ['label' => 'PO BC-2026-001', 'href' => '/...', 'badge' => 'Reçu', 'badgeColor' => 'emerald']
    'title' => 'Documents liés',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $links = collect($links)->filter(fn($l) => !empty($l['label']));
?>

<?php if($links->isNotEmpty()): ?>
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">🔗 <?php echo e($title); ?></h2>
    </div>
    <div class="divide-y divide-gray-50">
        <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e($link['href'] ?? '#'); ?>" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors">
            <div class="flex items-center gap-3 min-w-0">
                <span class="text-lg"><?php echo e($link['icon'] ?? '📄'); ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?php echo e($link['label']); ?></p>
                    <?php if(!empty($link['subtitle'])): ?>
                    <p class="text-xs text-gray-500 truncate"><?php echo e($link['subtitle']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <?php if(!empty($link['badge'])): ?>
                    <?php $bc = $link['badgeColor'] ?? 'gray'; ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-<?php echo e($bc); ?>-100 text-<?php echo e($bc); ?>-700"><?php echo e($link['badge']); ?></span>
                <?php endif; ?>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php endif; ?>
<?php /**PATH C:\laragon\www\iboa\resources\views/components/document/related.blade.php ENDPATH**/ ?>