import { useState, useEffect } from 'react';
import { usePage, Link } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import ProductionKpiCard from '../../../Components/Production/ProductionKpiCard';
import { fmt } from '../../../Utils/format';

// [REACT-01C] Portage 1:1 de resources/views/production/dashboard.blade.php —
// mêmes 351 lignes de calcul contrôleur, aucune formule reprise ici (TRS,
// rendement, coûts standard/réel restent des méthodes privées du contrôleur).

const OF_BADGE = {
    en_cours: ['En cours', 'bg-emerald-100 text-emerald-700'],
    lance: ['Lancé', 'bg-blue-100 text-blue-700'],
    planifie: ['Planifié', 'bg-indigo-100 text-indigo-700'],
    valide: ['Validé', 'bg-teal-100 text-teal-700'],
    brouillon: ['Brouillon', 'bg-gray-100 text-gray-600'],
    termine: ['Clôturé', 'bg-gray-200 text-gray-700'],
    annule: ['Annulé', 'bg-red-100 text-red-700'],
};
const MACHINE_BADGE = {
    en_service: ['En service', 'bg-emerald-100 text-emerald-700'], active: ['En service', 'bg-emerald-100 text-emerald-700'], disponible: ['En service', 'bg-emerald-100 text-emerald-700'],
    en_panne: ['En panne', 'bg-red-100 text-red-700'],
    maintenance: ['Maintenance', 'bg-amber-100 text-amber-700'], en_maintenance: ['Maintenance', 'bg-amber-100 text-amber-700'],
};
const QUALITE_BADGE = {
    conforme: ['Conforme', 'bg-emerald-100 text-emerald-700'],
    non_conforme: ['Non conforme', 'bg-red-100 text-red-700'],
    partiel: ['Partiel', 'bg-amber-100 text-amber-700'],
};
const CHAIN_COLORS = {
    blue: 'border-blue-300 bg-blue-50 text-blue-800', amber: 'border-amber-300 bg-amber-50 text-amber-800',
    emerald: 'border-emerald-300 bg-emerald-50 text-emerald-800', purple: 'border-purple-300 bg-purple-50 text-purple-800',
    teal: 'border-teal-300 bg-teal-50 text-teal-800', sky: 'border-sky-300 bg-sky-50 text-sky-800',
    indigo: 'border-indigo-300 bg-indigo-50 text-indigo-800',
};

const th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';
const panelH = 'flex items-center justify-between px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white';
const panelT = 'text-[13px] font-bold text-gray-900';
const foot = 'px-3 py-1.5 border-t border-gray-200 bg-[#f7faf8]';
const lnk = 'text-[12px] text-emerald-700 hover:text-emerald-900 font-semibold';

function Panel({ title, icon, children, footHref, footLabel }) {
    return (
        <div className="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
            <div className={panelH}>
                <h2 className={panelT}>{title}</h2>
                {icon}
            </div>
            {children}
            {footHref && <div className={foot}><a href={footHref} className={lnk}>{footLabel}</a></div>}
        </div>
    );
}

function useClock() {
    const [time, setTime] = useState('');
    useEffect(() => {
        const id = setInterval(() => setTime(new Date().toLocaleTimeString('fr-FR')), 1000);
        return () => clearInterval(id);
    }, []);
    return time;
}

