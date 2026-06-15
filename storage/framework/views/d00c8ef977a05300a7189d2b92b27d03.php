

<script data-turbo-eval="false">
(function(){var d=localStorage.getItem('erp_dark');if(d==='true'||(d===null&&window.matchMedia('(prefers-color-scheme:dark)').matches))document.documentElement.classList.add('dark');})();
</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

<script>
    window.addEventListener('unhandledrejection', function (e) {
        var r = e.reason, m = (r && (r.message || r.name)) || String(r || '');
        if (/NetworkError|Failed to fetch|Load failed|aborted|AbortError/i.test(m)) {
            e.preventDefault();
        }
    });
</script>

<meta name="turbo-prefetch" content="false">
<title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> — <?php echo e(config('app.name')); ?></title>

<link rel="icon" href="data:image/svg+xml,&lt;svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22&gt;&lt;rect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%234f46e5%22/&gt;&lt;text x=%2250%22 y=%2270%22 font-size=%2270%22 font-family=%22Arial%22 font-weight=%22700%22 fill=%22white%22 text-anchor=%22middle%22&gt;A&lt;/text&gt;&lt;/svg&gt;">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>
<?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>


<link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.tailwindcss.min.css">


<?php echo $__env->yieldPushContent('styles'); ?>


<?php echo $__env->yieldPushContent('head_scripts'); ?>


<script data-turbo-eval="false">
    window.fcfa = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n) + ' FCFA';
</script>


<script src="https://code.jquery.com/jquery-3.7.1.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.tailwindcss.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js" data-turbo-eval="false"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" data-turbo-eval="false"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js" data-turbo-eval="false"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js" data-turbo-eval="false"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js" data-turbo-eval="false"></script>
<?php /**PATH C:\laragon\www\iboa\resources\views/partials/layout/_head.blade.php ENDPATH**/ ?>