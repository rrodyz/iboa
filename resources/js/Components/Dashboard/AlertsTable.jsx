const NIVEAU_BADGES = {
    critique: ['Critique', 'bg-red-600 text-white'],
    alerte: ['Alerte', 'bg-amber-100 text-amber-800 border border-amber-300'],
    info: ['Information', 'bg-blue-50 text-blue-700 border border-blue-200'],
};

export default function AlertsTable({ items, validationsUrl }) {
    const today = new Date().toLocaleDateString('fr-FR');

    return (
        <div className="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <div className="px-4 py-2 border-b border-gray-200"><p className="text-[13px] font-bold text-gray-800">Alertes et points de vigilance</p></div>
            <table className="w-full text-[12.5px]">
                <thead className="bg-[#3b4248]">
                    <tr>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Date</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Niveau</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Message</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Module</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {items.length === 0 && (
                        <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Aucune alerte — tout est sous contrôle.</td></tr>
                    )}
                    {items.map((al, i) => {
                        const [nLabel, nCls] = NIVEAU_BADGES[al.niveau] ?? ['—', 'bg-gray-50 text-gray-600'];
                        return (
                            <tr key={i} className="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                <td className="px-3 py-1.5 text-gray-500 tabular-nums whitespace-nowrap">{today}</td>
                                <td className="px-3 py-1.5"><span className={`inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-semibold ${nCls}`}>{nLabel}</span></td>
                                <td className="px-3 py-1.5 text-gray-700"><a href={al.url} className="hover:text-emerald-700">{al.message}</a></td>
                                <td className="px-3 py-1.5 text-gray-500">{al.module}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            <div className="px-4 py-2 border-t border-gray-100">
                <a href={validationsUrl} className="text-[12px] font-medium text-emerald-700 hover:text-emerald-900">Voir toutes les alertes →</a>
            </div>
        </div>
    );
}
