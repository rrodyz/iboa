<?php $__env->startSection('title', 'Rapports production'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('production.dashboard')); ?>" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapports</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Rapports de production</h1>
            <p class="text-sm text-gray-500 mt-0.5"><?php echo e($report['title']); ?> — du <?php echo e(\Carbon\Carbon::parse($from)->format('d/m/Y')); ?> au <?php echo e(\Carbon\Carbon::parse($to)->format('d/m/Y')); ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?php echo e(route('production.reports', array_merge(request()->only('type','from','to'), ['export'=>'pdf']))); ?>" class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">PDF</a>
            <a href="<?php echo e(route('production.reports', array_merge(request()->only('type','from','to'), ['export'=>'excel']))); ?>" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Excel</a>
        </div>
    </div>

    
    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Type de rapport</label>
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-56" onchange="this.form.submit()">
                <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($type===$k): echo 'selected'; endif; ?>><?php echo e($lbl); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Du</label>
            <input type="date" name="from" value="<?php echo e($from); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Au</label>
            <input type="date" name="to" value="<?php echo e($to); ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Générer</button>
    </form>

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <?php $__currentLoopData = $report['headers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th class="<?php echo e(in_array($i, $report['numeric']) ? 'text-right' : 'text-left'); ?>"><?php echo e($h); ?></th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $report['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td class="<?php echo e(in_array($i, $report['numeric']) ? 'text-right font-mono tabular-nums text-gray-900' : 'text-gray-700'); ?>">
                            <?php echo e(in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell); ?>

                        </td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="<?php echo e(count($report['headers'])); ?>" class="px-4 py-12 text-center text-gray-400">Aucune donnée sur la période.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if($report['totals']): ?>
                <tfoot>
                    <tr class="font-semibold bg-gray-50">
                        <?php $__currentLoopData = $report['totals']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td class="<?php echo e(in_array($i, $report['numeric']) ? 'text-right font-mono tabular-nums text-gray-900' : 'text-gray-700'); ?>">
                            <?php echo e(in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell); ?>

                        </td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/production/reports/index.blade.php ENDPATH**/ ?>