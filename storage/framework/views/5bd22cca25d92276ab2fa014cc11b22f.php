
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['document']));

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

foreach (array_filter((['document']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $history = $document->workflowHistory()->with('user')->get();
?>

<?php if($history->isNotEmpty()): ?>
<div class="mt-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
        <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Historique de validation
    </h3>
    <ol class="relative border-l border-gray-200 ml-3 space-y-4">
        <?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $colorMap = [
                    'creation'       => ['dot'=>'bg-gray-400',   'icon'=>'⚪'],
                    'soumission'     => ['dot'=>'bg-yellow-400',  'icon'=>'📤'],
                    'validation'     => ['dot'=>'bg-green-500',   'icon'=>'✅'],
                    'refus'          => ['dot'=>'bg-orange-500',  'icon'=>'🔄'],
                    'annulation'     => ['dot'=>'bg-red-500',     'icon'=>'❌'],
                    'transformation' => ['dot'=>'bg-blue-500',    'icon'=>'🔁'],
                ];
                $style = $colorMap[$entry->action] ?? ['dot'=>'bg-gray-300', 'icon'=>'•'];
            ?>
            <li class="ml-4 relative">
                
                <div class="absolute -left-[1.4rem] top-1 size-3 rounded-full ring-2 ring-white <?php echo e($style['dot']); ?>"></div>

                <div class="bg-white rounded-lg border border-gray-100 shadow-sm p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-sm font-medium text-gray-900">
                                <?php echo e($style['icon']); ?> <?php echo e($entry->action_label); ?>

                            </span>
                            <?php if($entry->ancien_statut): ?>
                                <span class="ml-2 text-xs text-gray-400">
                                    <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $entry->ancien_statut,'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($entry->ancien_statut),'size' => 'sm']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal27a1c5f204e2cc813651860c6e32c072)): ?>
<?php $attributes = $__attributesOriginal27a1c5f204e2cc813651860c6e32c072; ?>
<?php unset($__attributesOriginal27a1c5f204e2cc813651860c6e32c072); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal27a1c5f204e2cc813651860c6e32c072)): ?>
<?php $component = $__componentOriginal27a1c5f204e2cc813651860c6e32c072; ?>
<?php unset($__componentOriginal27a1c5f204e2cc813651860c6e32c072); ?>
<?php endif; ?>
                                    <svg class="inline size-3 mx-0.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                    <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $entry->nouveau_statut,'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($entry->nouveau_statut),'size' => 'sm']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal27a1c5f204e2cc813651860c6e32c072)): ?>
<?php $attributes = $__attributesOriginal27a1c5f204e2cc813651860c6e32c072; ?>
<?php unset($__attributesOriginal27a1c5f204e2cc813651860c6e32c072); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal27a1c5f204e2cc813651860c6e32c072)): ?>
<?php $component = $__componentOriginal27a1c5f204e2cc813651860c6e32c072; ?>
<?php unset($__componentOriginal27a1c5f204e2cc813651860c6e32c072); ?>
<?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <time class="text-xs text-gray-400 whitespace-nowrap">
                            <?php echo e($entry->created_at->format('d/m/Y H:i')); ?>

                        </time>
                    </div>

                    <div class="mt-1 text-sm text-gray-600">
                        Par <strong><?php echo e($entry->user?->name ?? 'Inconnu'); ?></strong>
                        <?php if($entry->user_role): ?>
                            <span class="text-gray-400">(<?php echo e($entry->user_role); ?>)</span>
                        <?php endif; ?>
                    </div>

                    <?php if($entry->motif): ?>
                        <div class="mt-2 rounded bg-gray-50 border border-gray-100 px-3 py-2 text-sm text-gray-700 italic">
                            "<?php echo e($entry->motif); ?>"
                        </div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ol>
</div>
<?php endif; ?>
<?php /**PATH C:\laragon\www\iboa\resources\views/components/workflow/history.blade.php ENDPATH**/ ?>