export default function ProductionDashboard({ kpis, trs, meta, pendingCount, prod7Days, chaine, ofEnCours, suiviJour, alertes, consoMatieres, perfMachines, controlesQualite, links }) {
    const { auth } = usePage().props;
    const clock = useClock();

    return (
        <AppLayout>
            <div className="space-y-4">
                {pendingCount > 0 && (
                    <div className="flex items-center justify-between bg-[#fff8ec] border border-amber-200 rounded-[4px] px-3 py-2.5">
                        <div>
                            <p className="text-[13px] font-bold text-amber-700">{pendingCount} document(s) en attente de votre validation</p>
                            <p className="text-[11.5px] text-gray-500">Ordres de fabrication, rebuts, approvisionnement matières, déclarations de production…</p>
                        </div>
                        <a href={links.validationsUrl} className="text-[13px] font-semibold text-emerald-700 hover:text-emerald-900">Traiter →</a>
                    </div>
                )}

                <div className="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <div className="flex items-center gap-3">
                        <div className="w-11 h-11 rounded-[4px] bg-emerald-800 flex items-center justify-center">
                            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </div>
                        <div>
                            <p className="text-[14px] font-bold text-gray-900">{auth?.user?.name}</p>
                            <p className="text-[12px] text-gray-500">Responsable Production</p>
                        </div>
                    </div>
                    <div className="border-l border-gray-200 pl-6">
                        <p className="text-[11px] text-gray-500">Production mensuelle</p>
                        <p className="text-[22px] font-bold text-gray-900 leading-tight tabular-nums">{fmt(kpis.meters)} m</p>
                        <p className="text-[11px] text-gray-400">du {meta.from} au {meta.to}</p>
                    </div>
                    <div className="border-l border-gray-200 pl-6">
                        <span className="inline-flex items-center gap-2 border border-gray-200 rounded-[4px] px-3 py-1.5 text-[14px] font-semibold text-gray-800 tabular-nums">
                            <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                            {clock || '—'}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 ml-auto flex-wrap">
                        <a href={links.ofCreateUrl} className="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px]">Nouvel OF</a>
                        <a href={links.planningUrl} className="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px]">Suivi fabrication</a>
                        <a href={links.ofIndexUrl} className="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px]">Déclaration production</a>
                        <a href={links.reportsUrl} className="border border-gray-300 hover:border-emerald-500 hover:text-emerald-700 text-gray-700 text-[12.5px] font-semibold px-3 py-1.5 rounded-[4px]">Rapports</a>
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
                    <ProductionKpiCard label="OF en cours" value={fmt(kpis.of_en_cours)} sub={`${kpis.of_en_retard} en retard`} sparkSerie={prod7Days} />
                    <ProductionKpiCard label="Production du jour" value={`${fmt(kpis.meters_today)} m`} sub={`mois : ${fmt(kpis.meters)} m`} sparkSerie={prod7Days} />
                    <ProductionKpiCard label="Rendement moyen" value={kpis.yield !== null ? `${String(kpis.yield).replace('.', ',')} %` : '—'} sub="matière consommée" sparkSerie={prod7Days} />
                    <ProductionKpiCard label="Rebuts / Pertes" value={`${fmt(kpis.waste_weight)} kg`} sub={kpis.rebut_pct !== null ? `${String(kpis.rebut_pct).replace('.', ',')} % de la prod.` : '—'} alert sparkSerie={prod7Days} sparkColor="#f87171" />
                    <ProductionKpiCard label="Taux de conformité" value={kpis.conformite !== null ? `${String(kpis.conformite).replace('.', ',')} %` : '—'} sub="contrôles qualité" sparkSerie={prod7Days} />
                    <ProductionKpiCard label="Arrêts machine" value={`${String(trs.downtime_h).replace('.', ',')} h`} sub={`TRS : ${String(trs.trs).replace('.', ',')} %`} alert sparkSerie={prod7Days} sparkColor="#f87171" />
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
                    <ProductionKpiCard label="OF à lancer" value={fmt(kpis.of_a_lancer)} sub={`${kpis.of_termine} terminés (période)`} />
                    <ProductionKpiCard label="Tonnage produit" value={`${String(kpis.tonnage).replace('.', ',')} t`} sub="sur la période" />
                    <ProductionKpiCard label="Bobines" value={`${fmt(kpis.coils_dispo)} dispo`} sub={`${kpis.coils_reservees} en production / réservées`} />
                    <ProductionKpiCard label="Machines" value={`${fmt(kpis.machines_dispo)} dispo`} sub={`${kpis.machines_panne} indisponible(s)`} alert={kpis.machines_panne > 0} />
                    <ProductionKpiCard label="Qualité" value={`${kpis.nc_ouvertes} NC ouvertes`} sub={`${kpis.cq_attente} contrôles en attente`} alert={kpis.nc_ouvertes > 0} />
                    <ProductionKpiCard label="Marge estimée" value={`${fmt(kpis.marge_estimee)} F`} sub={`MO ${fmt(kpis.cout_mo)} F · machine ${fmt(kpis.cout_machine)} F`} alert={kpis.marge_estimee < 0} />
                </div>

                <div className="bg-white rounded-[4px] border border-gray-300 p-3">
                    <h2 className="text-[12px] font-bold text-gray-700 uppercase tracking-wide mb-2">Chaîne de production</h2>
                    <div className="flex items-stretch gap-0 overflow-x-auto pb-1">
                        {chaine.map((etape, i) => (
                            <div key={i} className="flex items-stretch">
                                <a href={etape.url} className={`shrink-0 border ${CHAIN_COLORS[etape.color] ?? 'border-gray-300 bg-gray-50 text-gray-700'} rounded-[4px] px-3 py-1.5 text-center hover:shadow-sm transition-shadow min-w-[92px]`}>
                                    <p className="text-[15px] font-bold tabular-nums leading-tight">{fmt(etape.count)}</p>
                                    <p className="text-[10.5px] font-semibold whitespace-nowrap">{etape.label}</p>
                                </a>
                                {i < chaine.length - 1 && <span className="shrink-0 self-center text-gray-300 px-1 text-[13px]">→</span>}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <Panel title="Ordres de fabrication en cours" footHref={links.ofIndexUrl} footLabel="Voir tous les OF en cours">
                        <div className="overflow-x-auto">
                            <table className="w-full text-[12px]">
                                <thead className="bg-[#3b4248] text-white"><tr>
                                    <th className={`${th} text-left`}>Référence</th><th className={`${th} text-left`}>Article</th>
                                    <th className={`${th} text-right`}>Prévue</th><th className={`${th} text-right`}>Réalisée</th><th className={`${th} text-center`}>Statut</th>
                                </tr></thead>
                                <tbody>
                                    {ofEnCours.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Aucun OF en cours.</td></tr>}
                                    {ofEnCours.map((of, i) => {
                                        const [sl, sc] = OF_BADGE[of.status] ?? [of.status, 'bg-gray-100 text-gray-600'];
                                        return (
                                            <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                                <td className="px-3 py-1.5 whitespace-nowrap"><a href={of.showUrl} className="font-mono text-emerald-800 hover:underline">{of.number}</a></td>
                                                <td className="px-3 py-1.5 text-gray-900 max-w-[180px] truncate">{of.productName ?? '—'}</td>
                                                <td className="px-3 py-1.5 text-right tabular-nums text-gray-600">{fmt(of.quantityRequested)}</td>
                                                <td className="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{fmt(of.quantityProduced)}</td>
                                                <td className="px-3 py-1.5 text-center"><span className={`inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium ${sc}`}>{sl}</span></td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel title="Suivi production du jour" footHref={links.planningUrl} footLabel="Voir tout le suivi du jour">
                        <div className="overflow-x-auto">
                            <table className="w-full text-[12px]">
                                <thead className="bg-[#3b4248] text-white"><tr>
                                    <th className={`${th} text-left`}>Date</th><th className={`${th} text-left`}>OF</th><th className={`${th} text-left`}>Article</th>
                                    <th className={`${th} text-right`}>Métrage</th><th className={`${th} text-left`}>Opérateur</th>
                                </tr></thead>
                                <tbody>
                                    {suiviJour.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Aucune production déclarée.</td></tr>}
                                    {suiviJour.map((out, i) => (
                                        <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                            <td className="px-3 py-1.5 text-gray-600 whitespace-nowrap">{out.producedAt ?? '—'}</td>
                                            <td className="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{out.ofNumber ?? '—'}</td>
                                            <td className="px-3 py-1.5 text-gray-900 max-w-[160px] truncate">{out.productName ?? '—'}</td>
                                            <td className="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{fmt(out.totalMeters)} m</td>
                                            <td className="px-3 py-1.5 text-gray-600 whitespace-nowrap">{out.operatorName ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Panel>

                    <Panel title="Alertes production" footHref={links.maintenanceUrl} footLabel="Voir toutes les alertes"
                           icon={<svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>}>
                        <div className="divide-y divide-gray-100">
                            {alertes.length === 0 && <div className="px-4 py-8 text-center text-gray-400 text-[12px]">Aucune alerte active.</div>}
                            {alertes.map((al, i) => (
                                <div key={i} className="px-3 py-2.5 flex items-start gap-2.5">
                                    <svg className={`w-4 h-4 mt-0.5 shrink-0 ${al.niveau === 'rouge' ? 'text-red-500' : 'text-amber-500'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <div className="min-w-0 flex-1">
                                        <p className={`text-[12.5px] font-semibold ${al.niveau === 'rouge' ? 'text-red-600' : 'text-amber-600'}`}>{al.titre}</p>
                                        <p className="text-[11.5px] text-gray-500 truncate">{al.detail}</p>
                                    </div>
                                    {al.heure && <span className="text-[11px] text-gray-400 tabular-nums shrink-0">{al.heure}</span>}
                                </div>
                            ))}
                        </div>
                    </Panel>
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <Panel title="Consommation matières" footHref={links.productsUrl} footLabel="Voir tous les articles matière">
                        <table className="w-full text-[12px]">
                            <thead className="bg-[#3b4248] text-white"><tr>
                                <th className={`${th} text-left`}>Article</th><th className={`${th} text-right`}>Stock dispo.</th>
                                <th className={`${th} text-right`}>Conso. jour</th><th className={`${th} text-right`}>Conso. mois</th><th className={`${th} text-left`}>Unité</th>
                            </tr></thead>
                            <tbody>
                                {consoMatieres.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Aucune matière première.</td></tr>}
                                {consoMatieres.map((cm, i) => (
                                    <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                        <td className="px-3 py-1.5 text-gray-900 max-w-[170px] truncate">{cm.name}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">{cm.stock.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-600">{cm.jour.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums text-gray-600">{cm.mois.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</td>
                                        <td className="px-3 py-1.5 text-gray-500">{cm.unite}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Panel>

                    <Panel title="Performances machines" footHref={links.machinesUrl} footLabel="Voir le détail des performances">
                        <table className="w-full text-[12px]">
                            <thead className="bg-[#3b4248] text-white"><tr>
                                <th className={`${th} text-left`}>Machine</th><th className={`${th} text-right`}>Disponibilité</th>
                                <th className={`${th} text-right`}>Arrêts</th><th className={`${th} text-center`}>Statut</th>
                            </tr></thead>
                            <tbody>
                                {perfMachines.length === 0 && <tr><td colSpan={4} className="px-4 py-8 text-center text-gray-400">Aucune machine active.</td></tr>}
                                {perfMachines.map((pm, i) => {
                                    const [ml, mc] = MACHINE_BADGE[pm.statut] ?? [String(pm.statut).replace(/_/g, ' '), 'bg-gray-100 text-gray-600'];
                                    return (
                                        <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                            <td className="px-3 py-1.5">
                                                <span className="font-mono text-emerald-800">{pm.code}</span>
                                                <p className="text-[10.5px] text-gray-400 truncate max-w-[140px]">{pm.name}</p>
                                            </td>
                                            <td className={`px-3 py-1.5 text-right tabular-nums font-semibold ${pm.dispo < 80 ? 'text-red-600' : 'text-gray-900'}`}>{String(pm.dispo).replace('.', ',')} %</td>
                                            <td className={`px-3 py-1.5 text-right tabular-nums ${pm.arrets > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'}`}>{String(pm.arrets).replace('.', ',')} h</td>
                                            <td className="px-3 py-1.5 text-center"><span className={`inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium ${mc}`}>{ml}</span></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </Panel>

                    <Panel title="Contrôle qualité" footHref={links.qualiteUrl} footLabel="Voir tous les contrôles">
                        <table className="w-full text-[12px]">
                            <thead className="bg-[#3b4248] text-white"><tr>
                                <th className={`${th} text-left`}>Date</th><th className={`${th} text-left`}>OF</th><th className={`${th} text-left`}>Contrôle</th>
                                <th className={`${th} text-center`}>Résultat</th><th className={`${th} text-left`}>Opérateur</th>
                            </tr></thead>
                            <tbody>
                                {controlesQualite.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">Aucun contrôle enregistré.</td></tr>}
                                {controlesQualite.map((ci, i) => {
                                    const [ql, qc] = QUALITE_BADGE[ci.status] ?? [ci.status, 'bg-gray-100 text-gray-600'];
                                    return (
                                        <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
                                            <td className="px-3 py-1.5 text-gray-600 whitespace-nowrap">{ci.inspectedAt ?? '—'}</td>
                                            <td className="px-3 py-1.5 font-mono text-emerald-800 whitespace-nowrap">{ci.ofNumber ?? '—'}</td>
                                            <td className="px-3 py-1.5 text-gray-600">{ci.typeLabel}</td>
                                            <td className="px-3 py-1.5 text-center"><span className={`inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium ${qc}`}>{ql}</span></td>
                                            <td className="px-3 py-1.5 text-gray-600 whitespace-nowrap">{ci.controllerName ?? '—'}</td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}
