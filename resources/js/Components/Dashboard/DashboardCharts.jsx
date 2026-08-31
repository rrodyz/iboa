import Chart from 'react-apexcharts';
import { fmtF } from '../../Utils/format';

// [REACT-01B] Portage 1:1 des options ApexCharts de dashboard.blade.php
// (mêmes couleurs, mêmes formatters, même agencement) — présentation
// uniquement, aucune donnée recalculée ici.

function ChartFrame({ title, subtitle, badge, children }) {
    return (
        <div className="bg-white border border-gray-300 rounded-[4px] p-3">
            <div className="flex items-center justify-between mb-1">
                <p className="text-[13px] font-bold text-gray-800">
                    {title} {subtitle && <span className="font-normal text-gray-500">({subtitle})</span>}
                </p>
                <span className="text-[11px] text-gray-400 border border-gray-200 rounded-[3px] px-2 py-0.5">{badge}</span>
            </div>
            <div className="min-h-[220px]">{children}</div>
        </div>
    );
}

export function CaAnnuelChart({ caAnnuel }) {
    const year = new Date().getFullYear();
    const options = {
        chart: { type: 'bar', height: 230, toolbar: { show: false } },
        xaxis: { categories: caAnnuel.labels, labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { formatter: (v) => (v >= 1e6 ? (v / 1e6).toFixed(1) + ' M' : v >= 1e3 ? (v / 1e3).toFixed(0) + ' k' : v) } },
        colors: ['#93c5fd', '#1d4ed8'],
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 2 } },
        dataLabels: { enabled: false },
        legend: { fontSize: '11px' },
        tooltip: { y: { formatter: fmtF } },
        grid: { strokeDashArray: 3 },
    };
    const series = [
        { name: `CA ${year - 1}`, data: caAnnuel.prev },
        { name: `CA ${year}`, data: caAnnuel.cur },
    ];

    return (
        <ChartFrame title="Évolution du chiffre d'affaires" subtitle="HT" badge="Année en cours">
            <Chart options={options} series={series} type="bar" height={230} />
        </ChartFrame>
    );
}

export function TresorerieChart({ treso30, treso30Labels }) {
    const options = {
        chart: { type: 'line', height: 230, toolbar: { show: false } },
        xaxis: { categories: treso30Labels, tickAmount: 6, labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { formatter: (v) => (Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + ' M' : (v / 1e3).toFixed(0) + ' k') } },
        colors: ['#059669'],
        stroke: { curve: 'smooth', width: 2.5 },
        markers: { size: 3 },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: fmtF } },
        grid: { strokeDashArray: 3 },
    };
    const series = [{ name: 'Trésorerie', data: treso30 }];

    return (
        <ChartFrame title="Trésorerie" subtitle="disponible" badge="30 derniers jours">
            <Chart options={options} series={series} type="line" height={230} />
        </ChartFrame>
    );
}

export function CaParFamilleChart({ caParFamille }) {
    if (!caParFamille || caParFamille.length === 0) {
        return (
            <ChartFrame title="Répartition du CA par famille" subtitle="YTD" badge="Année en cours">
                <p className="text-[12px] text-gray-400 text-center pt-16">Aucune vente sur l'exercice.</p>
            </ChartFrame>
        );
    }

    const options = {
        chart: { type: 'donut', height: 230 },
        labels: caParFamille.map((r) => r.label),
        colors: ['#1d4ed8', '#059669', '#f59e0b', '#8b5cf6', '#9ca3af'],
        legend: {
            position: 'bottom', horizontalAlign: 'left', fontSize: '11px', itemMargin: { horizontal: 8, vertical: 2 },
            formatter: (label, opts) => {
                const v = opts.w.globals.series[opts.seriesIndex];
                const t = opts.w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                const pct = t > 0 ? (v / t * 100).toFixed(1).replace('.', ',') : '0';
                return `${label} — ${pct} %`;
            },
        },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        name: { fontSize: '11px', color: '#6b7280', offsetY: -4 },
                        value: {
                            fontSize: '14px', fontWeight: 700, offsetY: 2,
                            formatter: (v) => {
                                v = parseFloat(v);
                                return v >= 1e6 ? (v / 1e6).toFixed(1).replace('.', ',') + ' M F' : fmtF(v);
                            },
                        },
                        total: {
                            show: true, label: 'Total HT', fontSize: '11px', color: '#6b7280',
                            formatter: (w) => {
                                const t = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                return t >= 1e6 ? (t / 1e6).toFixed(2).replace('.', ',') + ' M F' : fmtF(t);
                            },
                        },
                    },
                },
            },
        },
        tooltip: { y: { formatter: fmtF } },
    };
    const series = caParFamille.map((r) => r.value);

    return (
        <ChartFrame title="Répartition du CA par famille" subtitle="YTD" badge="Année en cours">
            <Chart options={options} series={series} type="donut" height={230} />
        </ChartFrame>
    );
}
