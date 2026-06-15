

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 fade-up" style="animation-delay:.28s">

    
    <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Flux de trésorerie</h2>
                <p class="text-xs text-gray-400 mt-0.5">Encaissements vs Décaissements · 6 mois</p>
            </div>
            <div class="flex items-center gap-4 text-xs text-gray-400 pt-1">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>Encaissements
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400 inline-block"></span>Décaissements
                </span>
            </div>
        </div>
        <div id="chart-cashflow"></div>
    </div>

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Activité récente</h2>
                <p class="text-xs text-gray-400 mt-0.5">Dernières actions</p>
            </div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('audit.view')): ?>
            <a href="<?php echo e(route('audit.index')); ?>" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700 whitespace-nowrap">
                Tout voir →
            </a>
            <?php endif; ?>
        </div>

        <?php if($recentActivity->isEmpty()): ?>
        <div class="flex-1 flex flex-col items-center justify-center gap-2 py-8 text-gray-300">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-gray-400">Aucune activité récente</p>
        </div>
        <?php else: ?>
        <div class="flex-1 overflow-y-auto max-h-[300px] feed-scroll space-y-0">
            <?php $__currentLoopData = $recentActivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $act): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $cs = [
                    'created'   => ['dot'=>'bg-emerald-400','badge'=>'bg-emerald-50 text-emerald-700','label'=>'Créé'],
                    'updated'   => ['dot'=>'bg-blue-400',   'badge'=>'bg-blue-50 text-blue-700',   'label'=>'Modifié'],
                    'deleted'   => ['dot'=>'bg-red-400',    'badge'=>'bg-red-50 text-red-600',     'label'=>'Supprimé'],
                    'login'     => ['dot'=>'bg-indigo-400', 'badge'=>'bg-indigo-50 text-indigo-700','label'=>'Connexion'],
                    'validated' => ['dot'=>'bg-violet-400', 'badge'=>'bg-violet-50 text-violet-700','label'=>'Validé'],
                ];
                $c     = $cs[$act->action] ?? ['dot'=>'bg-gray-300','badge'=>'bg-gray-50 text-gray-500','label'=>ucfirst($act->action??'—')];
                $model = $act->model_type ? $act->modelLabel() : null;
            ?>
            <div class="relative flex gap-3 py-2.5 <?php echo e($i < $recentActivity->count()-1 ? 'border-b border-gray-50' : ''); ?>">
                
                <div class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0 <?php echo e($c['dot']); ?>"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-800 leading-snug">
                        <span class="font-semibold"><?php echo e($act->user_name ?? 'Système'); ?></span>
                        <span class="inline-flex items-center mx-1 px-1.5 py-0.5 rounded text-xs font-bold <?php echo e($c['badge']); ?>"><?php echo e($c['label']); ?></span>
                        <?php if($model): ?>
                            <span class="text-gray-500"><?php echo e($model); ?></span>
                            <?php if($act->model_id): ?>
                                <span class="text-gray-300 ml-0.5">#<?php echo e($act->model_id); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5"><?php echo e($act->created_at->diffForHumans()); ?></p>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php /**PATH C:\laragon\www\iboa\resources\views/partials/dashboard/_dashboard-charts-row2.blade.php ENDPATH**/ ?>