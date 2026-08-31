import { fmtF, trendLabel } from '../../Utils/format';

const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function monthLabel(offset = 0) {
    const d = new Date();
    d.setMonth(d.getMonth() - offset);
    const m = MONTHS[d.getMonth()];
    return offset === 0 ? m.charAt(0).toUpperCase() + m.slice(1) + ' ' + d.getFullYear() : m;
}

export default function KpiCard({ label, value, trend, inverse = false, iconClass, iconPath }) {
    const t = trendLabel(trend, inverse);

    return (
        <div className="bg-white border border-gray-300 rounded-[4px] px-4 py-3 flex items-center gap-3">
            <span className={`w-11 h-11 rounded-full ${iconClass} flex items-center justify-center shrink-0`}>
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={iconPath} />
                </svg>
            </span>
            <div className="min-w-0">
                <p className="text-[12px] font-semibold text-gray-600">{label}</p>
                <p className="text-[20px] font-bold text-gray-900 tabular-nums leading-tight">{fmtF(value)}</p>
                <p className="text-[11px] text-gray-400">
                    {monthLabel(0)}
                    {t && (
                        <>
                            {' '}
                            <span className={`${t.className} font-semibold`}>{t.arrow} {t.text}</span>{' '}
                            <span className="text-gray-400">vs {monthLabel(1)}</span>
                        </>
                    )}
                </p>
            </div>
        </div>
    );
}
