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
