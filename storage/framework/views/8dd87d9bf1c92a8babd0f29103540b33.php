<div class="tbl-scroll">
    <table class="tbl w-full">
        <thead>
            <tr>
                <th class="text-left">Date</th>
                <th class="text-left">Journal</th>
                <th class="text-left">N° pièce</th>
                <th class="text-left">Libellé</th>
                <th class="text-right">Débit</th>
                <th class="text-right">Crédit</th>
                <th class="text-right">Solde cumulé</th>
            </tr>
        </thead>
        <tbody>
            <?php $running = 0; ?>
            <?php $__empty_1 = true; $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $running += $line->debit - $line->credit; ?>
            <tr>
                <td class="text-gray-600 whitespace-nowrap">
                    <?php echo e($line->journalEntry?->entry_date?->format('d/m/Y') ?? '—'); ?>

                </td>
                <td class="whitespace-nowrap">
                    <span class="font-mono text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">
                        <?php echo e($line->journalEntry?->journalType?->code ?? '—'); ?>

                    </span>
                </td>
                <td class="whitespace-nowrap">
                    <?php if($line->journalEntry): ?>
                    <a href="<?php echo e(route('comptabilite.journaux.show', $line->journal_entry_id)); ?>"
                       class="font-mono text-violet-600 hover:text-violet-800 hover:underline text-xs">
                        <?php echo e($line->journalEntry->number ?? '—'); ?>

                    </a>
                    <?php else: ?>
                    <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-gray-700 max-w-xs truncate" title="<?php echo e($line->label ?: $line->journalEntry?->description); ?>">
                    <?php echo e($line->label ?: $line->journalEntry?->description ?: '—'); ?>

                </td>
                <td class="text-right tabular-nums <?php echo e($line->debit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300'); ?>">
                    <?php echo e($line->debit > 0 ? number_format($line->debit, 0, ',', ' ') : '—'); ?>

                </td>
                <td class="text-right tabular-nums <?php echo e($line->credit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300'); ?>">
                    <?php echo e($line->credit > 0 ? number_format($line->credit, 0, ',', ' ') : '—'); ?>

                </td>
                <td class="text-right tabular-nums whitespace-nowrap
                    <?php echo e($running > 0 ? 'text-blue-700' : ($running < 0 ? 'text-red-700' : 'text-gray-400')); ?>">
                    <?php if($running == 0): ?>
                        —
                    <?php else: ?>
                        <?php echo e(number_format(abs($running), 0, ',', ' ')); ?>

                        <span class="text-xs font-normal ml-0.5"><?php echo e($running > 0 ? 'D' : 'C'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Aucun mouvement.</td>
            </tr>
            <?php endif; ?>
        </tbody>

        
        <?php if($lines->isNotEmpty()): ?>
        <?php
            $footDebit   = $lines->sum('debit');
            $footCredit  = $lines->sum('credit');
            $footBalance = $footDebit - $footCredit;
        ?>
        <tfoot>
            <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold">
                <td colspan="4" class="text-xs text-gray-600 uppercase tracking-wider">
                    Total — <?php echo e($lines->count()); ?> ligne(s)
                </td>
                <td class="text-right tabular-nums text-blue-700">
                    <?php echo e(number_format($footDebit, 0, ',', ' ')); ?>

                </td>
                <td class="text-right tabular-nums text-red-700">
                    <?php echo e(number_format($footCredit, 0, ',', ' ')); ?>

                </td>
                <td class="text-right tabular-nums whitespace-nowrap
                    <?php echo e($footBalance > 0 ? 'text-blue-700' : ($footBalance < 0 ? 'text-red-700' : 'text-gray-400')); ?>">
                    <?php if($footBalance == 0): ?>
                        <span class="text-gray-400 font-normal">Équilibré</span>
                    <?php else: ?>
                        <?php echo e(number_format(abs($footBalance), 0, ',', ' ')); ?>

                        <span class="text-xs font-normal ml-0.5"><?php echo e($footBalance > 0 ? 'D' : 'C'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>
<?php /**PATH C:\laragon\www\iboa\resources\views/comptabilite/_grand-livre-table.blade.php ENDPATH**/ ?>