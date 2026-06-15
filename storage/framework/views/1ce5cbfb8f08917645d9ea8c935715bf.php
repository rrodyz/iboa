<?php $__env->startSection('title', 'Tableau de bord'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <span class="text-gray-900 font-semibold">Tableau de bord</span>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
    <?php echo $__env->make('partials.dashboard._dashboard-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>


<div x-data="kpiAutoRefresh()" x-init="init()" class="space-y-5">

    
    <div class="flex items-center justify-end gap-2 text-xs text-gray-400 -mb-3 pr-1" x-cloak>
        
        <span x-show="status === 'loading'" class="flex items-center gap-1.5">
            <svg class="w-3 h-3 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span>Actualisation…</span>
        </span>
        
        <span x-show="status === 'ok' && refreshedAt" class="flex items-center gap-1 text-emerald-500">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <span x-text="'Actualisé à ' + refreshedAt"></span>
        </span>
        
        <span x-show="status === 'error'" class="text-rose-400 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12A9 9 0 113 12a9 9 0 0118 0z"/>
            </svg>
            <span>Hors ligne</span>
        </span>
        
        <button @click="fetchNow()"
                class="ml-2 p-1 rounded-lg hover:bg-gray-100 transition-colors"
                title="Actualiser maintenant">
            <svg class="w-3.5 h-3.5 text-gray-400" :class="status==='loading'?'animate-spin':''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </button>
    </div>

    <?php echo $__env->make('partials.dashboard._dashboard-hero', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-kpi-cards', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-charts-row1', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-charts-row2', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-tables', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-top-lists', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-payment-methods', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>

    <?php echo $__env->make('partials.dashboard._dashboard-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/dashboard.blade.php ENDPATH**/ ?>