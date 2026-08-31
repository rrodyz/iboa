import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { fmt, fmtF } from '../../../Utils/format';

// [REACT-01D] Portage 1:1 de resources/views/production/mrp/index.blade.php.
// Cet écran est le SEUL "MRP" réel du code : rupture bobines (matière première)
// vs stock_min → proposition de demande d'achat. available/min/deficit/
// avgCostPerKg/estimated viennent tels quels de MrpService::analyze() —
// aucune formule recalculée. Pas d'explosion BOM, pegging ou time-phasing
// sur cet écran (voir commentaire MrpController::index()) : ces notions
// n'existent pas ici, donc aucun panneau "Pourquoi ce besoin ?" n'est ajouté.

export default function MrpIndex({ shortfalls, stats, canGenerate, links }) {
    const { auth } = usePage().props;
    const [selected, setSelected] = useState(() => new Set(shortfalls.map((s) => s.productId)));
    const [submitting, setSubmitting] = useState(false);

    const toggle = (id) => {
        setSelected((prev) => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    };

    const submit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        router.post(links.generateUrl, { product_ids: Array.from(selected) }, {
            onFinish: () => setSubmitting(false),
        });
    };

    const kpis = [
        { label: 'Produits en déficit', value: fmt(stats.count), color: stats.count > 0 ? 'text-red-600' : 'text-gray-900', bg: 'bg-red-50' },
        { label: 'Déficit total', value: `${fmt(stats.deficit)} kg`, color: 'text-gray-900', bg: 'bg-amber-50' },
        { label: 'Coût estimé', value: fmtF(stats.estimated), color: 'text-gray-900', bg: 'bg-[#eef5f0]' },
    ];

    return (
        <AppLayout>
            <div className="space-y-4">
                <div>
                    <h1 className="text-[22px] font-bold text-gray-900 leading-tight">MRP — Réapprovisionnement bobines</h1>
                    <p className="text-[12px] text-gray-500">Déficits de matière première (poids disponible &lt; seuil minimum produit)</p>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    {kpis.map((k, i) => (
                        <div key={i} className="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
                            <div className={`w-9 h-9 rounded-[4px] ${k.bg} flex items-center justify-center shrink-0`}>
                                <span className={`w-2.5 h-2.5 rounded-full ${i === 0 && stats.count > 0 ? 'bg-red-500' : 'bg-gray-400'}`}></span>
                            </div>
                            <div className="min-w-0">
                                <p className="text-[11px] text-gray-500 truncate">{k.label}</p>
                                <p className={`text-[16px] font-bold ${k.color} tabular-nums leading-tight`}>{k.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <form onSubmit={submit} className="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                    <div className="flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                        <h2 className="text-[13px] font-bold text-gray-900">Déficits matière</h2>
                        {canGenerate && shortfalls.length > 0 && (
                            <button type="submit" disabled={submitting}
                                    className="bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 text-white text-[13px] font-semibold px-4 py-1.5 rounded-[4px] transition-colors">
                                Générer demande d'achat
                            </button>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-[12.5px] border-collapse">
                            <thead className="bg-[#3b4248] text-white">
                                <tr>
                                    <th className="px-3 py-1.5 w-8"></th>
                                    <th className="px-3 py-1.5 text-left text-[11px] font-bold uppercase tracking-wide">Matière</th>
                                    <th className="px-3 py-1.5 text-right text-[11px] font-bold uppercase tracking-wide">Disponible</th>
                                    <th className="px-3 py-1.5 text-right text-[11px] font-bold uppercase tracking-wide">Seuil min</th>
                                    <th className="px-3 py-1.5 text-right text-[11px] font-bold uppercase tracking-wide">Déficit</th>
                                    <th className="px-3 py-1.5 text-right text-[11px] font-bold uppercase tracking-wide hidden md:table-cell">Coût/kg moy.</th>
                                    <th className="px-3 py-1.5 text-right text-[11px] font-bold uppercase tracking-wide">Coût estimé</th>
                                </tr>
                            </thead>
                            <tbody>
                                {shortfalls.length === 0 && (
                                    <tr><td colSpan={7} className="px-4 py-16 text-center text-gray-400 text-sm">Aucun déficit — stock matière au-dessus des seuils.</td></tr>
                                )}
                                {shortfalls.map((s, i) => (
                                    <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                                        <td className="px-3 py-1.5">
                                            <input type="checkbox" checked={selected.has(s.productId)} onChange={() => toggle(s.productId)}
                                                   className="rounded border-[#c3d3c9] text-emerald-600 focus:ring-emerald-400" />
                                        </td>
                                        <td className="px-3 py-1.5 font-medium text-gray-900">{s.product}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-700">{fmt(s.available)} kg</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-500">{fmt(s.min)} kg</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums font-semibold text-red-600">{fmt(s.deficit)} kg</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-600 hidden md:table-cell">{s.avgCostPerKg.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{fmtF(s.estimated)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
                        {stats.count} déficit(s) — {fmt(stats.deficit)} kg — {fmtF(stats.estimated)} estimés — Le seuil minimum provient du champ « stock min » de chaque produit-matière (en kg).
                    </div>
                </form>

                <div className="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px] mt-3">
                    <span>Fonction : <span className="text-white font-semibold">Réappro (MRP)</span></span>
                    <span className="ml-auto">Utilisateur : <span className="text-white font-semibold">{auth?.user?.name}</span></span>
                </div>
            </div>
        </AppLayout>
    );
}
