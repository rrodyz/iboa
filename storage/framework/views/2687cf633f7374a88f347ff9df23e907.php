<?php $__env->startSection('title', 'Échéances de paiement'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('achats.dashboard')); ?>" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Échéances</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php $fmt = fn($n) => number_format((float) $n, 0, ',', ' '); ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">💰 Échéances de paiement</h1>
            <p class="text-sm text-gray-500">Cadenciers de paiement fournisseur — vue d'ensemble des prochaines échéances.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Fenêtre (jours)</label>
                <select name="window" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <?php $__currentLoopData = [7,15,30,60,90,180]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($w); ?>" <?php echo e($window==$w?'selected':''); ?>><?php echo e($w); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </form>
    </div>

    
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border-2 <?php echo e(count($overdue) > 0 ? 'border-red-300' : 'border-emerald-200'); ?> p-5">
            <p class="text-xs font-medium <?php echo e(count($overdue) > 0 ? 'text-red-600' : 'text-emerald-600'); ?> uppercase">⏰ En retard</p>
            <p class="mt-1 text-2xl font-bold tabular-nums <?php echo e(count($overdue) > 0 ? 'text-red-700' : 'text-emerald-700'); ?>"><?php echo e(count($overdue)); ?></p>
            <p class="text-xs <?php echo e(count($overdue) > 0 ? 'text-red-500' : 'text-emerald-500'); ?> mt-0.5"><?php echo e($fmt($totalOverdue)); ?> FCFA</p>
        </div>
        <div class="bg-white rounded-xl border border-amber-200 p-5">
            <p class="text-xs font-medium text-amber-600 uppercase">📅 À venir (<?php echo e($window); ?> j)</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-700"><?php echo e(count($upcoming)); ?></p>
            <p class="text-xs text-amber-500 mt-0.5"><?php echo e($fmt($totalUpcoming)); ?> FCFA</p>
        </div>
    </div>

    
    <?php if($overdue->isNotEmpty()): ?>
    <div class="bg-white rounded-xl border-2 border-red-300 overflow-hidden">
        <div class="px-5 py-3 border-b border-red-200 bg-red-50">
            <h2 class="text-sm font-semibold text-red-800">🛑 Échéances en retard (<?php echo e(count($overdue)); ?>)</h2>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">Facture FF</th>
                    <th class="px-4 py-2 text-left">Fournisseur</th>
                    <th class="px-4 py-2 text-left">Échéance n°</th>
                    <th class="px-4 py-2 text-right">Montant</th>
                    <th class="px-4 py-2 text-right">Payé</th>
                    <th class="px-4 py-2 text-right">Reste dû</th>
                    <th class="px-4 py-2 text-right">Échue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php $__currentLoopData = $overdue; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $remain = $s->amount - $s->paid_amount; ?>
                <tr class="hover:bg-red-50/30">
                    <td class="px-4 py-2 font-mono text-xs">
                        <a href="<?php echo e(route('achats.factures-fournisseurs.show', $s->supplierInvoice)); ?>" class="text-blue-700 hover:underline"><?php echo e($s->supplierInvoice?->number); ?></a>
                    </td>
                    <td class="px-4 py-2 text-xs"><?php echo e($s->supplierInvoice?->supplier?->name); ?></td>
                    <td class="px-4 py-2 text-xs"><?php echo e($s->label ?? 'Éch. '.$s->installment_number); ?></td>
                    <td class="px-4 py-2 text-right tabular-nums"><?php echo e($fmt($s->amount)); ?></td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-500"><?php echo e($fmt($s->paid_amount)); ?></td>
                    <td class="px-4 py-2 text-right tabular-nums font-semibold text-red-700"><?php echo e($fmt($remain)); ?></td>
                    <td class="px-4 py-2 text-right text-xs text-red-700 font-medium"><?php echo e($s->due_date?->format('d/m/Y')); ?> <span class="text-red-500">(+<?php echo e($s->due_date?->diffInDays(now())); ?> j)</span></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">📅 Échéances à venir (<?php echo e(count($upcoming)); ?>)</h2>
        </div>
        <?php if($upcoming->isEmpty()): ?>
            <div class="p-8 text-center text-emerald-700 text-sm">✓ Aucune échéance dans la fenêtre.</div>
        <?php else: ?>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">Facture FF</th>
                    <th class="px-4 py-2 text-left">Fournisseur</th>
                    <th class="px-4 py-2 text-left">Échéance</th>
                    <th class="px-4 py-2 text-right">Montant</th>
                    <th class="px-4 py-2 text-right">Reste dû</th>
                    <th class="px-4 py-2 text-right">Dans</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php $__currentLoopData = $upcoming; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $remain = $s->amount - $s->paid_amount; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-mono text-xs">
                        <a href="<?php echo e(route('achats.factures-fournisseurs.show', $s->supplierInvoice)); ?>" class="text-blue-700 hover:underline"><?php echo e($s->supplierInvoice?->number); ?></a>
                    </td>
                    <td class="px-4 py-2 text-xs"><?php echo e($s->supplierInvoice?->supplier?->name); ?></td>
                    <td class="px-4 py-2 text-xs"><?php echo e($s->label ?? 'Éch. '.$s->installment_number); ?></td>
                    <td class="px-4 py-2 text-right tabular-nums"><?php echo e($fmt($s->amount)); ?></td>
                    <td class="px-4 py-2 text-right tabular-nums font-medium text-amber-700"><?php echo e($fmt($remain)); ?></td>
                    <td class="px-4 py-2 text-right text-xs text-gray-600"><?php echo e($s->due_date?->format('d/m/Y')); ?> <span class="text-gray-400">(<?php echo e(now()->diffInDays($s->due_date, false)); ?> j)</span></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/achats/schedules/upcoming.blade.php ENDPATH**/ ?>