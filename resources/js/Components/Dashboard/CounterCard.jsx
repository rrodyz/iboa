import { fmt } from '../../Utils/format';

export default function CounterCard({ label, value, sub, subClass, url, iconClass, iconPath }) {
    return (
        <a href={url} className="bg-white border border-gray-300 rounded-[4px] px-4 py-3 flex items-center gap-3 hover:border-emerald-400 transition-colors group">
            <span className={`w-10 h-10 rounded-[6px] ${iconClass} flex items-center justify-center shrink-0`}>
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d={iconPath} />
                </svg>
            </span>
            <div className="min-w-0 flex-1">
                <p className="text-[12px] font-semibold text-gray-600">{label}</p>
                <p className="text-[20px] font-bold text-gray-900 tabular-nums leading-tight">{fmt(value)}</p>
                {sub && <p className={`text-[11px] ${subClass}`}>{sub}</p>}
            </div>
            <svg className="w-4 h-4 text-gray-300 group-hover:text-emerald-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    );
}
