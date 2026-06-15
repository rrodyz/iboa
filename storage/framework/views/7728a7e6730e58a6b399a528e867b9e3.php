

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 fade-up" style="animation-delay:.36s">

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base font-bold text-gray-900">Top clients</h3>
                <p class="text-xs text-gray-400 mt-0.5">CA ce mois · <span class="font-semibold text-gray-600"><?php echo e($nbClients); ?></span> clients actifs</p>
            </div>
            <a href="<?php echo e(route('clients.index')); ?>" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700">Voir tous →</a>
        </div>

        <?php if($topClients->isEmpty()): ?>
        <div class="flex flex-col items-center justify-center py-8 gap-2 text-gray-300">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="text-sm text-gray-400">Aucune vente ce mois</p>
        </div>
        <?php else: ?>
        <?php
            $maxC = $topClients->max('total');
            $pal  = [
                ['bg'=>'bg-indigo-600','light'=>'bg-indigo-100','text'=>'text-indigo-700','bar'=>'#4f46e5'],
                ['bg'=>'bg-violet-600','light'=>'bg-violet-100','text'=>'text-violet-700','bar'=>'#7c3aed'],
                ['bg'=>'bg-blue-600',  'light'=>'bg-blue-100',  'text'=>'text-blue-700',  'bar'=>'#2563eb'],
                ['bg'=>'bg-sky-600',   'light'=>'bg-sky-100',   'text'=>'text-sky-700',   'bar'=>'#0284c7'],
                ['bg'=>'bg-cyan-600',  'light'=>'bg-cyan-100',  'text'=>'text-cyan-700',  'bar'=>'#0891b2'],
            ];
        ?>
        <div class="space-y-4">
            <?php $__currentLoopData = $topClients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $tc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $pct = $maxC > 0 ? round(($tc->total/$maxC)*100) : 0; $p = $pal[$i] ?? $pal[4]; ?>
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-7 h-7 rounded-lg <?php echo e($p['light']); ?> flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-black <?php echo e($p['text']); ?>"><?php echo e($i+1); ?></span>
                    </div>
                    <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                        <?php if($tc->client): ?>
                        <a href="<?php echo e(route('clients.show', $tc->client)); ?>"
                           class="text-xs font-semibold text-gray-700 hover:text-indigo-600 truncate transition-colors"><?php echo e($tc->client->name); ?></a>
                        <?php else: ?>
                        <span class="text-xs font-semibold text-gray-700 truncate">Client #<?php echo e($tc->client_id); ?></span>
                        <?php endif; ?>
                        <span class="text-xs font-black text-gray-900 tabular-nums flex-shrink-0"><?php echo e(number_format($tc->total, 0, ',', ' ')); ?> F</span>
                    </div>
                </div>
                <div class="ml-10 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full progress-fill rounded-full" style="width:<?php echo e($pct); ?>%;background:<?php echo e($p['bar']); ?>;animation-delay:<?php echo e($i*0.12); ?>s"></div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base font-bold text-gray-900">Top produits</h3>
                <p class="text-xs text-gray-400 mt-0.5">CA et marge ce mois</p>
            </div>
            <a href="<?php echo e(route('reports.margins')); ?>" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700">Marges →</a>
        </div>

        <?php if($topProduits->isEmpty()): ?>
        <div class="flex flex-col items-center justify-center py-8 gap-2 text-gray-300">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <p class="text-sm text-gray-400">Aucune vente ce mois</p>
        </div>
        <?php else: ?>
        <?php $maxP = $topProduits->max('ca_ht'); $barColors = ['#4f46e5','#7c3aed','#2563eb','#0284c7','#0891b2']; ?>
        <div class="space-y-4">
            <?php $__currentLoopData = $topProduits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $tp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $pct  = $maxP > 0 ? round(($tp->ca_ht/$maxP)*100) : 0;
                $mPct = $tp->ca_ht > 0 ? round(($tp->marge/$tp->ca_ht)*100, 1) : 0;
                $bc   = $barColors[$i] ?? '#4f46e5';
            ?>
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-xs font-black text-gray-500"><?php echo e($i+1); ?></span>
                    </div>
                    <div class="flex-1 min-w-0 flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-gray-700 truncate"><?php echo e($tp->name); ?></span>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs font-black text-gray-900 tabular-nums"><?php echo e(number_format($tp->ca_ht, 0, ',', ' ')); ?> F</span>
                            <span class="text-xs font-bold px-1.5 py-0.5 rounded-full <?php echo e($mPct >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'); ?>">
                                <?php echo e($mPct >= 0 ? '+' : ''); ?><?php echo e($mPct); ?>%
                            </span>
                        </div>
                    </div>
                </div>
                <div class="ml-10 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full progress-fill rounded-full" style="width:<?php echo e($pct); ?>%;background:<?php echo e($bc); ?>;animation-delay:<?php echo e($i*0.12); ?>s"></div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php /**PATH C:\laragon\www\iboa\resources\views/partials/dashboard/_dashboard-top-lists.blade.php ENDPATH**/ ?>