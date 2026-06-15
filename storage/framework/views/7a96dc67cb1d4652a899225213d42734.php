<?php $__env->startSection('title', 'BL '.$deliveryNote->number); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('ventes.bons-livraison.index')); ?>" class="hover:text-gray-700">Bons de livraison</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium"><?php echo e($deliveryNote->number); ?></span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">

    
    <?php echo $__env->make('partials._workflow-ventes', [
        'currentStep'  => 'livraison',
        'quote'        => $deliveryNote->order?->quote ?? null,
        'order'        => $deliveryNote->order ?? null,
        'deliveryNote' => $deliveryNote,
        'invoice'      => $deliveryNote->invoices->first() ?? ($deliveryNote->order?->invoices->first() ?? null),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 font-mono"><?php echo e($deliveryNote->number); ?></h1>
                <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $deliveryNote->status,'label' => $deliveryNote->status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote->status_label)]); ?>
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
                <span class="text-gray-500 text-sm"><?php echo e($deliveryNote->client?->name); ?></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                
                <a href="<?php echo e(route('ventes.bons-livraison.pdf', $deliveryNote)); ?>?preview=1" target="_blank"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>
                <a href="<?php echo e(route('ventes.bons-livraison.pdf', $deliveryNote)); ?>"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors"
                   data-loading data-loading-text="Génération du bon de livraison…">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </a>

                <?php
                    $blBtnO  = 'inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors';
                    $blBtnP  = 'inline-flex items-center gap-2 px-4 py-2 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors';
                    $blBtnWO = 'inline-flex items-center gap-2 px-3 py-2 border border-orange-200 text-orange-600 rounded-lg text-sm font-medium hover:bg-orange-50 transition-colors';
                    $blBtnDO = 'inline-flex items-center gap-2 px-3 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors';
                ?>

                
                <?php if($deliveryNote->status === 'brouillon'): ?>
                    <a href="<?php echo e(route('ventes.bons-livraison.edit', $deliveryNote)); ?>" class="<?php echo e($blBtnO); ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Modifier
                    </a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.submit')): ?>
                    <form action="<?php echo e(route('ventes.bons-livraison.submit', $deliveryNote)); ?>" method="POST"
                          onsubmit="return confirm('Soumettre ce bon de livraison à la validation interne ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="<?php echo e($blBtnP); ?> bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Soumettre à validation
                        </button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>

                
                <?php if($deliveryNote->status === 'en_attente_validation'): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.validate')): ?>
                    <form action="<?php echo e(route('ventes.bons-livraison.validate-internal', $deliveryNote)); ?>" method="POST"
                          onsubmit="return confirm('Valider ce bon de livraison ? Le stock sera décrémenté.')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="<?php echo e($blBtnP); ?> bg-teal-600 hover:bg-teal-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider la livraison
                        </button>
                    </form>
                    <form action="<?php echo e(route('ventes.bons-livraison.reject-internal', $deliveryNote)); ?>" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="<?php echo e($blBtnWO); ?>">Refuser</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="<?php echo e($blBtnO); ?>">Annuler</button>
                                    <button type="submit" class="<?php echo e($blBtnP); ?> bg-orange-600 hover:bg-orange-700">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.cancel')): ?>
                    <form action="<?php echo e(route('ventes.bons-livraison.cancel-internal', $deliveryNote)); ?>" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="<?php echo e($blBtnDO); ?>">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="<?php echo e($blBtnO); ?>">Fermer</button>
                                    <button type="submit" class="<?php echo e($blBtnP); ?> bg-red-600 hover:bg-red-700">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>

                
                <?php if($deliveryNote->status === 'valide'): ?>
                    <form action="<?php echo e(route('ventes.bons-livraison.invoice', $deliveryNote)); ?>" method="POST"
                          onsubmit="return confirm('Créer une facture depuis ce bon de livraison ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Créer facture
                        </button>
                    </form>
                <?php endif; ?>

                <a href="<?php echo e(route('ventes.bons-livraison.index')); ?>"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    
    <?php echo $__env->make('partials._doc-letterhead', [
        'docType'   => 'BON DE LIVRAISON',
        'docNumber' => $deliveryNote->number,
        'docDate'   => $deliveryNote->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => [
            'brouillon'            => ['label' => 'Brouillon',               'class' => 'bg-gray-100 text-gray-700'],
            'en_attente_validation'=> ['label' => 'En attente de validation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'valide'               => ['label' => 'Validé',                  'class' => 'bg-green-100 text-green-700'],
            'livre'                => ['label' => 'Livré',                   'class' => 'bg-purple-100 text-purple-700'],
            'annule'               => ['label' => 'Annulé',                  'class' => 'bg-red-100 text-red-700'],
        ][$deliveryNote->status] ?? ['label' => ucfirst($deliveryNote->status), 'class' => 'bg-gray-100 text-gray-700'],
        'docExtra'  => array_values(array_filter([
            $deliveryNote->client    ? ['label' => 'Client',      'value' => $deliveryNote->client->name]          : null,
            $deliveryNote->warehouse ? ['label' => 'Entrepôt',    'value' => $deliveryNote->warehouse->name]       : null,
            $deliveryNote->order     ? ['label' => 'Commande',    'value' => $deliveryNote->order->number]         : null,
        ])),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900"><?php echo e($deliveryNote->client?->name ?? '—'); ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900"><?php echo e($deliveryNote->number); ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($deliveryNote->issued_at?->format('d/m/Y') ?? '—'); ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Entrepôt</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($deliveryNote->warehouse?->name ?? '—'); ?></dd>
                </div>
                <?php if($deliveryNote->delivery_address): ?>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse livraison</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($deliveryNote->delivery_address); ?></dd>
                </div>
                <?php endif; ?>
                <?php if($deliveryNote->carrier): ?>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Transporteur</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($deliveryNote->carrier); ?></dd>
                </div>
                <?php endif; ?>
                <?php if($deliveryNote->tracking_number): ?>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">N° suivi</dt>
                    <dd class="mt-0.5 text-gray-700 font-mono"><?php echo e($deliveryNote->tracking_number); ?></dd>
                </div>
                <?php endif; ?>
                <?php if($deliveryNote->notes): ?>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap"><?php echo e($deliveryNote->notes); ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 h-fit space-y-3">
            <h2 class="text-base font-semibold text-gray-900">Quantités</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total articles</span>
                <span class="font-semibold text-gray-900"><?php echo e($deliveryNote->items->count()); ?> ligne(s)</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total quantité</span>
                <span class="font-semibold text-gray-900"><?php echo e(number_format($deliveryNote->total_quantity, 2, ',', ' ')); ?></span>
            </div>
        </div>
    </div>

    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Articles livrés</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Réf. produit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">N° Lot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $__empty_1 = true; $__currentLoopData = $deliveryNote->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 text-xs"><?php echo e($loop->iteration); ?></td>
                        <td class="px-4 py-3 text-gray-900">
                            <?php echo e($item->description); ?>

                            <?php if($item->expiry_date): ?>
                                <p class="text-xs text-orange-500">Exp. : <?php echo e($item->expiry_date->format('d/m/Y')); ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs font-mono hidden md:table-cell"><?php echo e($item->product?->reference ?? '—'); ?></td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900"><?php echo e(number_format($item->quantity, 2, ',', ' ')); ?></td>
                        <td class="px-4 py-3 text-gray-500 text-xs hidden lg:table-cell"><?php echo e($item->lot_number ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                Validation interne
            </h2>
            <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $deliveryNote->status,'label' => $deliveryNote->status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote->status_label)]); ?>
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
        </div>
        <?php if($deliveryNote->rejection_reason): ?>
            <div class="mb-4 rounded-lg bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> <?php echo e($deliveryNote->rejection_reason); ?>

            </div>
        <?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal36143b33ccf4f9885e81ce756e34dbce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36143b33ccf4f9885e81ce756e34dbce = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.action-buttons','data' => ['document' => $deliveryNote,'submitRoute' => 'ventes.bons-livraison.submit','validateRoute' => 'ventes.bons-livraison.validate-internal','rejectRoute' => 'ventes.bons-livraison.reject-internal','cancelRoute' => 'ventes.bons-livraison.cancel-internal','routeParam' => $deliveryNote->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.action-buttons'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['document' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote),'submitRoute' => 'ventes.bons-livraison.submit','validateRoute' => 'ventes.bons-livraison.validate-internal','rejectRoute' => 'ventes.bons-livraison.reject-internal','cancelRoute' => 'ventes.bons-livraison.cancel-internal','routeParam' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote->id)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal36143b33ccf4f9885e81ce756e34dbce)): ?>
