

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 fade-up" style="animation-delay:.32s">

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"
         x-data="{ tab: 'factures' }">

        
        <div class="px-5 pt-4 pb-0 border-b border-gray-100">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold text-gray-900">Suivi paiements</h3>
                <a href="<?php echo e(route('ventes.factures.index')); ?>" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700">Tout voir →</a>
            </div>
            <div class="flex gap-0">
                <button @click="tab='factures'"
                        :class="tab==='factures' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-400 hover:text-gray-600'"
                        class="px-4 py-2 text-xs font-semibold transition-colors">
                    Factures à encaisser
                    <?php if($facturesAEncaisser->count() > 0): ?>
                    <span class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-100 text-rose-600 text-xs font-black"><?php echo e($facturesAEncaisser->count()); ?></span>
                    <?php endif; ?>
                </button>
                <button @click="tab='paiements'"
                        :class="tab==='paiements' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-400 hover:text-gray-600'"
                        class="px-4 py-2 text-xs font-semibold transition-colors">
                    Paiements reçus
                </button>
            </div>
        </div>

        
        <div x-show="tab==='factures'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <?php if($facturesAEncaisser->isEmpty()): ?>
            <div class="flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">Tout est encaissé ✓</p>
            </div>
            <?php else: ?>
            <div class="tbl-rx">
            <table class="w-full min-w-[420px] text-sm">
                <thead class="bg-gray-50/70">
                    <tr>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Facture</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Échéance</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Reste dû</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $facturesAEncaisser; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $facture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $overdue = $facture->due_at?->isPast();
                        $days    = $overdue ? now()->diffInDays($facture->due_at) : 0;
                    ?>
                    <tr class="data-row border-b border-gray-50 last:border-0 <?php echo e($overdue ? 'bg-rose-50/20' : ''); ?>">
                        <td class="px-5 py-3">
                            <a href="<?php echo e(route('ventes.factures.show', $facture)); ?>"
                               class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-800"><?php echo e($facture->number); ?></a>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-indigo-50 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-black text-indigo-500"><?php echo e(strtoupper(substr(optional($facture->client)->name ?? '?', 0, 2))); ?></span>
                                </div>
                                <span class="text-xs text-gray-700 font-medium truncate max-w-[100px]"><?php echo e(optional($facture->client)->name ?? '—'); ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <?php if($facture->due_at): ?>
                            <span class="text-xs font-semibold <?php echo e($overdue ? 'text-rose-600' : 'text-gray-600'); ?>"><?php echo e($facture->due_at->format('d/m/Y')); ?></span>
                            <?php if($overdue): ?><br><span class="text-xs text-rose-400">+<?php echo e($days); ?>j</span><?php endif; ?>
                            <?php else: ?><span class="text-gray-300 text-xs">—</span><?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-xs font-black <?php echo e($overdue ? 'text-rose-600' : 'text-gray-800'); ?> tabular-nums whitespace-nowrap"><?php echo e(number_format($facture->remaining_amount, 0, ',', ' ')); ?> F</span>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

        
        <div x-show="tab==='paiements'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <?php if($derniersEncaissements->isEmpty()): ?>
            <div class="flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">Aucun paiement ce mois</p>
            </div>
            <?php else: ?>
            <div class="tbl-rx">
            <table class="w-full min-w-[380px] text-sm">
                <thead class="bg-gray-50/70">
                    <tr>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Moyen</th>
                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Date</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $derniersEncaissements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="data-row border-b border-gray-50 last:border-0">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-black text-emerald-600"><?php echo e(strtoupper(substr(optional($p->client)->name ?? '?', 0, 2))); ?></span>
                                </div>
                                <span class="text-xs text-gray-700 font-medium truncate max-w-[90px]"><?php echo e(optional($p->client)->name ?? '—'); ?></span>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                <?php echo e(optional($p->paymentMethod)->name ?? '—'); ?>

                            </span>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="text-xs text-gray-500"><?php echo e(\Carbon\Carbon::parse($p->payment_date)->format('d/m/Y')); ?></span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-xs font-black text-emerald-700 tabular-nums whitespace-nowrap"><?php echo e(number_format($p->amount, 0, ',', ' ')); ?> F</span>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Dernières commandes</h3>
                    <p class="text-xs text-gray-400"><?php echo e($nbCommandesEnCours); ?> en cours</p>
                </div>
            </div>
            <a href="<?php echo e(route('ventes.commandes.index')); ?>" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700">Tout voir →</a>
        </div>

        <?php if($dernieresCommandes->isEmpty()): ?>
        <div class="flex flex-col items-center justify-center py-12 gap-3">
            <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">Aucune commande</p>
        </div>
        <?php else: ?>
        <div class="tbl-rx">
        <table class="w-full min-w-[420px] text-sm">
            <thead class="bg-gray-50/70">
                <tr>
                    <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Commande</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">Montant</th>
                    <th class="px-5 py-2.5 text-center text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $dernieresCommandes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $commande): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    [$sc, $sl] = match($commande->status) {
                        'brouillon'           => ['bg-gray-100 text-gray-500',       'Brouillon'],
                        'confirme'            => ['bg-blue-100 text-blue-700',       'Confirmée'],
                        'en_preparation'      => ['bg-amber-100 text-amber-700',     'En prépa.'],
                        'partiellement_livre' => ['bg-orange-100 text-orange-700',   'Part. livrée'],
                        'livre'               => ['bg-emerald-100 text-emerald-700', 'Livrée'],
                        'facture'             => ['bg-indigo-100 text-indigo-700',   'Facturée'],
                        'annule'              => ['bg-red-100 text-red-600',         'Annulée'],
                        default               => ['bg-gray-100 text-gray-500',       ucfirst($commande->status)],
                    };
                ?>
                <tr class="data-row border-b border-gray-50 last:border-0">
                    <td class="px-5 py-3">
                        <a href="<?php echo e(route('ventes.commandes.show', $commande)); ?>" class="font-mono text-xs font-bold text-indigo-600 hover:text-indigo-800"><?php echo e($commande->number); ?></a>
                        <?php if($commande->issued_at): ?><p class="text-xs text-gray-400"><?php echo e(\Carbon\Carbon::parse($commande->issued_at)->format('d/m/Y')); ?></p><?php endif; ?>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-violet-50 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-black text-violet-600"><?php echo e(strtoupper(substr(optional($commande->client)->name ?? '?', 0, 2))); ?></span>
                            </div>
                            <span class="text-xs text-gray-700 font-medium truncate max-w-[90px]"><?php echo e(optional($commande->client)->name ?? '—'); ?></span>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-right">
                        <span class="text-xs font-black text-gray-800 tabular-nums whitespace-nowrap"><?php echo e(number_format($commande->total_ttc, 0, ',', ' ')); ?> F</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold <?php echo e($sc); ?>"><?php echo e($sl); ?></span>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php /**PATH C:\laragon\www\iboa\resources\views/partials/dashboard/_dashboard-tables.blade.php ENDPATH**/ ?>