{{--
    Dashboard — Scripts ApexCharts.
    Définit kpiCounter, caChartFromConfig, et tous les rendus de graphiques
    (sparklines, CA évolution, donut trésorerie, cashflow, top lists).
--}}
<script>
/* Tout le code est dans un IIFE unique : les const sont scopées ici et ne
   causent pas de redeclaration lors des re-évaluations Turbo. Les factories
   Alpine sont exposées via window.* pour rester accessibles depuis x-data. */
(function () {

/* ══════════════════════════════════════════════════════════════
   Helpers
══════════════════════════════════════════════════════════════ */
const fmtNum = v => Math.round(Number(v) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
const fmt  = v => fmtNum(v) + ' F';
const fmt0 = v => fmtNum(v);

const baseChart = {
    chart:      { toolbar:{show:false}, fontFamily:'Figtree, ui-sans-serif, sans-serif', animations:{enabled:true,easing:'easeinout',speed:700} },
    grid:       { borderColor:'#f1f5f9', strokeDashArray:4, padding:{left:2,right:4,top:-8,bottom:0} },
    dataLabels: { enabled:false },
    tooltip:    { theme:'light', y:{formatter:fmt} },
};

/* ══════════════════════════════════════════════════════════════
   Alpine: dashboard hero (clock)
══════════════════════════════════════════════════════════════ */
window.dashboardHero = function () {
    return {
        clock: '',
        init() {
            const tick = () => {
                const d = new Date();
                this.clock = d.toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
            };
            tick();
            setInterval(tick, 1000);
        }
    };
};

/* ══════════════════════════════════════════════════════════════
   Alpine: KPI animated counter
══════════════════════════════════════════════════════════════ */
window.kpiCounter = function (target) {
    return {
        current: 0,
        target: Math.round(target || 0),
        init() {
            if (!this.target) return;
            const dur = 1100, start = performance.now(), self = this;
            const run = t => {
                const p = Math.min((t - start) / dur, 1);
                self.current = Math.round((1 - Math.pow(1 - p, 4)) * self.target);
                if (p < 1) requestAnimationFrame(run);
            };
            requestAnimationFrame(run);
        },
        formatted() {
            return fmt0(this.current);
        }
    };
};

/* ══════════════════════════════════════════════════════════════
   Alpine: CA chart with period toggle
══════════════════════════════════════════════════════════════ */
window.caChartFromConfig = function (cfg) {
    return window.caChart(cfg.ca7Days, cfg.ca7Labels, cfg.ca30Days, cfg.ca30Labels, cfg.caParMois);
};

window.caChart = function (data7, labels7, data30, labels30, monthly) {
    return {
        period: '6m',
        chart: null,
        datasets: {
            '7j':  { data: data7,  labels: labels7 },
            '30j': { data: data30, labels: labels30 },
            '6m':  { data: monthly.map(m => m.amount), labels: monthly.map(m => m.month) },
        },
        currentData() { return this.datasets[this.period].data; },
        currentLabels() { return this.datasets[this.period].labels; },
        sum()     { return this.currentData().reduce((a,b) => a+b, 0); },
        max()     { return Math.max(...this.currentData(), 0); },
        avg()     { const d = this.currentData(); return d.length ? d.reduce((a,b)=>a+b,0)/d.length : 0; },
        sumFormatted() { return fmt(this.sum()); },
        maxFormatted() { return fmt(this.max()); },
        avgFormatted() { return fmt(this.avg()); },

        init() {
            this.$nextTick(() => {
                this.chart = new ApexCharts(document.querySelector('#chart-ca'), this.buildOpts());
                this.chart.render();
            });
        },
        buildOpts() {
            return {
                ...baseChart,
                chart:  { ...baseChart.chart, type: 'area', height: 220 },
                series: [{ name: 'CA TTC', data: this.currentData() }],
                xaxis:  {
                    categories: this.currentLabels(),
                    labels: { style:{fontSize:'11px',colors:Array(20).fill('#94a3b8')}, rotate:0 },
                    axisBorder:{show:false}, axisTicks:{show:false},
                    tooltip:{enabled:false},
                },
                yaxis:  { labels:{ style:{fontSize:'11px',colors:['#94a3b8']}, formatter:fmt }, min:0 },
                colors: ['#4f46e5'],
                fill:   { type:'gradient', gradient:{shadeIntensity:1,opacityFrom:.28,opacityTo:.01,stops:[0,95]} },
                stroke: { curve:'smooth', width:2.5 },
                markers:{ size:4, strokeColors:'#fff', strokeWidth:2.5, fillOpacity:1, hover:{size:6} },
            };
        },
        setPeriod(p) {
            this.period = p;
            if (this.chart) {
                this.chart.updateOptions({
                    series: [{ name:'CA TTC', data: this.currentData() }],
                    xaxis: { categories: this.currentLabels() },
                });
            }
        }
    };
};

/* ══════════════════════════════════════════════════════════════
   Render sparklines + cashflow + donut
   Exécution directe (Turbo re-évalue les scripts body à chaque navigation).
   Les instances sont enregistrées dans window.__turboCleanups pour être
   détruites automatiquement avant la mise en cache Turbo.
══════════════════════════════════════════════════════════════ */
(function () {
    /* ── Les sparklines/cashflow/donut sont initialisés dans run().
       Sur un premier chargement (hard load), app.js est un module ES et s'exécute
       en différé — ApexCharts n'est donc pas encore disponible au moment où ce
       script synchrone s'exécute. On attend l'événement turbo:load (déclenché
       après que tous les modules ont tourné). Sur les navigations Turbo suivantes,
       window.ApexCharts est déjà disponible et on appelle run() directement.
    ── */
    function run() {
        const charts = [];

        const sparkBase = {
            chart:      { toolbar:{show:false}, sparkline:{enabled:true}, animations:{enabled:true,easing:'easeinout',speed:800} },
            dataLabels: { enabled:false },
            stroke:     { curve:'smooth', width:2.5 },
            tooltip:    { theme:'light', fixed:{enabled:false}, y:{formatter:fmt}, x:{show:false}, marker:{show:false} },
        };

        function renderChart(selector, options) {
            const el = document.querySelector(selector);
            if (!el) return;
            const c = new ApexCharts(el, options);
            c.render();
            charts.push(c);
        }

        // Sparkline: CA 14j
        renderChart('#spark-ca', {
            ...sparkBase,
            chart:  { ...sparkBase.chart, type:'area', height:60 },
            series: [{ name:'CA', data: @json($caDaily) }],
            colors: ['#4f46e5'],
            fill:   { type:'gradient', gradient:{shadeIntensity:1,opacityFrom:.22,opacityTo:0,stops:[0,100]} },
        });

        // Sparkline: Encaissements 14j
        renderChart('#spark-enc', {
            ...sparkBase,
            chart:  { ...sparkBase.chart, type:'area', height:60 },
            series: [{ name:'Enc.', data: @json($encDaily) }],
            colors: ['#10b981'],
            fill:   { type:'gradient', gradient:{shadeIntensity:1,opacityFrom:.22,opacityTo:0,stops:[0,100]} },
        });

        // Sparkline: 7 derniers jours
        renderChart('#spark-jour', {
            ...sparkBase,
            chart:  { ...sparkBase.chart, type:'area', height:60 },
            series: [{ name:'CA', data: @json($ca7Days) }],
            colors: ['#0ea5e9'],
            fill:   { type:'gradient', gradient:{shadeIntensity:1,opacityFrom:.22,opacityTo:0,stops:[0,100]} },
        });

        // Cashflow: grouped bar
        renderChart('#chart-cashflow', {
            ...baseChart,
            chart:   { ...baseChart.chart, type:'bar', height:220 },
            series:  [
                { name:'Encaissements', data: @json($encVsDec->pluck('enc')) },
                { name:'Décaissements', data: @json($encVsDec->pluck('dec')) },
            ],
            xaxis:   {
                categories: @json($encVsDec->pluck('month')),
                labels:{ style:{fontSize:'11px',colors:Array(6).fill('#94a3b8')} },
                axisBorder:{show:false}, axisTicks:{show:false},
            },
            yaxis:   { labels:{ style:{fontSize:'11px',colors:['#94a3b8']}, formatter:fmt }, min:0 },
            colors:  ['#10b981','#f43f5e'],
            plotOptions: { bar:{ borderRadius:4, columnWidth:'48%', borderRadiusApplication:'end' } },
            legend:  { show:false },
        });

        @if($cashAccounts->isNotEmpty())
        // Donut: caisses
        renderChart('#chart-caisse', {
            ...baseChart,
            chart:   { ...baseChart.chart, type:'donut', height:180 },
            series:  @json($cashAccounts->pluck('current_balance')),
            labels:  @json($cashAccounts->pluck('name')),
            colors:  ['#4f46e5','#10b981','#f59e0b','#6366f1','#ef4444','#3b82f6'],
            legend:  { show:false },
            plotOptions: { pie:{ donut:{ size:'65%',
                labels:{ show:true, total:{ show:true, label:'Total', fontSize:'11px', fontWeight:700, color:'#374151',
                    formatter: w => fmt(w.globals.seriesTotals.reduce((a,b)=>a+b,0))
                }}
            }}},
            stroke:  { width:0 },
            tooltip: { theme:'light', y:{formatter:fmt} },
            dataLabels: { enabled:false },
        });
        @endif

        // Enregistrer le cleanup pour turbo:before-cache
        window.__turboCleanups = window.__turboCleanups || [];
        window.__turboCleanups.push(() => charts.forEach(c => { try { c.destroy(); } catch(e) {} }));
    }

    // Hard load : app.js (module ES) n'a pas encore tourné → attendre turbo:load.
    // Turbo navigation : window.ApexCharts est déjà défini → exécuter immédiatement.
    if (window.ApexCharts) {
        run();
    } else {
        document.addEventListener('turbo:load', run, { once: true });
    }

}()); // fin IIFE sparklines

}()); // fin IIFE global
</script>
