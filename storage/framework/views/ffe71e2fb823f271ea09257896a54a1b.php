

<?php if($paymentsByMethod->isNotEmpty()): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 fade-up" style="animation-delay:.4s">
    <?php
        $totalP = $paymentsByMethod->sum('total');
        $mpC    = ['#4f46e5','#10b981','#f59e0b','#6366f1','#ef4444','#3b82f6','#ec4899'];
    ?>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h3 class="text-base font-bold text-gray-900">Encaissements par mode de paiement</h3>
            <p class="text-xs text-gray-400 mt-0.5">Ce mois · <span class="font-semibold text-gray-600"><?php echo e(number_format($totalP, 0, ',', ' ')); ?> FCFA</span> total</p>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-<?php echo e(min($paymentsByMethod->count(), 5)); ?> gap-3">
        <?php $__currentLoopData = $paymentsByMethod; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $pm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php $pct = $totalP > 0 ? round(($pm->total/$totalP)*100) : 0; $c = $mpC[$i % count($mpC)]; ?>
        <div class="rounded-xl border border-gray-100 p-4 hover:border-gray-200 transition-colors">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?php echo e($c); ?>"></div>
                <p class="text-xs text-gray-600 font-semibold truncate"><?php echo e(optional($pm->paymentMethod)->name ?? 'Autre'); ?></p>
            </div>
            <p class="text-lg font-black text-gray-900 tabular-nums"><?php echo e(number_format($pm->total, 0, ',', ' ')); ?> F</p>
            <div class="mt-2.5 flex items-center justify-between gap-2">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full progress-fill rounded-full" style="width:<?php echo e($pct); ?>%;background:<?php echo e($c); ?>;animation-delay:.2s"></div>
                </div>
                <span class="text-xs font-bold text-gray-400 flex-shrink-0"><?php echo e($pct); ?>%</span>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php endif; ?>

</div><!-- /space-y-5 -->
<?php /**PATH C:\laragon\www\iboa\resources\views/partials/dashboard/_dashboard-payment-methods.blade.php ENDPATH**/ ?>