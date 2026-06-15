<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9pt; color: #1f2937; margin: 0; padding: 28px 32px; }
        .header { border-bottom: 2px solid #ea580c; padding-bottom: 10px; margin-bottom: 14px; overflow: hidden; }
        .company { font-size: 14pt; font-weight: bold; color: #ea580c; }
        .sub { font-size: 8pt; color: #6b7280; }
        h1 { font-size: 12pt; margin: 4px 0 2px; }
        .period { font-size: 8.5pt; color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; padding: 5px 7px; border-bottom: 1px solid #d1d5db; color: #374151; font-size: 8pt; }
        td { padding: 4px 7px; border-bottom: 1px solid #eee; font-size: 8.5pt; }
        th.num, td.num { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #ea580c; background: #fff7ed; }
        .footer { position: fixed; bottom: 12px; left: 32px; right: 32px; font-size: 7pt; color: #9ca3af; text-align: center; border-top: 1px solid #eee; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company"><?php echo e($company->name ?? 'Société'); ?></div>
        <?php if($company?->ifu): ?><div class="sub">IFU : <?php echo e($company->ifu); ?></div><?php endif; ?>
    </div>

    <h1><?php echo e($report['title']); ?></h1>
    <div class="period">Période : du <?php echo e(\Carbon\Carbon::parse($from)->format('d/m/Y')); ?> au <?php echo e(\Carbon\Carbon::parse($to)->format('d/m/Y')); ?> · édité le <?php echo e(now()->format('d/m/Y H:i')); ?></div>

    <table>
        <thead>
            <tr>
                <?php $__currentLoopData = $report['headers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <th class="<?php echo e(in_array($i, $report['numeric']) ? 'num' : ''); ?>"><?php echo e($h); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $report['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <td class="<?php echo e(in_array($i, $report['numeric']) ? 'num' : ''); ?>"><?php echo e(in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell); ?></td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="<?php echo e(count($report['headers'])); ?>" style="text-align:center;color:#9ca3af;padding:20px;">Aucune donnée.</td></tr>
            <?php endif; ?>
        </tbody>
        <?php if($report['totals']): ?>
        <tfoot>
            <tr>
                <?php $__currentLoopData = $report['totals']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <td class="<?php echo e(in_array($i, $report['numeric']) ? 'num' : ''); ?>"><?php echo e(in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell); ?></td>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <div class="footer"><?php echo e($company->name ?? ''); ?> — Rapport de production généré par l'ERP</div>
</body>
</html>
<?php /**PATH C:\laragon\www\iboa\resources\views/production/reports/pdf.blade.php ENDPATH**/ ?>