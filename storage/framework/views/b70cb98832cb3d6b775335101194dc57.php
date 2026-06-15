
<?php
    use Illuminate\Support\Facades\Storage;

    // once() memoises per request — safe if the partial is included multiple times on the same page
    $co = once(fn() => \App\Models\Company::first());

    // [FIX-LOGO-URL] Storage::url() yields a root-absolute path like "/storage/..." which
    // breaks under a sub-folder deployment (e.g. /iboa/public/). url() prepends the current
    // request base path so the URL stays valid regardless of where the app is mounted.
    $logoUrl = $co?->logo ? url(Storage::url($co->logo)) : null;

    $docTypeColors = [
        'FACTURE'           => ['bg' => '#4f46e5', 'light' => '#eef2ff'],
        'DEVIS'             => ['bg' => '#0284c7', 'light' => '#e0f2fe'],
        'BON DE LIVRAISON'  => ['bg' => '#0f766e', 'light' => '#f0fdfa'],
        'AVOIR'             => ['bg' => '#7c3aed', 'light' => '#f5f3ff'],
        'COMMANDE'          => ['bg' => '#b45309', 'light' => '#fffbeb'],
    ];
    $colors = $docTypeColors[$docType] ?? ['bg' => '#374151', 'light' => '#f9fafb'];

    $docExtra = $docExtra ?? [];
    $docStatus = $docStatus ?? null;
?>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-5">

    
    <div style="height:4px;background:<?php echo e($colors['bg']); ?>"></div>

    <div class="p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-5">

            
            <div class="flex items-start gap-4 min-w-0">

                
                <div class="flex-shrink-0">
                    <?php if($logoUrl): ?>
                    <img src="<?php echo e($logoUrl); ?>"
                         alt="<?php echo e($co->name); ?>"
                         class="h-16 w-auto max-w-[140px] object-contain rounded-lg">
                    <?php else: ?>
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white text-xl font-black tracking-tight"
                         style="background:<?php echo e($colors['bg']); ?>">
                        <?php echo e(strtoupper(substr($co?->name ?? 'IB', 0, 2))); ?>

                    </div>
                    <?php endif; ?>
                </div>

                
                <div class="min-w-0">
                    <p class="text-base font-bold text-gray-900 leading-tight">
                        <?php echo e($co?->trade_name ?? $co?->name ?? config('app.name')); ?>

                    </p>
                    <?php if($co?->slogan): ?>
                    <p class="text-xs text-gray-400 italic mt-0.5"><?php echo e($co->slogan); ?></p>
                    <?php endif; ?>
                    <div class="mt-2 space-y-0.5">
                        <?php if($co?->address || $co?->city): ?>
                        <p class="text-xs text-gray-500 flex items-start gap-1.5">
                            <svg class="w-3 h-3 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <?php echo e(collect([$co->address, $co->city])->filter()->implode(', ')); ?>

                        </p>
                        <?php endif; ?>
                        <?php if($co?->phone): ?>
                        <p class="text-xs text-gray-500 flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <?php echo e($co->phone); ?><?php if($co->phone2): ?> · <?php echo e($co->phone2); ?><?php endif; ?>
                        </p>
                        <?php endif; ?>
                        <?php if($co?->email): ?>
                        <p class="text-xs text-gray-500 flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <?php echo e($co->email); ?>

                        </p>
                        <?php endif; ?>
                        <?php if($co?->ifu || $co?->rccm || $co?->nif): ?>
                        <p class="text-xs text-gray-400 mt-1 font-mono">
                            <?php if($co->ifu): ?> IFU : <?php echo e($co->ifu); ?> <?php endif; ?>
                            <?php if($co->rccm): ?> · RCCM : <?php echo e($co->rccm); ?> <?php endif; ?>
                            <?php if($co->nif): ?> · NIF : <?php echo e($co->nif); ?> <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div class="sm:text-right flex-shrink-0">

                
                <div class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-black tracking-widest uppercase mb-3"
                     style="background:<?php echo e($colors['light']); ?>;color:<?php echo e($colors['bg']); ?>">
                    <?php echo e($docType); ?>

                </div>

                
                <p class="text-2xl font-black text-gray-900 font-mono leading-none"><?php echo e($docNumber); ?></p>

                
                <div class="mt-2 space-y-1">
                    <p class="text-xs text-gray-500">
                        <span class="font-medium text-gray-700">Date :</span> <?php echo e($docDate); ?>

                    </p>
                    <?php $__currentLoopData = $docExtra; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $extra): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <p class="text-xs text-gray-500">
                        <span class="font-medium text-gray-700"><?php echo e($extra['label']); ?> :</span> <?php echo e($extra['value']); ?>

                    </p>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                
                <?php if($docStatus): ?>
                <div class="mt-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo e($docStatus['class']); ?>">
                        <?php echo e($docStatus['label']); ?>

                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\iboa\resources\views/partials/_doc-letterhead.blade.php ENDPATH**/ ?>