// [REACT-01B] Formatage pur affichage — aucun calcul métier. Miroir exact des
// helpers $fmt / $trendBadge de resources/views/dashboard.blade.php.
export function fmt(n) {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(Number(n) || 0));
}

export function fmtF(n) {
    return fmt(n) + ' F';
}

// trend: { value: number|null, direction: 'up'|'down'|'neutral' } — déjà
// calculé côté serveur (DashboardController::trend()), jamais recalculé ici.
export function trendLabel(trend, inverse = false) {
    if (!trend || trend.value === null) return null;
    const up = trend.direction === 'up';
    const good = inverse ? !up : up;
    return {
        text: (up ? '+' : '-') + new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(trend.value) + ' %',
        arrow: up ? '↗' : '↘',
        className: good ? 'text-emerald-600' : 'text-red-600',
    };
}

// [REACT-01C] Portage 1:1 du helper $spark() de production/dashboard.blade.php
// — normalise une série (ex: prod7Days) en points SVG polyline 100x28.
// Pure présentation, aucune donnée métier recalculée.
export function sparklinePoints(serie) {
    if (!serie || serie.length === 0) return '';
    const max = Math.max(...serie, 1);
    return serie
        .map((v, i) => {
            const x = Math.round(i * (100 / Math.max(serie.length - 1, 1)) * 10) / 10;
            const y = Math.round((26 - (v / max) * 22) * 10) / 10;
            return `${x},${y}`;
        })
        .join(' ');
}
