import { usePage } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { fmt } from '../../../Utils/format';

// [REACT-01D] Portage 1:1 de resources/views/production/orders/mts.blade.php.
// besoin/etat/dispo/cible/seuil/plan viennent tels quels de
// NetRequirementService (via ProductionOrderController::mts()) — aucun terme
// n'est recalculé ici, y compris l'état (non_parametre/rupture/sous_min/ok).

const ETAT_BADGE = {
    rupture: ['Rupture', 'bg-red-100 text-red-800'],
    sous_min: ['Sous le seuil', 'bg-amber-100 text-amber-800'],
    ok: ['OK', 'bg-green-100 text-green-800'],
};

export default function MtsIndex({ rows, links }) {
    const { auth } = usePage().props;

    return (
        <AppLayout>
            <div className="space-y-3">
                <div className="bg-white border border-gray-300 rounded-[4px]">
                    <div className="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
                        <div>
                            <h2 className="text-[22px] font-bold text-gray-900 leading-tight">Planification MTS — production pour stock</h2>
                            <p className="text-[11.5px] text-gray-400">Articles fabriqués pour le stock (fer à béton…). Besoin net = cible + sécurité + demande client ferme − disponible − OF planifiés − réceptions attendues.</p>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <a href={links.mtoUrl} className="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Tableau de bord MTO</a>
                            <a href={links.eligibleUrl} className="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Éligibles MTO</a>
                            <a href={links.ofIndexUrl} className="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Ordres de fabrication</a>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-[#eef5f0] border-b border-gray-300">
                            <tr>
                                {['Article', 'Physique', 'Réservé', 'Disponible', 'Demande client', 'Attendu', 'Seuil', 'Cible', 'OF planifiés', 'Besoin net', 'État', ''].map((h, i) => (
                                    <th key={i} className={`px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide ${i >= 1 && i <= 9 ? 'text-right' : i === 10 ? 'text-center' : 'text-left'} ${i === 11 ? 'w-28' : ''}`}
                                        title={i === 4 ? 'Commandes clients confirmées non encore livrées' : i === 5 ? 'Commandes fournisseurs engagées et non soldées' : undefined}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {rows.length === 0 && (
                                <tr><td colSpan={12} className="px-3 py-6 text-center text-gray-400 text-[12.5px]">Aucun article MTS actif.</td></tr>
                            )}
                            {rows.map((r, i) => {
                                const [etatLabel, etatCls] = ETAT_BADGE[r.etat] ?? [null, null];
                                return (
                                    <tr key={i} className="hover:bg-[#eef5f0]/40">
                                        <td className="px-3 py-1.5">
                                            <span className="font-medium text-gray-900">{r.productName}</span>
                                            <span className="text-[11px] text-gray-400 font-mono ml-1">{r.productReference}</span>
                                        </td>
                                        <td className="px-3 py-1.5 text-right tabular-nums">{fmt(r.physique)}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-500">{fmt(r.reserve)}</td>
                                        <td className={`px-3 py-1.5 text-right tabular-nums font-semibold ${r.dispo <= 0 ? 'text-red-600' : ''}`}>{fmt(r.dispo)}</td>
                                        <td className={`px-3 py-1.5 text-right tabular-nums ${r.client > 0 ? 'text-gray-900 font-medium' : 'text-gray-400'}`}>{fmt(r.client)}</td>
                                        <td className={`px-3 py-1.5 text-right tabular-nums ${r.recu > 0 ? 'text-indigo-700' : 'text-gray-400'}`}>{fmt(r.recu)}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-500">{r.seuil > 0 ? fmt(r.seuil) : '—'}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-500">{r.cible > 0 ? fmt(r.cible) : '—'}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-blue-700">{fmt(r.plan)}</td>
                                        <td className={`px-3 py-1.5 text-right tabular-nums font-bold ${r.besoin > 0 ? 'text-amber-700' : 'text-gray-400'}`}>{fmt(r.besoin)}</td>
                                        <td className="px-3 py-1.5 text-center">
                                            {r.etat === 'non_parametre' ? (
                                                <a href={r.editUrl} className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700 hover:bg-gray-300"
                                                   title="Ni minimum, ni maximum, ni point de commande, ni stock de sécurité : le besoin ne peut pas être calculé.">Seuil non défini</a>
                                            ) : (
                                                <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${etatCls}`}>{etatLabel}</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-1.5 text-right">
                                            {r.canCreateOf && (
                                                <a href={r.createOfUrl} className="inline-flex items-center gap-1 px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Créer OF MTS</a>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
                    <span>Module : <span className="text-white font-semibold">production — planification MTS</span></span>
                    <span className="ml-auto">Utilisateur : <span className="text-white font-semibold">{auth?.user?.name}</span></span>
                </div>
            </div>
        </AppLayout>
    );
}
