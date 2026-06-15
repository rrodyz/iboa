<?php $__env->startSection('title', 'Grand livre'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-500">Comptabilité</span>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Grand livre</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">

    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Grand livre</h1>
            <?php if($account): ?>
                <p class="text-sm text-gray-500 mt-0.5">Compte <?php echo e($account->code); ?> — <?php echo e($account->name); ?></p>
            <?php elseif($accountGroups->isNotEmpty()): ?>
                <p class="text-sm text-gray-500 mt-0.5"><?php echo e($accountGroups->count()); ?> compte(s) avec mouvements</p>
            <?php endif; ?>
        </div>
        <a href="<?php echo e(route('comptabilite.balance')); ?>"
           class="self-start inline-flex items-center gap-1.5 text-sm text-violet-600 hover:text-violet-700 font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Balance générale
        </a>
    </div>

    
    <?php
        $hasFilters = ($accountId || $classId || $search || $dateFrom || $dateTo);
    ?>
    <form method="GET" id="filter-form" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

            
            <select name="class_id"
                    onchange="this.form.querySelector('[name=account_id]').value=''; this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                <option value="">— Toutes les classes —</option>
                <?php $__currentLoopData = $classes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $class): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($class->id); ?>" <?php echo e(($classId ?? '') == $class->id ? 'selected' : ''); ?>>
                    Classe <?php echo e($class->number); ?> — <?php echo e($class->name); ?>

                </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            
            <select name="account_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                <option value="">— Tous les comptes —</option>
                <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($acc->id); ?>" <?php echo e($accountId == $acc->id ? 'selected' : ''); ?>>
                    <?php echo e($acc->code); ?> — <?php echo e($acc->name); ?>

                </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>

            
            <input type="text" name="search" value="<?php echo e($search ?? ''); ?>"
                   placeholder="Libellé, n° pièce..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">

            
            <input type="date" name="date_from" value="<?php echo e($dateFrom ?? ''); ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">

            
            <div class="flex gap-2">
                <input type="date" name="date_to" value="<?php echo e($dateTo ?? ''); ?>"
                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
                <button type="submit"
                        class="bg-violet-600 hover:bg-violet-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                    Afficher
                </button>
                <?php if($hasFilters): ?>
                <a href="<?php echo e(route('comptabilite.grand-livre')); ?>"
                   class="flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors"
                   title="Réinitialiser les filtres">
                    ✕
                </a>
                <?php endif; ?>
            </div>
        </div>

        
        <?php if($hasFilters): ?>
        <div class="mt-3 flex flex-wrap gap-2 items-center">
            <span class="text-xs text-gray-400">Filtres actifs :</span>
            <?php if($classId): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 rounded-full text-xs font-medium">
                Classe <?php echo e($classes->firstWhere('id', $classId)?->number); ?>

            </span>
            <?php endif; ?>
            <?php if($accountId): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 rounded-full text-xs font-medium">
                <?php echo e($accounts->firstWhere('id', $accountId)?->code); ?>

            </span>
            <?php endif; ?>
            <?php if($search): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 rounded-full text-xs font-medium">
                "<?php echo e($search); ?>"
            </span>
            <?php endif; ?>
            <?php if($dateFrom || $dateTo): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-violet-100 text-violet-700 rounded-full text-xs font-medium">
                <?php echo e($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '…'); ?>

                →
                <?php echo e($dateTo   ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y')   : '…'); ?>

            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        
        <div class="mt-3 flex justify-end gap-2">
            <a href="<?php echo e(route('comptabilite.grand-livre.export', request()->query())); ?>"
               class="inline-flex items-center gap-1.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exporter Excel
            </a>
            <a href="<?php echo e(route('comptabilite.grand-livre.pdf', request()->query())); ?>"
               class="inline-flex items-center gap-1.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
        </div>
    </form>

    
    <?php if($account): ?>
    <?php
        $totalDebit  = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $balance     = $totalDebit - $totalCredit;
    ?>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center gap-3 mb-3">
            <span class="font-mono text-xl font-bold text-violet-700"><?php echo e($account->code); ?></span>
            <span class="text-gray-900 font-semibold"><?php echo e($account->name); ?></span>
            <span class="ml-auto text-xs text-gray-400"><?php echo e($lines->count()); ?> ligne(s)</span>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center p-3 bg-blue-50 rounded-lg">
                <p class="text-xs text-gray-500 mb-1">Total Débit</p>
                <p class="font-bold tabular-nums text-blue-700"><?php echo e(number_format($totalDebit, 0, ',', ' ')); ?></p>
            </div>
            <div class="text-center p-3 bg-red-50 rounded-lg">
                <p class="text-xs text-gray-500 mb-1">Total Crédit</p>
                <p class="font-bold tabular-nums text-red-700"><?php echo e(number_format($totalCredit, 0, ',', ' ')); ?></p>
            </div>
            <div class="text-center p-3 <?php echo e($balance >= 0 ? 'bg-green-50' : 'bg-orange-50'); ?> rounded-lg">
                <p class="text-xs text-gray-500 mb-1">Solde</p>
                <p class="font-bold tabular-nums <?php echo e($balance >= 0 ? 'text-green-700' : 'text-orange-700'); ?>">
                    <?php if($balance == 0): ?>
                        <span class="text-gray-400">Équilibré</span>
                    <?php else: ?>
                        <?php echo e(number_format(abs($balance), 0, ',', ' ')); ?>

                        <span class="text-xs font-normal ml-0.5"><?php echo e($balance >= 0 ? 'Débiteur' : 'Créditeur'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <?php echo $__env->make('comptabilite._grand-livre-table', ['lines' => $lines], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <?php elseif($accountGroups->isNotEmpty()): ?>
    <?php
        $currentClassNum = null;
        $grandDebit      = $accountGroups->sum('total_debit');
        $grandCredit     = $accountGroups->sum('total_credit');
        $grandBalance    = $grandDebit - $grandCredit;
    ?>

    
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3"
         x-data="{
             allOpen: false,
             toggleAll() {
                 this.allOpen = !this.allOpen;
                 document.querySelectorAll('[data-gl-account]').forEach(el => {
                     el._x_dataStack[0].open = this.allOpen;
                 });
             }
         }">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <span class="text-gray-500">
                <span class="font-semibold text-gray-900"><?php echo e($accountGroups->count()); ?></span> compte(s) avec mouvements
            </span>

            
            <button type="button" @click="toggleAll()"
                    class="inline-flex items-center gap-1.5 text-xs text-violet-600 hover:text-violet-800 border border-violet-200 hover:border-violet-400 rounded-lg px-3 py-1.5 transition-colors">
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="allOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                <span x-text="allOpen ? 'Tout replier' : 'Tout déplier'">Tout déplier</span>
            </button>

            <span class="ml-auto flex flex-wrap gap-4 tabular-nums">
                <span class="text-blue-700 font-semibold">
                    Total D : <?php echo e(number_format($grandDebit, 0, ',', ' ')); ?>

                </span>
                <span class="text-red-700 font-semibold">
                    Total C : <?php echo e(number_format($grandCredit, 0, ',', ' ')); ?>

                </span>
                <?php if($grandBalance != 0): ?>
                <span class="<?php echo e($grandBalance >= 0 ? 'text-green-700' : 'text-orange-700'); ?> font-semibold">
                    Solde : <?php echo e(number_format(abs($grandBalance), 0, ',', ' ')); ?> <?php echo e($grandBalance >= 0 ? 'D' : 'C'); ?>

                </span>
                <?php else: ?>
                <span class="text-gray-400 font-semibold">Équilibré</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <?php $__currentLoopData = $accountGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $classNum = substr($group['account']->code, 0, 1); ?>

    
    <?php if($classNum !== $currentClassNum): ?>
    <?php $currentClassNum = $classNum; ?>
    <div class="px-3 py-1.5 bg-violet-100 rounded-lg text-xs font-bold text-violet-800 uppercase tracking-wide flex items-center gap-2">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        Classe <?php echo e($classNum); ?>

    </div>
    <?php endif; ?>

    
    <?php
        $bal      = $group['total_debit'] - $group['total_credit'];
        $maxLines = 8;   // lignes visibles avant "Voir tout"
        $preview  = $group['lines']->take($maxLines);
        $hasMore  = $group['lines']->count() > $maxLines;
        $moreCount = $group['lines']->count() - $maxLines;
    ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden"
         x-data="{ open: false }"
         data-gl-account>

        
        <button type="button" @click="open = !open"
                class="w-full px-4 py-3 bg-gray-50 border-b border-gray-100 hover:bg-gray-100 transition-colors text-left">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <svg class="w-4 h-4 flex-shrink-0 text-gray-400 transition-transform duration-200"
                         :class="open ? 'rotate-0' : '-rotate-90'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span class="font-mono font-bold text-violet-700 flex-shrink-0"><?php echo e($group['account']->code); ?></span>
                    <span class="text-gray-800 font-medium truncate"><?php echo e($group['account']->name); ?></span>
                    <span class="flex-shrink-0 text-xs text-gray-400 bg-gray-200 rounded-full px-2 py-0.5">
                        <?php echo e($group['lines']->count()); ?> ligne(s)
                    </span>
                </div>
                <div class="flex-shrink-0 flex gap-4 text-xs tabular-nums" @click.stop>
                    <span class="text-blue-700 font-semibold">D: <?php echo e(number_format($group['total_debit'], 0, ',', ' ')); ?></span>
                    <span class="text-red-700 font-semibold">C: <?php echo e(number_format($group['total_credit'], 0, ',', ' ')); ?></span>
                    <?php if($bal != 0): ?>
                    <span class="<?php echo e($bal >= 0 ? 'text-green-700' : 'text-orange-700'); ?> font-semibold">
                        <?php echo e(number_format(abs($bal), 0, ',', ' ')); ?> <?php echo e($bal >= 0 ? 'D' : 'C'); ?>

                    </span>
                    <?php else: ?>
                    <span class="text-gray-400 font-semibold">Équilibré</span>
                    <?php endif; ?>
                </div>
            </div>
        </button>

        
        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <?php echo $__env->make('comptabilite._grand-livre-table', ['lines' => $preview], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php if($hasMore): ?>
            <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
                <span class="text-xs text-gray-500">
                    Affichage limité à <?php echo e($maxLines); ?> lignes sur <?php echo e($group['lines']->count()); ?>

                </span>
                <a href="<?php echo e(route('comptabilite.grand-livre', array_merge(request()->query(), ['account_id' => $group['account']->id]))); ?>"
                   class="inline-flex items-center gap-1 text-xs font-semibold text-violet-600 hover:text-violet-800 transition-colors">
                    Voir les <?php echo e($moreCount); ?> ligne(s) restante(s)
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <?php else: ?>
    <div class="bg-white rounded-xl border border-gray-200 py-16 text-center">
        <div class="flex flex-col items-center gap-3 text-gray-400">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-medium">Aucun mouvement comptable trouvé</p>
            <?php if($hasFilters): ?>
            <a href="<?php echo e(route('comptabilite.grand-livre')); ?>"
               class="text-violet-600 hover:text-violet-700 text-sm">Effacer les filtres</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/comptabilite/grand-livre.blade.php ENDPATH**/ ?>