import { usePage } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { fmt } from '../../../Utils/format';

// [REACT-01C] Portage 1:1 de resources/views/production/orders/mto.blade.php.
// La colonne « Statut financier » et le bouton « Créer OF » n'affichent JAMAIS
// une décision recalculée ici — row.eligible et row.canCreateOf viennent tels
// quels de ProductionOrderController::mtoDashboard() (voir Phase 4/13).

export default function MtoDashboard({ rows, links }) {
    const { auth } = usePage().props;

    return (
        <AppLayout>
            <div className="space-y-3">
                <div className="bg-white border border-gray-300 rounded-[4px]">
                    <div className="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
                        <div>
                            <h2 className="text-[22px] font-bold text-gray-900 leading-tight">Tableau de bord MTO — production à la commande</h2>
                            <p className="text-[11.5px] text-gray-400">Toute ligne de commande fabriquée à la commande (tôle bac), avec ou sans OF déjà créé.</p>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <a href={links.eligibleUrl} className="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Éligibles sans OF</a>
                            <a href={links.mtsUrl} className="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Planification MTS</a>
                        </div>
                    </div>
                </div>

                <div className="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead className="bg-[#eef5f0] border-b border-gray-300">
                            <tr>
                                {['Commande', 'Client', 'Article', 'Commandée', 'Stock dispo.', 'Produite', 'Restant à produire', 'OF existant', 'Statut financier', 'Date requise', ''].map((h, i) => (
                                    <th key={i} className={`px-3 py-1.5 text-[11px] font-bold text-emerald-900 uppercase tracking-wide ${i >= 3 && i <= 6 ? 'text-right' : i === 8 ? 'text-center' : 'text-left'} ${i === 10 ? 'w-32' : ''}`}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {rows.length === 0 && (
                                <tr><td colSpan={11} className="px-3 py-6 text-center text-gray-400 text-[12.5px]">Aucune ligne MTO ouverte.</td></tr>
                            )}
                            {rows.map((r, i) => (
                                <tr key={i} className="hover:bg-[#eef5f0]/40">
                                    <td className="px-3 py-1.5"><a href={r.orderShowUrl} className="font-semibold text-emerald-800 hover:underline">{r.orderNumber}</a></td>
                                    <td className="px-3 py-1.5 text-gray-900">{r.clientName ?? '—'}</td>
                                    <td className="px-3 py-1.5">
                                        <span className="text-gray-900">{r.productName}</span>
                                        <span className="text-[11px] text-gray-400 font-mono ml-1">{r.productReference}</span>
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(r.commandee)}</td>
                                    <td className={`px-3 py-1.5 text-right tabular-nums ${r.dispo <= 0 ? 'text-red-600' : ''}`}>{fmt(r.dispo)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-blue-700">{fmt(r.produite)}</td>
                                    <td className={`px-3 py-1.5 text-right tabular-nums font-bold ${r.restante > 0 ? 'text-amber-700' : 'text-gray-400'}`}>{fmt(r.restante)}</td>
                                    <td className="px-3 py-1.5">
                                        {r.of ? (
                                            <>
                                                <a href={r.of.showUrl} className="text-emerald-800 hover:underline font-medium">{r.of.number}</a>
                                                <span className="text-[10.5px] text-gray-400 ml-1">{r.of.status}</span>
                                            </>
                                        ) : (
                                            <span className="text-gray-400 text-[12px]">Aucun</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-1.5 text-center">
                                        {r.eligible ? (
                                            <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Éligible</span>
                                        ) : (
                                            <span className="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600">Non couverte</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-1.5 text-gray-600 text-[12px]">{r.deliveryDate ?? '—'}</td>
                                    <td className="px-3 py-1.5 text-right">
                                        {r.canCreateOf && (
                                            <a href={r.createOfUrl} className="inline-flex items-center gap-1 px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Créer OF</a>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
                    <span>Module : <span className="text-white font-semibold">production — tableau de bord MTO</span></span>
                    <span className="ml-auto">Utilisateur : <span className="text-white font-semibold">{auth?.user?.name}</span></span>
                </div>
            </div>
        </AppLayout>
    );
}
