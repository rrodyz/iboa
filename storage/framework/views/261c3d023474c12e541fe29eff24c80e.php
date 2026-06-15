<?php $__env->startSection('title', 'Nouveau devis'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('ventes.devis.index')); ?>" class="hover:text-gray-700">Devis</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Nouveau devis</h1>
        <p class="text-sm text-gray-500 mt-0.5">Créez un devis et imputez-le sur un client</p>
    </div>

    <form method="POST" action="<?php echo e(route('ventes.devis.store')); ?>">
        <?php echo csrf_field(); ?>
        <?php if (isset($component)) { $__componentOriginal78127ff16d3eb87434619d2552f4f7d4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal78127ff16d3eb87434619d2552f4f7d4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.form-guard','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('form-guard'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal78127ff16d3eb87434619d2552f4f7d4)): ?>
<?php $attributes = $__attributesOriginal78127ff16d3eb87434619d2552f4f7d4; ?>
<?php unset($__attributesOriginal78127ff16d3eb87434619d2552f4f7d4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal78127ff16d3eb87434619d2552f4f7d4)): ?>
<?php $component = $__componentOriginal78127ff16d3eb87434619d2552f4f7d4; ?>
<?php unset($__componentOriginal78127ff16d3eb87434619d2552f4f7d4); ?>
<?php endif; ?>
        <?php echo $__env->make('ventes.devis._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/ventes/devis/create.blade.php ENDPATH**/ ?>