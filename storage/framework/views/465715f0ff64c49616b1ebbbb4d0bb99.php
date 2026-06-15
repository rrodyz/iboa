<?php $__env->startSection('title', $order->exists ? 'Modifier OF '.$order->number : 'Nouvel ordre de fabrication'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('dashboard')); ?>" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="<?php echo e(route('production.orders.index')); ?>" class="hover:text-gray-700">Ordres de fabrication</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium"><?php echo e($order->exists ? $order->number : 'Nouveau'); ?></span>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $initialLines = old('lines', $order->exists ? $order->lines->map(fn($l)=>[
        'length'=>$l->length,'quantity'=>$l->quantity,'unit_id'=>$l->unit_id,'label'=>$l->label,
    ])->values()->all() : []);
?>
<div class="max-w-4xl mx-auto space-y-5" x-data="{ lines: <?php echo e(Js::from($initialLines)); ?> }">
    <h1 class="text-2xl font-bold text-gray-900"><?php echo e($order->exists ? 'Modifier l\'OF '.$order->number : 'Nouvel ordre de fabrication'); ?></h1>

    <?php if($errors->any()): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        <ul class="list-disc pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e($order->exists ? route('production.orders.update', $order) : route('production.orders.store')); ?>" class="space-y-5">
        <?php echo csrf_field(); ?>
        <?php if($order->exists): ?><?php echo method_field('PUT'); ?><?php endif; ?>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                    <select name="client_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucun —</option>
                        <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($c->id); ?>" <?php if(old('client_id',$order->client_id)==$c->id): echo 'selected'; endif; ?>><?php echo e($c->trade_name ?? $c->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Commande de vente</label>
                    <select name="order_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucune —</option>
                        <?php $__currentLoopData = $salesOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $so): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($so->id); ?>" <?php if(old('order_id',$order->order_id)==$so->id): echo 'selected'; endif; ?>><?php echo e($so->number); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produit fini</label>
                    <select name="product_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucun —</option>
                        <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($p->id); ?>" <?php if(old('product_id',$order->product_id)==$p->id): echo 'selected'; endif; ?>><?php echo e($p->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomenclature</label>
                    <select name="bill_of_material_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucune —</option>
                        <?php $__currentLoopData = $boms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($b->id); ?>" <?php if(old('bill_of_material_id',$order->bill_of_material_id)==$b->id): echo 'selected'; endif; ?>><?php echo e($b->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ligne de production</label>
                    <select name="production_line_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucune —</option>
                        <?php $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($l->id); ?>" <?php if(old('production_line_id',$order->production_line_id)==$l->id): echo 'selected'; endif; ?>><?php echo e($l->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                    <select name="responsible_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucun —</option>
                        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($u->id); ?>" <?php if(old('responsible_id',$order->responsible_id)==$u->id): echo 'selected'; endif; ?>><?php echo e($u->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de tôle</label>
                    <input type="text" name="sheet_type" value="<?php echo e(old('sheet_type', $order->sheet_type)); ?>" maxlength="60" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Épaisseur (mm)</label>
                    <input type="number" name="thickness" value="<?php echo e(old('thickness', $order->thickness)); ?>" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                    <input type="text" name="color" value="<?php echo e(old('color', $order->color)); ?>" maxlength="60" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Largeur utile (mm)</label>
                    <input type="number" name="usable_width" value="<?php echo e(old('usable_width', $order->usable_width)); ?>" step="0.1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Qté demandée</label>
                    <input type="number" name="quantity_requested" value="<?php echo e(old('quantity_requested', $order->quantity_requested)); ?>" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300"><?php echo e(old('notes', $order->notes)); ?></textarea>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-900">Détail des coupes</h2>
                    <p class="text-xs text-gray-400">Longueur × quantité → mètres linéaires. La quantité demandée se calcule à partir d'ici.</p>
                </div>
                <button type="button" @click="lines.push({length:'',quantity:'',unit_id:'',label:''})" class="text-indigo-600 text-sm font-medium hover:underline">+ Ajouter une coupe</button>
            </div>

            <div class="tbl-scroll">
                <table class="tbl w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Libellé</th>
                            <th class="text-right">Longueur (m)</th>
                            <th class="text-right">Quantité</th>
                            <th class="text-right">Total m</th>
                            <th class="text-left">Unité</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, i) in lines" :key="i">
                            <tr>
                                <td><input type="text" :name="`lines[${i}][label]`" x-model="line.label" class="w-full border border-gray-200 rounded px-2 py-1 text-sm" placeholder="ex. Bac 6m"></td>
                                <td><input type="number" step="0.01" min="0" :name="`lines[${i}][length]`" x-model="line.length" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td><input type="number" step="0.01" min="0" :name="`lines[${i}][quantity]`" x-model="line.quantity" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td class="text-right font-mono tabular-nums text-gray-700" x-text="((parseFloat(line.length)||0)*(parseFloat(line.quantity)||0)).toFixed(2)"></td>
                                <td>
                                    <select :name="`lines[${i}][unit_id]`" x-model="line.unit_id" class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
                                        <option value="">—</option>
                                        <?php $__currentLoopData = $units; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($u->id); ?>"><?php echo e($u->abbreviation ?? $u->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </td>
                                <td class="text-right"><button type="button" @click="lines.splice(i,1)" class="text-gray-400 hover:text-red-600 text-sm">✕</button></td>
                            </tr>
                        </template>
                        <tr x-show="lines.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Aucune coupe. La quantité demandée sera prise du champ ci-dessus.</td></tr>
                    </tbody>
                    <tfoot x-show="lines.length > 0">
                        <tr class="font-semibold">
                            <td class="text-right text-gray-500" colspan="3">Total mètres</td>
                            <td class="text-right font-mono tabular-nums text-gray-900" x-text="lines.reduce((s,l)=>s+(parseFloat(l.length)||0)*(parseFloat(l.quantity)||0),0).toFixed(2)"></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?php echo e(route('production.orders.index')); ?>" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.erp', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\iboa\resources\views/production/orders/form.blade.php ENDPATH**/ ?>