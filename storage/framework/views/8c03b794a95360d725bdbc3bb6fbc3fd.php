<?php $__env->startSection('title', 'État des impayés clients'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('reports.index')); ?>" class="hover:text-gray-700">Rapports</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Impayés clients</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">

    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">État des impayés clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">Factures avec solde restant dû — FCFA</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="<?php echo e(request()->fullUrlWithQuery(['export' => 'excel'])); ?>"
               class="inline-flex items-center gap-2 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export Excel
            </a>
            <a href="<?php echo e(request()->fullUrlWithQuery(['export' => 'pdf'])); ?>"
               class="inline-flex items-center gap-2 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Export PDF
            </a>
        </div>
    </div>

    
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">À la date du</label>
                <input type="date" name="as_of" value="<?php echo e($asOf); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-400">
                    <option value="">— Tous les clients —</option>
                    <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($c->id); ?>" <?php echo e($clientId == $c->id ? 'selected' : ''); ?>><?php echo e($c->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Appliquer</button>
            <a href="<?php echo e(route('reports.impayes')); ?>" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg">Réinitialiser</a>
        </div>
    </form>

    
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nb factures</p>
            <p class="mt-1 text-xl font-bold text-indigo-700"><?php echo e($totals['count']); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total TTC</p>
            <p class="mt-1 text-xl font-bold text-blue-700"><?php echo e(number_format($totals['total_ttc'], 0, ',', ' ')); ?> F</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Déjà réglé</p>
            <p class="mt-1 text-xl font-bold text-emerald-700"><?php echo e(number_format($totals['paid'], 0, ',', ' ')); ?> F</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Restant dû</p>
            <p class="mt-1 text-xl font-bold text-rose-700"><?php echo e(number_format($totals['remaining'], 0, ',', ' ')); ?> F</p>
        </div>
    </div>

    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-rose-700 text-white">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">N° Facture</th>
                    <th class="px-4 py-3 text-center font-semibold">Date émission</th>
                    <th class="px-4 py-3 text-center font-semibold">Échéance</th>
                    <th class="px-4 py-3 text-left font-semibold">Client</th>
                    <th class="px-4 py-3 text-left font-semibold">Téléphone</th>
                    <th class="px-4 py-3 text-right font-semibold">Total TTC</th>
                    <th class="px-4 py-3 text-right font-semibold">Réglé</th>
                    <th class="px-4 py-3 text-right font-semibold">Restant dû</th>
                    <th class="px-4 py-3 text-center font-semibold">Retard</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-gray-50 <?php echo e($r->jours_retard > 30 ? 'bg-rose-50' : ''); ?>">
                    <td class="px-4 py-2.5 font-medium text-indigo-700">
                        <a href="<?php echo e(route('ventes.factures.show', $r->id)); ?>" class="hover:underline"><?php echo e($r->number); ?></a>
                    </td>
                    <td class="px-4 py-2.5 text-center text-gray-600"><?php echo e($r->issued_at?->format('d/m/Y')); ?></td>
                    <td class="px-4 py-2.5 text-center <?php echo e($r->jours_retard > 0 ? 'text-rose-700 font-medium' : 'text-gray-600'); ?>">
                        <?php echo e($r->due_at?->format('d/m/Y')); ?>

                    </td>
                    <td class="px-4 py-2.5 font-medium text-gray-800"><?php echo e($r->client?->name ?? '—'); ?></td>
                    <td class="px-4 py-2.5 text-gray-500"><?php echo e($r->client?->phone ?? '—'); ?></td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-700"><?php echo e(number_format($r->total_ttc, 0, ',', ' ')); ?></td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-emerald-700"><?php echo e(number_format($r->paid_amount, 0, ',', ' ')); ?></td>
                    <td class="px-4 py-2.5 text-right tabular-nums font-bold text-rose-700"><?php echo e(number_format($r->remaining_amount, 0, ',', ' ')); ?></td>
                    <td class="px-4 py-2.5 text-center">
                        <?php if($r->jours_retard > 0): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                <?php echo e($r->jours_retard > 60 ? 'bg-rose-200 text-rose-900' : ($r->jours_retard > 30 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800')); ?>">
                                <?php echo e($r->jours_retard); ?> j
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                À échoir
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-gray-400">Aucune facture impayée</td>
                </tr>
                <?php endif; ?>
            </tbody>
            <?php if($rows->count()): ?>
            <tfoot class="bg-rose-900 text-white font-bold">
                <tr>
                    <td class="px-4 py-3" colspan="5">TOTAL (<?php echo e($totals['count']); ?> facture<?php echo e($totals['count'] > 1 ? 's' : ''); ?>)</td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo e(number_format($totals['total_ttc'], 0, ',', ' ')); ?></td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo e(number_format($totals['paid'], 0, ',', ' ')); ?></td>
                    <td class="px-4 py-3 text-right tabular-nums"><?php echo e(number_format($totals['remaining'], 0, ',', ' ')); ?></td>
                    <td></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/reports/impayes.blade.php ENDPATH**/ ?>