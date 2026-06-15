<?php $__env->startSection('title', 'Commande '.$order->number); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('ventes.commandes.index')); ?>" class="hover:text-gray-700">Commandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium"><?php echo e($order->number); ?></span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">

    
    <?php echo $__env->make('partials._workflow-ventes', [
        'currentStep'  => 'commande',
        'quote'        => $order->quote ?? null,
        'order'        => $order,
        'deliveryNote' => $order->deliveryNotes->first() ?? null,
        'invoice'      => $order->invoices->first() ?? null,
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 font-mono"><?php echo e($order->number); ?></h1>
                <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $order->status,'label' => $order->status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->status_label)]); ?>
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
                <span class="text-gray-500 text-sm"><?php echo e($order->client?->name); ?></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                <?php
                    $btnO  = 'inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors';
                    $btnP  = 'inline-flex items-center gap-2 px-4 py-2 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors';
                    $btnWO = 'inline-flex items-center gap-2 px-3 py-2 border border-orange-200 text-orange-600 rounded-lg text-sm font-medium hover:bg-orange-50 transition-colors';
                    $btnDO = 'inline-flex items-center gap-2 px-3 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors';
                ?>

                
                <?php if($order->status === 'brouillon'): ?>
                    <a href="<?php echo e(route('ventes.commandes.edit', $order)); ?>" class="<?php echo e($btnO); ?>">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Modifier
                    </a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.submit')): ?>
                    <form action="<?php echo e(route('ventes.commandes.submit', $order)); ?>" method="POST"
                          onsubmit="return confirm('Soumettre cette commande à la validation interne ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="<?php echo e($btnP); ?> bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3 3L22 4"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                            </svg>
                            Soumettre à validation
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('orders.validate')): ?>
                    <form action="<?php echo e(route('ventes.commandes.destroy', $order)); ?>" method="POST"
                          onsubmit="return confirm('Supprimer définitivement cette commande brouillon ?')">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button type="submit" class="<?php echo e($btnDO); ?>">Supprimer</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>

                
                <?php if($order->status === 'en_attente_validation'): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm text-yellow-700 bg-yellow-50 border border-yellow-200">
                        <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        En attente de validation
                    </span>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.validate')): ?>
                    <form action="<?php echo e(route('ventes.commandes.validate-internal', $order)); ?>" method="POST"
                          onsubmit="return confirm('Valider cette commande ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="<?php echo e($btnP); ?> bg-emerald-600 hover:bg-emerald-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Valider
                        </button>
                    </form>
                    <form action="<?php echo e(route('ventes.commandes.reject-internal', $order)); ?>" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="<?php echo e($btnWO); ?>">Refuser</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif de refus</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="<?php echo e($btnO); ?>">Annuler</button>
                                    <button type="submit" class="<?php echo e($btnP); ?> bg-orange-600 hover:bg-orange-700">Confirmer le refus</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales.cancel')): ?>
                    <form action="<?php echo e(route('ventes.commandes.cancel-internal', $order)); ?>" method="POST"
                          x-data="{ open: false, motif: '' }"
                          @submit.prevent="if(motif.trim().length < 5){ alert('Motif obligatoire'); return; } $el.submit()">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="motif" x-model="motif">
                        <button type="button" @click="open = true" class="<?php echo e($btnDO); ?>">Annuler</button>
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
                            <div class="bg-white rounded-xl p-6 shadow-2xl w-full max-w-md mx-4">
                                <h3 class="font-semibold text-gray-900 mb-3">Motif d'annulation</h3>
                                <textarea x-model="motif" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Motif obligatoire…"></textarea>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" @click="open = false" class="<?php echo e($btnO); ?>">Fermer</button>
                                    <button type="submit" class="<?php echo e($btnP); ?> bg-red-600 hover:bg-red-700">Confirmer l'annulation</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>

                
                <?php if($order->status === 'confirme'): ?>
                    <form action="<?php echo e(route('ventes.commandes.delivery-note', $order)); ?>" method="POST"
                          onsubmit="return confirm('Créer un bon de livraison depuis cette commande ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 text-white rounded-lg text-sm font-medium hover:bg-teal-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6"/>
                            </svg>
                            Créer BL
                        </button>
                    </form>
                    <form action="<?php echo e(route('ventes.commandes.cancel', $order)); ?>" method="POST"
                          onsubmit="return confirm('Annuler cette commande ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 border border-red-300 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Annuler
                        </button>
                    </form>
                <?php endif; ?>

                
                <?php if(in_array($order->status, ['en_preparation', 'partiellement_livre'])): ?>
                    <form action="<?php echo e(route('ventes.commandes.delivery-note', $order)); ?>" method="POST"
                          onsubmit="return confirm('Créer un bon de livraison complémentaire ?')">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 text-white rounded-lg text-sm font-medium hover:bg-teal-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6"/>
                            </svg>
                            Nouveau BL
                        </button>
                    </form>
                    <form action="<?php echo e(route('ventes.commandes.invoice', $order)); ?>" method="POST"
                          onsubmit="return confirm('Créer une facture depuis cette commande ?')">
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

                
                <?php if($order->status === 'livre'): ?>
                    <form action="<?php echo e(route('ventes.commandes.invoice', $order)); ?>" method="POST"
                          onsubmit="return confirm('Créer une facture depuis cette commande ?')">
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

                
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('production.create')): ?>
                <?php if(!in_array($order->status, ['brouillon', 'annule'])): ?>
                    <a href="<?php echo e(route('production.orders.create', ['order_id' => $order->id])); ?>"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        Lancer en production
                    </a>
                <?php endif; ?>
                <?php endif; ?>

                <a href="<?php echo e(route('ventes.commandes.index')); ?>"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    
    <?php
        $statusMapCmd = [
            'brouillon'            => ['label' => 'Brouillon',               'class' => 'bg-gray-100 text-gray-700'],
            'en_attente_validation'=> ['label' => 'En attente de validation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'confirme'             => ['label' => 'Confirmé',                'class' => 'bg-blue-100 text-blue-700'],
            'en_preparation'       => ['label' => 'En préparation',          'class' => 'bg-yellow-100 text-yellow-700'],
            'partiellement_livre'  => ['label' => 'Part. livré',             'class' => 'bg-orange-100 text-orange-700'],
            'livre'                => ['label' => 'Livré',                   'class' => 'bg-green-100 text-green-700'],
            'facture'              => ['label' => 'Facturé',                 'class' => 'bg-purple-100 text-purple-700'],
            'annule'               => ['label' => 'Annulé',                  'class' => 'bg-red-100 text-red-700'],
        ];
    ?>
    <?php echo $__env->make('partials._doc-letterhead', [
        'docType'   => 'COMMANDE',
        'docNumber' => $order->number,
        'docDate'   => $order->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusMapCmd[$order->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $order->client        ? ['label' => 'Client',            'value' => $order->client->name]                  : null,
            $order->delivery_date ? ['label' => 'Livraison prévue',  'value' => $order->delivery_date->format('d/m/Y')] : null,
        ])),
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900"><?php echo e($order->client?->name ?? '—'); ?></dd>
                    <?php if($order->client): ?>
                    <dd class="mt-1">
                        <?php if($order->client->is_tax_exempt): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700" title="<?php echo e($order->client->tax_exemption_reason); ?>">Exonéré TVA</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Assujetti TVA</span>
                        <?php endif; ?>
                        <?php if($order->client->tax_regime): ?><span class="ml-1 text-xs text-gray-400"><?php echo e($order->client->tax_regime); ?></span><?php endif; ?>
                    </dd>
                    <?php endif; ?>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900"><?php echo e($order->number); ?></dd>
                    <?php if($order->reference): ?>
                    <dd class="text-gray-500 text-xs">Réf : <?php echo e($order->reference); ?></dd>
                    <?php endif; ?>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date commande</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($order->issued_at?->format('d/m/Y') ?? '—'); ?></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison prévue</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($order->delivery_date?->format('d/m/Y') ?? '—'); ?></dd>
                </div>
                <?php if($order->delivery_address): ?>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse livraison</dt>
                    <dd class="mt-0.5 text-gray-700"><?php echo e($order->delivery_address); ?></dd>
                </div>
                <?php endif; ?>
                <?php if($order->quote): ?>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Devis d'origine</dt>
                    <dd class="mt-0.5">
                        <a href="<?php echo e(route('ventes.devis.show', $order->quote)); ?>" class="text-blue-600 hover:underline font-mono"><?php echo e($order->quote->number); ?></a>
                    </dd>
                </div>
                <?php endif; ?>
                <?php if($order->notes): ?>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap"><?php echo e($order->notes); ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums"><?php echo e(number_format($order->subtotal_ht, 0, ',', ' ')); ?> FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums"><?php echo e(number_format($order->total_tax, 0, ',', ' ')); ?> FCFA</span>
            </div>
            <?php if($order->global_discount_amount > 0): ?>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Remise globale</span>
                <span class="font-medium tabular-nums text-orange-600">— <?php echo e(number_format($order->global_discount_amount, 0, ',', ' ')); ?> FCFA</span>
            </div>
            <?php endif; ?>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-blue-700 tabular-nums"><?php echo e(number_format($order->total_ttc, 0, ',', ' ')); ?> FCFA</span>
            </div>
            <?php if($order->invoiced_amount > 0): ?>
            <div class="flex justify-between text-sm text-gray-500 border-t border-gray-100 pt-2">
                <span>Déjà facturé</span>
                <span class="tabular-nums"><?php echo e(number_format($order->invoiced_amount, 0, ',', ' ')); ?> FCFA</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <?php if(isset($productionSummary)): ?>
    <?php $ps = $productionSummary; $agg = $ps['aggregate']; ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-900">Suivi production</h2>
                <?php $ac = ['gray'=>'bg-gray-100 text-gray-600','green'=>'bg-green-100 text-green-700','sky'=>'bg-sky-100 text-sky-700','amber'=>'bg-amber-100 text-amber-700','teal'=>'bg-teal-100 text-teal-700','red'=>'bg-red-100 text-red-700'][$agg['color']] ?? 'bg-gray-100 text-gray-600'; ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($ac); ?>"><?php echo e($agg['label']); ?></span>
            </div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('production.create')): ?>
            <?php if(!in_array($order->status, ['brouillon','annule'])): ?>
            <a href="<?php echo e(route('production.orders.create', ['order_id' => $order->id])); ?>"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <?php echo e($ps['count'] ? 'Nouvel OF' : 'Lancer en production'); ?>

            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if($ps['count']): ?>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="px-5 py-2">N° OF</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2 text-right">Demandé</th>
                        <th class="px-3 py-2 text-right">Produit</th>
                        <th class="px-3 py-2">Qualité</th>
                        <th class="px-3 py-2">PF stock</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $__currentLoopData = $ps['orders']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $of): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td class="px-5 py-2.5 font-mono text-xs text-indigo-600"><?php echo e($of['number']); ?></td>
                        <td class="px-3 py-2.5">
                            <?php $sc = match($of['status']){ 'brouillon'=>'bg-gray-100 text-gray-600','lance'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-sky-100 text-sky-700','termine'=>'bg-green-100 text-green-700',default=>'bg-red-100 text-red-700' }; ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo e($sc); ?>"><?php echo e($of['status_label']); ?></span>
                        </td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700"><?php echo e(number_format($of['qty_requested'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-900"><?php echo e(number_format($of['qty_produced'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5">
                            <?php if($of['qc_status']): ?>
                                <?php $qc = match($of['qc_status']){ 'conforme'=>'bg-green-100 text-green-700','a_reprendre'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo e($qc); ?>"><?php echo e($of['qc_label']); ?></span>
                            <?php else: ?> <span class="text-gray-400 text-xs">—</span> <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5"><?php echo $of['has_output'] ? '<span class="text-green-600 text-xs">✓ Entré</span>' : '<span class="text-gray-400 text-xs">—</span>'; ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('production.view')): ?>
                            <a href="<?php echo e(route('production.orders.show', $of['id'])); ?>" class="text-indigo-600 hover:underline text-xs font-medium">Voir OF →</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="px-5 py-8 text-center text-gray-400 text-sm">Aucun ordre de fabrication. Lancez la production pour cette commande.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <?php if(isset($stockAnalysis) && $stockAnalysis['lines']->isNotEmpty()): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-gray-900">Disponibilité produit fini</h2>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('production.update')): ?>
            <?php if($stockAnalysis['reservable'] > 0 && !in_array($order->status, ['brouillon','annule'])): ?>
            <form method="POST" action="<?php echo e(route('production.sales.reserve-stock', $order)); ?>"><?php echo csrf_field(); ?>
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-teal-600 text-white rounded-lg text-sm font-medium hover:bg-teal-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Réserver le stock disponible (<?php echo e(number_format($stockAnalysis['reservable'],0,',',' ')); ?>)
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs text-gray-500 uppercase">
                        <th class="px-5 py-2">Produit</th>
                        <th class="px-3 py-2 text-right">Commandé</th>
                        <th class="px-3 py-2 text-right">Dispo stock</th>
                        <th class="px-3 py-2 text-right">Déjà réservé</th>
                        <th class="px-3 py-2 text-right">À réserver</th>
                        <th class="px-3 py-2 text-right">À produire</th>
                        <th class="px-3 py-2">Décision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $__currentLoopData = $stockAnalysis['lines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td class="px-5 py-2.5 text-gray-800"><?php echo e($l['product']); ?></td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700"><?php echo e(number_format($l['ordered'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700"><?php echo e(number_format($l['available'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-500"><?php echo e(number_format($l['reserved'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-teal-700 font-medium"><?php echo e(number_format($l['reservable'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5 text-right tabular-nums text-orange-700 font-medium"><?php echo e(number_format($l['to_produce'],0,',',' ')); ?></td>
                        <td class="px-3 py-2.5">
                            <?php [$dc,$dl] = match($l['decision']){ 'stock'=>['bg-green-100 text-green-700','Stock suffisant'],'produce'=>['bg-orange-100 text-orange-700','À produire'],default=>['bg-amber-100 text-amber-700','Mixte (stock + prod)'] }; ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo e($dc); ?>"><?php echo e($dl); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
        <?php if($stockAnalysis['to_produce'] > 0): ?>
        <p class="px-5 py-3 text-xs text-orange-600 border-t border-gray-100">⚠ <?php echo e(number_format($stockAnalysis['to_produce'],0,',',' ')); ?> unité(s) à produire — lancez un ordre de fabrication.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de commande</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Prix Unit.</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Remise%</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">TVA%</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total HT</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $__empty_1 = true; $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 text-xs"><?php echo e($loop->iteration); ?></td>
                        <td class="px-4 py-3 text-gray-900"><?php echo e($item->description); ?></td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums"><?php echo e(number_format($item->quantity, 2, ',', ' ')); ?></td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums"><?php echo e(number_format($item->unit_price, 0, ',', ' ')); ?> FCFA</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums"><?php echo e($item->discount_percent > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—'); ?></td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums"><?php echo e(number_format($item->tax_rate_value, 2, ',', ' ')); ?>%</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums font-medium"><?php echo e(number_format($item->line_total_ht, 0, ',', ' ')); ?> FCFA</td>
                        <td class="px-4 py-3 text-right text-gray-900 tabular-nums font-semibold"><?php echo e(number_format($item->line_total_ttc, 0, ',', ' ')); ?> FCFA</td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <?php if($order->deliveryNotes->isNotEmpty()): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Bons de livraison</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $__currentLoopData = $order->deliveryNotes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dn): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-teal-600"><?php echo e($dn->number); ?></td>
                        <td class="px-4 py-3 text-gray-600"><?php echo e($dn->issued_at?->format('d/m/Y') ?? '—'); ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if($dn->status === 'valide'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Validé</span>
                            <?php elseif($dn->status === 'livre'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Livré</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?php echo e(route('ventes.bons-livraison.show', $dn)); ?>" class="text-teal-600 hover:text-teal-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    
    <?php if($order->invoices->isNotEmpty()): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Factures</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant TTC</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $__currentLoopData = $order->invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-indigo-600"><?php echo e($invoice->number); ?></td>
                        <td class="px-4 py-3 text-gray-600"><?php echo e($invoice->issued_at?->format('d/m/Y') ?? '—'); ?></td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900"><?php echo e(number_format($invoice->total_ttc, 0, ',', ' ')); ?> FCFA</td>
                        <td class="px-4 py-3 text-center">
                            <?php switch($invoice->status):
                                case ('brouillon'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Brouillon</span> <?php break; ?>
                                <?php case ('emise'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Émise</span> <?php break; ?>
                                <?php case ('envoyee'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">Envoyée</span> <?php break; ?>
                                <?php case ('partiellement_payee'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Part. payée</span> <?php break; ?>
                                <?php case ('payee'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Payée</span> <?php break; ?>
                                <?php case ('en_retard'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">En retard</span> <?php break; ?>
                                <?php case ('annulee'): ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-600">Annulée</span> <?php break; ?>
                                <?php default: ?> <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><?php echo e($invoice->status); ?></span>
                            <?php endswitch; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?php echo e(route('ventes.factures.show', $invoice)); ?>" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>



    
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <svg class="size-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                Validation interne
            </h2>
            <?php if (isset($component)) { $__componentOriginal27a1c5f204e2cc813651860c6e32c072 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal27a1c5f204e2cc813651860c6e32c072 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.status-badge','data' => ['status' => $order->status,'label' => $order->status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->status_label)]); ?>
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
        <?php if($order->rejection_reason): ?>
            <div class="mb-4 rounded-lg bg-orange-50 border border-orange-200 p-3 text-sm text-orange-800">
                <strong>Motif de refus :</strong> <?php echo e($order->rejection_reason); ?>

            </div>
        <?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal36143b33ccf4f9885e81ce756e34dbce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal36143b33ccf4f9885e81ce756e34dbce = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.action-buttons','data' => ['document' => $order,'submitRoute' => 'ventes.commandes.submit','validateRoute' => 'ventes.commandes.validate-internal','rejectRoute' => 'ventes.commandes.reject-internal','cancelRoute' => 'ventes.commandes.cancel-internal','routeParam' => $order->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.action-buttons'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['document' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order),'submitRoute' => 'ventes.commandes.submit','validateRoute' => 'ventes.commandes.validate-internal','rejectRoute' => 'ventes.commandes.reject-internal','cancelRoute' => 'ventes.commandes.cancel-internal','routeParam' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->id)]); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workflow.history','data' => ['document' => $order]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workflow.history'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['document' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order)]); ?>
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
        if ($order->quote) {
            $relatedLinks[] = [
                'icon'       => '📋',
                'label'      => 'Devis ' . $order->quote->number,
                'href'       => route('ventes.devis.show', $order->quote),
                'badge'      => $order->quote->status_label ?? ucfirst($order->quote->status),
                'badgeColor' => 'gray',
            ];
        }
        foreach ($order->deliveryNotes ?? [] as $dn) {
            $relatedLinks[] = [
                'icon'       => '🚚',
                'label'      => 'Bon de livraison ' . $dn->number,
                'href'       => route('ventes.bons-livraison.show', $dn),
                'badge'      => $dn->status_label ?? ucfirst($dn->status),
                'badgeColor' => 'purple',
            ];
        }
        foreach ($order->invoices ?? [] as $inv) {
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.document.related','data' => ['links' => $relatedLinks,'title' => 'Documents liés à cette commande']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('document.related'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['links' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($relatedLinks),'title' => 'Documents liés à cette commande']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.audit.timeline','data' => ['model' => \App\Models\Order::class,'id' => $order->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('audit.timeline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['model' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\App\Models\Order::class),'id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($order->id)]); ?>
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

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/ventes/commandes/show.blade.php ENDPATH**/ ?>