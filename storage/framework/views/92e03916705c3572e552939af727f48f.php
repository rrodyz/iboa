<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'model' => null,            // ex: App\Models\Quote::class
    'id' => null,               // id de l'entité
    'limit' => 20,
    'title' => 'Historique d\'activité',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'model' => null,            // ex: App\Models\Quote::class
    'id' => null,               // id de l'entité
    'limit' => 20,
    'title' => 'Historique d\'activité',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $modelClass = is_string($model) ? $model : (is_object($model) ? get_class($model) : null);
    $logs = $modelClass && $id
        ? \App\Models\AuditLog::where('model_type', $modelClass)
            ->where('model_id', $id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
        : collect();

    $actionLabel = function(string $action) {
        if (str_starts_with($action, 'approval_')) {
            $sub = substr($action, 9);
            return [
                'en_attente' => '🟡 Soumis à approbation',
                'approuve'   => '✅ Approuvé',
                'rejete'     => '🛑 Rejeté',
                'non_requis' => '⚪ Approbation non requise',
            ][$sub] ?? '🔄 ' . $sub;
        }
        return [
            'created'         => '➕ Créé',
            'updated'         => '✏️ Modifié',
            'deleted'         => '🗑️ Supprimé',
            'restored'        => '♻️ Restauré',
            'status_changed'  => '🔄 Changement de statut',
            'validated'       => '✅ Validé',
            'paid'            => '💰 Payé',
            'payment_created' => '💰 Paiement enregistré',
            'payment_cancelled' => '↩️ Paiement annulé',
            'payment_modified'  => '✏️ Paiement modifié',
            'journal_entry_created' => '📒 Écriture comptable créée',
            'stock_movement'  => '📦 Mouvement de stock',
            'stock_movement_modified' => '📦 Mouvement modifié',
        ][$action] ?? '🔄 ' . $action;
    };
?>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700"><?php echo e($title); ?></h2>
        <span class="text-xs text-gray-400"><?php echo e($logs->count()); ?> événement<?php echo e($logs->count() > 1 ? 's' : ''); ?></span>
    </div>

    <?php if($logs->isEmpty()): ?>
        <div class="p-6 text-center text-gray-400 text-sm">Aucune activité enregistrée.</div>
    <?php else: ?>
    <ol class="relative px-5 py-4 space-y-3">
        <?php $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="relative pl-8">
                <div class="absolute left-0 top-1 w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center">
                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                </div>
                <?php if(!$loop->last): ?>
                    <div class="absolute left-2 top-6 bottom-[-12px] w-px bg-gray-200"></div>
                <?php endif; ?>
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-2">
                    <span class="text-sm font-medium text-gray-900"><?php echo e($actionLabel($log->action)); ?></span>
                    <span class="text-xs text-gray-500">par <span class="font-medium"><?php echo e($log->user_name ?? 'Système'); ?></span></span>
                    <span class="text-xs text-gray-400 sm:ml-auto" title="<?php echo e($log->created_at); ?>">
                        <?php echo e($log->created_at?->diffForHumans()); ?>

                    </span>
                </div>

                <?php if($log->new_values || $log->old_values): ?>
                    <?php
                        $newValues = is_array($log->new_values) ? $log->new_values : (json_decode($log->new_values ?? '[]', true) ?: []);
                        $oldValues = is_array($log->old_values) ? $log->old_values : (json_decode($log->old_values ?? '[]', true) ?: []);
                        $changes = [];
                        foreach ($newValues as $k => $v) {
                            if (in_array($k, ['updated_at','created_at'])) continue;
                            $oldVal = $oldValues[$k] ?? null;
                            $changes[$k] = ['old' => $oldVal, 'new' => $v];
                        }
                    ?>
                    <?php if(!empty($changes)): ?>
                    <div class="mt-1 text-xs text-gray-600 space-y-0.5">
                        <?php $__currentLoopData = $changes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $pair): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div>
                                <span class="font-mono text-gray-500"><?php echo e($field); ?></span>
                                <?php if($log->action !== 'created' && $log->action !== 'deleted'): ?>
                                    : <span class="line-through text-red-500"><?php echo e(is_scalar($pair['old']) ? \Str::limit((string) $pair['old'], 40) : json_encode($pair['old'])); ?></span>
                                    →
                                <?php endif; ?>
                                <span class="text-emerald-700 font-medium"><?php echo e(is_scalar($pair['new']) ? \Str::limit((string) $pair['new'], 40) : json_encode($pair['new'])); ?></span>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ol>
    <?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\iboa\resources\views/components/audit/timeline.blade.php ENDPATH**/ ?>