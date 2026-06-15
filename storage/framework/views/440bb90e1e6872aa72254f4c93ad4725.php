<?php $__env->startSection('title', 'Tableau de bord Direction'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Direction</span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-5">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord Direction</h1>
        <p class="text-sm text-gray-500 mt-0.5">Synthèse exécutive — <?php echo e(now()->translatedFormat('F Y')); ?></p>
    </div>

    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Chiffre d'affaires (mois)</p>
            <p class="text-2xl font-bold text-indigo-700 tabular-nums mt-1"><?php echo e(number_format($kpis['ca_month'], 0, ',', ' ')); ?> F</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Marge production (mois)</p>
            <p class="text-2xl font-bold <?php echo e($kpis['marge_month'] >= 0 ? 'text-green-700' : 'text-red-700'); ?> tabular-nums mt-1"><?php echo e(number_format($kpis['marge_month'], 0, ',', ' ')); ?> F</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Trésorerie (solde)</p>
            <p class="text-2xl font-bold <?php echo e($kpis['tresorerie'] >= 0 ? 'text-sky-700' : 'text-red-700'); ?> tabular-nums mt-1"><?php echo e(number_format($kpis['tresorerie'], 0, ',', ' ')); ?> F</p>
        </div>
        <a href="<?php echo e(route('reports.impayes')); ?>" class="bg-red-50 border border-red-200 rounded-2xl p-5 hover:bg-red-100 transition-colors">
            <p class="text-xs text-red-600 uppercase tracking-wider">Factures impayées</p>
            <p class="text-2xl font-bold text-red-800 tabular-nums mt-1"><?php echo e(number_format($kpis['impayes_montant'], 0, ',', ' ')); ?> F</p>
            <p class="text-xs text-gray-400 mt-0.5"><?php echo e($kpis['impayes_count']); ?> facture(s)</p>
        </a>
    </div>

    
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Production</h2>
            <a href="<?php echo e(route('production.dashboard')); ?>" class="text-sm text-indigo-600 hover:underline">Détail →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-sky-50 border border-sky-200 rounded-xl p-4">
                <p class="text-xs text-sky-600 uppercase tracking-wider">OF en cours</p>
                <p class="text-xl font-bold text-sky-800 mt-1"><?php echo e($kpis['of_en_cours']); ?></p>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                <p class="text-xs text-green-600 uppercase tracking-wider">OF terminés (mois)</p>
                <p class="text-xl font-bold text-green-800 mt-1"><?php echo e($kpis['of_termine_month']); ?></p>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                <p class="text-xs text-orange-600 uppercase tracking-wider">Mètres produits</p>
                <p class="text-xl font-bold text-orange-800 tabular-nums mt-1"><?php echo e(number_format($kpis['meters_month'], 0, ',', ' ')); ?></p>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <p class="text-xs text-emerald-600 uppercase tracking-wider">Rendement matière</p>
                <p class="text-xl font-bold text-emerald-800 tabular-nums mt-1"><?php echo e($kpis['rendement'] !== null ? number_format($kpis['rendement'], 1, ',', ' ').' %' : '—'); ?></p>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                <p class="text-xs text-amber-600 uppercase tracking-wider">Chutes (mois)</p>
                <p class="text-xl font-bold text-amber-800 tabular-nums mt-1"><?php echo e(number_format($kpis['waste_month'], 0, ',', ' ')); ?> kg</p>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="<?php echo e(route('production.dashboard')); ?>" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5 hover:border-indigo-200 transition-colors">
            <p class="font-semibold text-gray-900">🏭 Production</p>
            <p class="text-xs text-gray-500 mt-1">OF, rendement, coûts</p>
        </a>
        <a href="<?php echo e(route('ventes.dashboard')); ?>" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5 hover:border-indigo-200 transition-colors">
            <p class="font-semibold text-gray-900">💼 Commercial</p>
            <p class="text-xs text-gray-500 mt-1">Devis, commandes, CA</p>
        </a>
        <a href="<?php echo e(route('comptabilite.dashboard')); ?>" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5 hover:border-indigo-200 transition-colors">
            <p class="font-semibold text-gray-900">💰 Comptable</p>
            <p class="text-xs text-gray-500 mt-1">TVA, balance, créances</p>
        </a>
        <a href="<?php echo e(route('tresorerie.dashboard')); ?>" class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5 hover:border-indigo-200 transition-colors">
            <p class="font-semibold text-gray-900">🏦 Trésorerie</p>
            <p class="text-xs text-gray-500 mt-1">Encaissements, prévisions</p>
        </a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/direction/dashboard.blade.php ENDPATH**/ ?>