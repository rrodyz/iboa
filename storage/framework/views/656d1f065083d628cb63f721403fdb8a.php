<?php $__env->startSection('title', 'Devis'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Devis</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); ?>
<div class="space-y-5">

    
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Total TTC filtré</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums"><?php echo e($fmt($summary['total_ttc'])); ?> <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Montant accepté</p>
            <p class="text-lg font-bold text-emerald-600 tabular-nums"><?php echo e($fmt($summary['total_accepted'])); ?> <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">En attente</p>
            <p class="text-lg font-bold text-blue-600 tabular-nums"><?php echo e($summary['count_pending']); ?> <span class="text-xs font-normal text-gray-400">devis</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Expirés</p>
            <p class="text-lg font-bold <?php echo e($summary['count_expired'] > 0 ? 'text-orange-600' : 'text-gray-900'); ?> tabular-nums"><?php echo e($summary['count_expired']); ?> <span class="text-xs font-normal text-gray-400">devis</span></p>
        </div>
    </div>

    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Devis</h1>
            <p class="text-sm text-gray-500 mt-0.5"><?php echo e($quotes->total()); ?> devis</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="<?php echo e(route('ventes.devis.export', array_filter([
                    'status'    => $filters['status']    ?? null,
                    'search'    => $filters['search']    ?? null,
                    'date_from' => $filters['date_from'] ?? null,
                    'date_to'   => $filters['date_to']   ?? null,
                ]))); ?>"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-lg transition-colors"
               data-loading data-loading-text="Export Excel en cours…">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exporter Excel
            </a>
            <a href="<?php echo e(route('ventes.devis.create')); ?>"
               class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau devis
            </a>
        </div>
    </div>

    
    <form method="GET" data-autosubmit class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="search" value="<?php echo e($filters['search'] ?? ''); ?>" placeholder="Numéro, client..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             <?php echo e(($filters['status'] ?? '') === 'brouillon'             ? 'selected' : ''); ?>>Brouillon</option>
                <option value="en_attente_validation" <?php echo e(($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : ''); ?>>⏳ En attente de validation</option>
                <option value="envoye"                <?php echo e(($filters['status'] ?? '') === 'envoye'                ? 'selected' : ''); ?>>Envoyé</option>
                <option value="accepte"               <?php echo e(($filters['status'] ?? '') === 'accepte'               ? 'selected' : ''); ?>>Accepté</option>
                <option value="refuse"                <?php echo e(($filters['status'] ?? '') === 'refuse'                ? 'selected' : ''); ?>>Refusé</option>
                <option value="expire"                <?php echo e(($filters['status'] ?? '') === 'expire'                ? 'selected' : ''); ?>>Expiré</option>
                <option value="annule"                <?php echo e(($filters['status'] ?? '') === 'annule'                ? 'selected' : ''); ?>>Annulé</option>
            </select>

            <input type="date" name="date_from" value="<?php echo e($filters['date_from'] ?? ''); ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

            <input type="date" name="date_to" value="<?php echo e($filters['date_to'] ?? ''); ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                <?php if(request()->hasAny(['search','status','client_id','date_from','date_to'])): ?>
                <a href="<?php echo e(route('ventes.devis.index')); ?>"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">
                    ✕
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky">
                <thead>
                    <tr>
                        <th class="text-left">Numéro</th>
                        <th class="text-left">Client</th>
                        <th class="text-left hidden md:table-cell">Date</th>
                        <th class="text-left hidden lg:table-cell">Validité</th>
                        <th class="text-right">Montant TTC</th>
                        <th class="text-center">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $quotes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <a href="<?php echo e(route('ventes.devis.show', $quote)); ?>"
                               class="font-mono font-semibold text-blue-600 hover:text-blue-800">
                                <?php echo e($quote->number); ?>

                            </a>
                            <?php if($quote->reference): ?>
                            <p class="text-xs text-gray-400"><?php echo e($quote->reference); ?></p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="font-medium text-gray-900"><?php echo e($quote->client?->name ?? '—'); ?></span>
                            <?php if($quote->client?->trade_name): ?>
                            <p class="text-xs text-gray-400"><?php echo e($quote->client->trade_name); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="text-gray-600 hidden md:table-cell">
                            <?php echo e($quote->issued_at?->format('d/m/Y') ?? '—'); ?>

                        </td>
                        <td class="hidden lg:table-cell">
                            <?php if($quote->expires_at): ?>
                                <span class="<?php echo e($quote->expires_at->isPast() && !in_array($quote->status, ['accepte','annule']) ? 'text-red-600 font-medium' : 'text-gray-600'); ?>">
                                    <?php echo e($quote->expires_at->format('d/m/Y')); ?>

                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-semibold tabular-nums text-gray-900">
                            <?php echo e(number_format($quote->total_ttc, 0, ',', ' ')); ?> FCFA
                        </td>
                        <td class="text-center">
                            <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $quote->status,'label' => $quote->status_label,'size' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($quote->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($quote->status_label),'size' => 'sm']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal27a1c5f204e2cc813651860c6e32c072)): ?>
<?php $attributes = $__attributesOriginal27a1c5f204e2cc813651860c6e32c072; ?>
<?php unset($__attributesOriginal27a1c5f204e2cc813651860c6e32c072); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal27a1c5f204e2cc813651860c6e32c072)): ?>
<?php $component = $__componentOriginal27a1c5f204e2cc813651860c6e32c072; ?>
<?php unset($__componentOriginal27a1c5f204e2cc813651860c6e32c072); ?>
<?php endif; ?>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                
                                <a href="<?php echo e(route('ventes.devis.show', $quote)); ?>"
                                   class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                
                                <?php if(in_array($quote->status, ['brouillon', 'envoye'])): ?>
                                <a href="<?php echo e(route('ventes.devis.edit', $quote)); ?>"
                                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <?php endif; ?>
                                
                                <?php if(in_array($quote->status, ['envoye', 'brouillon']) && !$quote->converted_to_order_id): ?>
                                <form action="<?php echo e(route('ventes.devis.convert', $quote)); ?>" method="POST"
                                      data-confirm="Convertir ce devis en commande ?"
                                      data-confirm-title="Convertir en commande"
                                      data-confirm-label="Convertir"
                                      data-confirm-danger="false">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Convertir en commande">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if($quote->status === 'brouillon'): ?>
                                <form action="<?php echo e(route('ventes.devis.destroy', $quote)); ?>" method="POST"
                                      data-confirm="Supprimer le devis <?php echo e($quote->number); ?> ?"
                                      data-confirm-title="Supprimer le devis">
                                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucun devis trouvé.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($quotes->hasPages()): ?>
        <div class="px-4 py-3 border-t border-gray-100">
            <?php echo e($quotes->appends($filters)->links()); ?>

        </div>
        <?php endif; ?>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/ventes/devis/index.blade.php ENDPATH**/ ?>