<?php $attributes = $__attributesOriginal36143b33ccf4f9885e81ce756e34dbce; ?>
<?php unset($__attributesOriginal36143b33ccf4f9885e81ce756e34dbce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal36143b33ccf4f9885e81ce756e34dbce)): ?>
<?php $component = $__componentOriginal36143b33ccf4f9885e81ce756e34dbce; ?>
<?php unset($__componentOriginal36143b33ccf4f9885e81ce756e34dbce); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal86a72f886af00d1d42946a6c0c7bd2ac = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal86a72f886af00d1d42946a6c0c7bd2ac = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.history','data' => ['document' => $deliveryNote]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.history'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['document' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal86a72f886af00d1d42946a6c0c7bd2ac)): ?>
<?php $attributes = $__attributesOriginal86a72f886af00d1d42946a6c0c7bd2ac; ?>
<?php unset($__attributesOriginal86a72f886af00d1d42946a6c0c7bd2ac); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal86a72f886af00d1d42946a6c0c7bd2ac)): ?>
<?php $component = $__componentOriginal86a72f886af00d1d42946a6c0c7bd2ac; ?>
<?php unset($__componentOriginal86a72f886af00d1d42946a6c0c7bd2ac); ?>
<?php endif; ?>
    </div>

    
    <?php
        $relatedLinks = [];
        if ($deliveryNote->order) {
            $relatedLinks[] = [
                'icon'       => '📦',
                'label'      => 'Commande ' . $deliveryNote->order->number,
                'href'       => route('ventes.commandes.show', $deliveryNote->order),
                'badge'      => $deliveryNote->order->status_label ?? ucfirst($deliveryNote->order->status),
                'badgeColor' => 'blue',
            ];
            if ($deliveryNote->order->quote) {
                $relatedLinks[] = [
                    'icon'       => '📋',
                    'label'      => 'Devis ' . $deliveryNote->order->quote->number,
                    'href'       => route('ventes.devis.show', $deliveryNote->order->quote),
                    'badge'      => $deliveryNote->order->quote->status_label ?? ucfirst($deliveryNote->order->quote->status),
                    'badgeColor' => 'gray',
                ];
            }
        }
        foreach ($deliveryNote->invoices ?? [] as $inv) {
            $relatedLinks[] = [
                'icon'       => '🧾',
                'label'      => 'Facture ' . $inv->number,
                'href'       => route('ventes.factures.show', $inv),
                'badge'      => $inv->status_label ?? ucfirst($inv->status),
                'badgeColor' => 'green',
            ];
        }
    ?>
    <?php if(count($relatedLinks)): ?>
        <?php if (isset($component)) { $__componentOriginal2e0d076a2800bded219e34b59ffc4f13 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2e0d076a2800bded219e34b59ffc4f13 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.document.related','data' => ['links' => $relatedLinks,'title' => 'Documents liés à ce bon de livraison']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('document.related'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['links' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($relatedLinks),'title' => 'Documents liés à ce bon de livraison']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2e0d076a2800bded219e34b59ffc4f13)): ?>
<?php $attributes = $__attributesOriginal2e0d076a2800bded219e34b59ffc4f13; ?>
<?php unset($__attributesOriginal2e0d076a2800bded219e34b59ffc4f13); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2e0d076a2800bded219e34b59ffc4f13)): ?>
<?php $component = $__componentOriginal2e0d076a2800bded219e34b59ffc4f13; ?>
<?php unset($__componentOriginal2e0d076a2800bded219e34b59ffc4f13); ?>
<?php endif; ?>
    <?php endif; ?>

    
    <?php if (isset($component)) { $__componentOriginal510ebc3b63b0b470389b14ffd7cf0916 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal510ebc3b63b0b470389b14ffd7cf0916 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.audit.timeline','data' => ['model' => \App\Models\DeliveryNote::class,'id' => $deliveryNote->id,'title' => 'Historique du bon de livraison']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('audit.timeline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['model' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\App\Models\DeliveryNote::class),'id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deliveryNote->id),'title' => 'Historique du bon de livraison']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal510ebc3b63b0b470389b14ffd7cf0916)): ?>
<?php $attributes = $__attributesOriginal510ebc3b63b0b470389b14ffd7cf0916; ?>
<?php unset($__attributesOriginal510ebc3b63b0b470389b14ffd7cf0916); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal510ebc3b63b0b470389b14ffd7cf0916)): ?>
<?php $component = $__componentOriginal510ebc3b63b0b470389b14ffd7cf0916; ?>
<?php unset($__componentOriginal510ebc3b63b0b470389b14ffd7cf0916); ?>
<?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/ventes/bons-livraison/show.blade.php ENDPATH**/ ?>