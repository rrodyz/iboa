import { sparklinePoints } from '../../Utils/format';

// [REACT-01C] Carte KPI production — présentation uniquement, valeurs déjà
// formatées côté appelant (miroir des 2 rangées de cartes du Blade historique).
export default function ProductionKpiCard({ label, value, sub, alert = false, iconPath, sparkSerie, sparkColor = '#10b981' }) {
    return (
        <div className="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5">
            <div className="flex items-center justify-between">
                <p className="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{label}</p>
                {iconPath && (
                    <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={iconPath} />
                    </svg>
                )}
            </div>
            <p className={`font-bold tabular-nums mt-1 ${sparkSerie ? 'text-[17px]' : 'text-[15px]'} ${alert ? 'text-red-600' : 'text-gray-900'}`}>{value}</p>
            {sub && <p className="text-[11px] text-gray-400 truncate" title={sub}>{sub}</p>}
            {sparkSerie && (
                <svg viewBox="0 0 100 28" className="w-full h-6 mt-1" preserveAspectRatio="none">
                    <polyline points={sparklinePoints(sparkSerie)} fill="none" stroke={sparkColor} strokeWidth="1.5" strokeLinejoin="round" strokeLinecap="round" />
                </svg>
            )}
        </div>
    );
}
