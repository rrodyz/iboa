<?php $__env->startSection('title', 'Comptabilité'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comptabilité</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
?>

<div class="space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Comptabilité</h1>
            <p class="text-sm text-gray-500">
                Exercice : <span class="font-medium text-gray-700"><?php echo e($fiscalYear?->label ?? 'non défini'); ?></span>
                <?php if($fiscalYear): ?>
                    · <?php echo e($fiscalYear->starts_at->format('d/m/Y')); ?> → <?php echo e($fiscalYear->ends_at->format('d/m/Y')); ?>

                    <?php if($fiscalYear->status !== 'ouvert'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 ml-1"><?php echo e(ucfirst($fiscalYear->status)); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('accounting.write')): ?>
            <a href="<?php echo e(route('comptabilite.journaux.create')); ?>"
               class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nouvelle écriture
            </a>
            <?php endif; ?>
            <a href="<?php echo e(route('comptabilite.journaux.export-pdf')); ?>" class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">
                Export PDF
            </a>
        </div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Résultat de l'exercice</p>
            <p class="mt-2 text-3xl font-bold tabular-nums <?php echo e($kpis['resultat'] >= 0 ? 'text-emerald-600' : 'text-red-600'); ?>">
                <?php echo e($kpis['resultat'] >= 0 ? '+' : ''); ?><?php echo e($fmt($kpis['resultat'])); ?>

            </p>
            <p class="text-xs text-gray-500 mt-2">
                Produits <?php echo e($fmt($kpis['produits'])); ?> − Charges <?php echo e($fmt($kpis['charges'])); ?>

            </p>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Trésorerie nette</p>
            <p class="mt-2 text-3xl font-bold tabular-nums <?php echo e($kpis['tresorerie'] >= 0 ? 'text-blue-600' : 'text-red-600'); ?>">
                <?php echo e($fmt($kpis['tresorerie'])); ?>

            </p>
            <p class="text-xs text-gray-500 mt-2">Comptes 52 · 53 · 57 (banques, instr., caisse)</p>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Activité — <?php echo e(now()->translatedFormat('F Y')); ?></p>
            <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900"><?php echo e($monthly['validees_mois']); ?></p>
            <p class="text-xs text-gray-500 mt-2">
                écritures validées · volume <?php echo e($fmt($monthly['volume_mois'])); ?>

                <?php if($monthly['brouillons'] > 0): ?>
                    · <span class="text-amber-600 font-medium"><?php echo e($monthly['brouillons']); ?> brouillon<?php echo e($monthly['brouillons']>1?'s':''); ?></span>
                <?php endif; ?>
            </p>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Créances clients</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-amber-600"><?php echo e($fmt($kpis['creances'])); ?></p>
            <p class="text-xs text-gray-500 mt-2">Compte 41 — ce que les clients vous doivent</p>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Dettes fournisseurs</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-orange-600"><?php echo e($fmt($kpis['dettes'])); ?></p>
            <p class="text-xs text-gray-500 mt-2">Compte 40 — ce que vous devez aux fournisseurs</p>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Position commerciale nette</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-gray-900"><?php echo e($fmt($kpis['creances'] - $kpis['dettes'])); ?></p>
            <p class="text-xs text-gray-500 mt-2">Créances − Dettes</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Top comptes mouvementés ce mois</h2>
                <a href="<?php echo e(route('comptabilite.grand-livre')); ?>" class="text-xs text-violet-600 hover:underline">Grand livre →</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Compte</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Débit</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Crédit</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Volume</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php $__empty_1 = true; $__currentLoopData = $topAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <span class="font-mono font-semibold text-violet-700"><?php echo e($row->code); ?></span>
                            <span class="text-gray-600 ml-2 text-xs"><?php echo e($row->name); ?></span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-700"><?php echo e($fmt($row->sd)); ?></td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-700"><?php echo e($fmt($row->sc)); ?></td>
                        <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-900"><?php echo e($fmt($row->volume)); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">Aucune activité ce mois.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Brouillons à valider</h2>
                <a href="<?php echo e(route('comptabilite.journaux.index', ['status' => 'brouillon'])); ?>" class="text-xs text-violet-600 hover:underline">Tous →</a>
            </div>
            <div class="divide-y divide-gray-50">
                <?php $__empty_1 = true; $__currentLoopData = $drafts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <a href="<?php echo e(route('comptabilite.journaux.show', $d)); ?>" class="block px-4 py-3 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs font-semibold text-violet-700"><?php echo e($d->number); ?></span>
                        <span class="text-xs text-gray-500"><?php echo e($d->entry_date?->format('d/m/Y')); ?></span>
                    </div>
                    <p class="text-sm text-gray-900 mt-0.5 truncate"><?php echo e($d->description); ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <?php echo e($d->journalType?->code); ?> · <?php echo e($fmt($d->total_debit)); ?>

                        <?php if(!$d->isBalanced()): ?>
                            <span class="text-red-600 ml-1">⚠ déséquilibré</span>
                        <?php endif; ?>
                    </p>
                </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="px-4 py-8 text-center text-gray-400 text-sm">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Aucun brouillon en attente.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Accès rapides</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 text-sm">
            <a href="<?php echo e(route('comptabilite.journaux.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📒 Journaux</a>
            <a href="<?php echo e(route('comptabilite.grand-livre')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📖 Grand livre</a>
            <a href="<?php echo e(route('comptabilite.balance')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">⚖ Balance</a>
            <a href="<?php echo e(route('comptabilite.balance-auxiliaire')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">👥 Balance aux.</a>
            <a href="<?php echo e(route('comptabilite.bilan')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📊 Bilan</a>
            <a href="<?php echo e(route('comptabilite.compte-de-resultat')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📈 Résultat</a>
            <a href="<?php echo e(route('comptabilite.sig')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📐 SIG</a>
            <a href="<?php echo e(route('comptabilite.plan-comptable.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">🗂 Plan comptable</a>
            <a href="<?php echo e(route('comptabilite.lettrage.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">🔗 Lettrage</a>
            <a href="<?php echo e(route('comptabilite.rapprochement.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">🏦 Rapprochement</a>
            <a href="<?php echo e(route('comptabilite.tva.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📋 TVA</a>
            <a href="<?php echo e(route('comptabilite.fec.export')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📤 Export FEC</a>
            <a href="<?php echo e(route('comptabilite.periods.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">🔒 Périodes</a>
            <a href="<?php echo e(route('settings.fiscal-years.index')); ?>" class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 text-center">📅 Exercices</a>
        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/comptabilite/dashboard.blade.php ENDPATH**/ ?>