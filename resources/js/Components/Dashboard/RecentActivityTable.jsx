const TYPE_BADGES = {
    Order: ['Commande client', 'bg-blue-50 text-blue-700 border-blue-200'],
    Invoice: ['Facture ventes', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
    DeliveryNote: ['Bon de livraison', 'bg-sky-50 text-sky-700 border-sky-200'],
    PurchaseOrder: ['Achat', 'bg-violet-50 text-violet-700 border-violet-200'],
    Reception: ['Achat', 'bg-violet-50 text-violet-700 border-violet-200'],
    SupplierInvoice: ['Achat', 'bg-violet-50 text-violet-700 border-violet-200'],
    ProductionOrder: ['OF', 'bg-lime-50 text-lime-700 border-lime-200'],
    Quote: ['Devis', 'bg-indigo-50 text-indigo-700 border-indigo-200'],
    ClientPayment: ['Encaissement', 'bg-teal-50 text-teal-700 border-teal-200'],
    Client: ['Client', 'bg-gray-50 text-gray-700 border-gray-200'],
    JournalEntry: ['Écriture', 'bg-amber-50 text-amber-700 border-amber-200'],
    CreditNote: ['Avoir', 'bg-rose-50 text-rose-700 border-rose-200'],
    StockTransfer: ['Transfert stock', 'bg-cyan-50 text-cyan-700 border-cyan-200'],
};

const ACTION_LABELS = {
    created: 'créé(e)', updated: 'modifié(e)', modified: 'modifié(e)',
    deleted: 'supprimé(e)', validated: 'validé(e)', sent: 'envoyé(e)',
    paid: 'payé(e)', confirmed: 'confirmé(e)', submitted: 'soumis(e)',
    cancelled: 'annulé(e)', launched: 'lancé(e)',
};

function frAction(action, tLabel) {
    const a = (action || '').toLowerCase();
    for (const [en, fr] of Object.entries(ACTION_LABELS)) {
        if (a.includes(en)) return `${tLabel} ${fr}`;
    }
    return `${tLabel} — ${(action || '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())}`;
}

function limit(str, n) {
    if (!str) return '';
    return str.length > n ? str.slice(0, n) + '…' : str;
}

export default function RecentActivityTable({ items, auditUrl }) {
    return (
        <div className="bg-white border border-gray-300 rounded-[4px] overflow-hidden">
            <div className="px-4 py-2 border-b border-gray-200"><p className="text-[13px] font-bold text-gray-800">Activités récentes</p></div>
            <table className="w-full text-[12.5px]">
                <thead className="bg-[#3b4248]">
                    <tr>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase whitespace-nowrap">Date/Heure</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Type</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Description</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase whitespace-nowrap">Tiers / Référence</th>
                        <th className="px-3 py-1.5 text-left text-[11px] font-semibold text-white uppercase">Utilisateur</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {items.length === 0 && (
                        <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Aucune activité récente.</td></tr>
                    )}
                    {items.map((a, i) => {
                        const [tLabel, tCls] = TYPE_BADGES[a.modelType] ?? [a.modelType || '—', 'bg-gray-50 text-gray-600 border-gray-200'];
                        return (
                            <tr key={i} className="odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                <td className="px-3 py-1.5 text-gray-500 tabular-nums whitespace-nowrap">{a.createdAt}</td>
                                <td className="px-3 py-1.5"><span className={`inline-flex px-2 py-0.5 rounded-[3px] text-[10.5px] font-medium border ${tCls} whitespace-nowrap`}>{tLabel}</span></td>
                                <td className="px-3 py-1.5 text-gray-700">{frAction(a.action, tLabel)}</td>
                                <td className="px-3 py-1.5">
                                    {a.docRef && <span className="block font-mono text-[11px] text-blue-700 whitespace-nowrap">{a.docRef}</span>}
                                    {a.tiers && <span className="block text-gray-500 text-[11px] whitespace-nowrap">{limit(a.tiers, 16)}</span>}
                                    {!a.docRef && !a.tiers && <span className="text-gray-400">—</span>}
                                </td>
                                <td className="px-3 py-1.5 text-gray-600 whitespace-nowrap">{limit(a.userName || '—', 12)}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            <div className="px-4 py-2 border-t border-gray-100">
                <a href={auditUrl} className="text-[12px] font-medium text-emerald-700 hover:text-emerald-900">Voir toutes les activités →</a>
            </div>
        </div>
    );
}
