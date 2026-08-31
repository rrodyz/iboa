import { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import AppLayout from '../../../Layouts/AppLayout';
import { fmt } from '../../../Utils/format';

// [REACT-01E] Portage 1:1 de resources/views/production/planning/index.blade.php.
// Charge/capacité/occupation/statut/conflits viennent tels quels de
// PlanningService et SchedulingConflictService (via ProductionPlanningController)
// — aucun calcul de calendrier, capacité, downtime ou chevauchement ici.
// SCHEDULING LEVEL: manuel/assisté + détection de conflits — pas un solveur,
// jamais présenté comme une optimisation automatique.

const STATUS_BAR = { surcharge: 'bg-red-500', charge: 'bg-amber-500', libre: 'bg-gray-300' };
const OF_BADGE = {
    brouillon: 'bg-gray-100 text-gray-600', lance: 'bg-blue-100 text-blue-700',
    en_cours: 'bg-emerald-100 text-emerald-700', suspendu: 'bg-orange-100 text-orange-700',
};

function OccupationBar({ status, occupation }) {
    const bar = STATUS_BAR[status] ?? 'bg-emerald-500';
    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                <div className={`h-full ${bar}`} style={{ width: `${Math.min(100, occupation)}%` }} />
            </div>
            <span className={`text-[11.5px] tabular-nums w-12 text-right ${status === 'surcharge' ? 'text-red-600 font-semibold' : 'text-gray-600'}`}>{fmt(occupation)} %</span>
        </div>
    );
}

const th = 'px-3 py-1.5 text-[11px] font-bold text-white uppercase tracking-wide';

function ReplanRow({ of, lignes }) {
    const [lineId, setLineId] = useState(of.productionLineId ?? '');
    const [dateFab, setDateFab] = useState(of.dateFabricationPrevue ?? '');
    const [dateFin, setDateFin] = useState(of.dateFinPrevue ?? '');
    const [submitting, setSubmitting] = useState(false);

    const submit = () => {
        setSubmitting(true);
        router.post(of.replanUrl, {
            production_line_id: lineId || null,
            date_fabrication_prevue: dateFab || null,
            date_fin_prevue: dateFin || null,
        }, { onFinish: () => setSubmitting(false), preserveScroll: true });
    };

    const inpMini = 'h-7 border border-[#c3d3c9] rounded-[3px] px-1.5 py-0 text-[12px] bg-white focus:outline-none focus:border-emerald-600';

    return (
        <tr className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50">
            <td className="px-3 py-1.5 whitespace-nowrap">
                <a href={of.showUrl} className="font-mono text-emerald-800 hover:underline">{of.number}</a>
                {of.enRetard && <span className="ml-1 inline-block w-2 h-2 rounded-full bg-red-500" title="En retard"></span>}
            </td>
            <td className="px-3 py-1.5 text-gray-600 max-w-[180px] truncate" title={of.productName}>{of.productName ?? '—'}</td>
            <td className="px-3 py-1.5 text-gray-500 hidden lg:table-cell max-w-[130px] truncate">{of.clientName ?? '—'}</td>
            <td className="px-3 py-1.5 text-center">
                <span className={`inline-flex px-2 py-0.5 rounded-full text-[10.5px] font-medium ${OF_BADGE[of.status] ?? 'bg-amber-100 text-amber-700'}`}>{of.statusLabel}</span>
            </td>
            <td className="px-3 py-1.5">
                <select value={lineId} onChange={(e) => setLineId(e.target.value)} className={`${inpMini} appearance-none pr-6 min-w-[120px]`}>
                    <option value="">—</option>
                    {lignes.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                </select>
            </td>
            <td className="px-3 py-1.5"><input type="date" value={dateFab} onChange={(e) => setDateFab(e.target.value)} className={inpMini} /></td>
            <td className="px-3 py-1.5"><input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} className={`${inpMini} ${of.enRetard ? 'border-red-400' : ''}`} /></td>
            <td className="px-3 py-1.5 text-right">
                <button onClick={submit} disabled={submitting} className="text-[12px] font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 px-2.5 py-1 rounded-[3px]">Appliquer</button>
            </td>
        </tr>
    );
}

function CapacityTable({ rows, columns, empty }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-[12.5px] border-collapse">
                <thead className="bg-[#3b4248] text-white"><tr>{columns.map((c, i) => <th key={i} className={`${th} ${c.align === 'right' ? 'text-right' : c.align === 'left-wide' ? 'text-left w-1/3' : 'text-left'}`}>{c.label}</th>)}</tr></thead>
                <tbody>
                    {rows.length === 0 && <tr><td colSpan={columns.length} className="px-4 py-16 text-center text-gray-400 text-sm">{empty}</td></tr>}
                    {rows.map((r, i) => (
                        <tr key={i} className="border-b border-gray-100 odd:bg-white even:bg-gray-50/40 hover:bg-emerald-50/50 transition-colors">
                            {columns.map((c, j) => <td key={j} className={`px-3 py-1.5 ${c.align === 'right' ? 'text-right tabular-nums' : ''}`}>{c.render(r)}</td>)}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function PlanningIndex({ horizon, tauxGlobal, plan, planMachine, planTeam, conflicts, canReplan, lignes, ofActifs, links }) {
    const { auth } = usePage().props;
    const [tab, setTab] = useState('centre');

    const setHorizon = (h) => {
        router.get(links.planningUrl, { horizon: h }, { preserveState: true, preserveScroll: true });
    };

    const kpis = [
        { label: 'Charge planifiée', value: `${fmt(plan.totalPlannedH)} h`, bg: 'bg-[#eef5f0]', color: 'text-gray-900' },
        { label: `Capacité (${horizon} j)`, value: `${fmt(plan.totalCapacityH)} h`, bg: 'bg-blue-50', color: 'text-gray-900' },
        { label: 'Centres en surcharge', value: fmt(plan.overloaded), bg: 'bg-red-50', color: plan.overloaded > 0 ? 'text-red-600' : 'text-gray-900' },
        { label: 'Taux global', value: `${fmt(tauxGlobal)} %`, bg: 'bg-emerald-50', color: 'text-emerald-700' },
    ];

    return (
        <AppLayout>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-bold text-gray-900 leading-tight">Plan de charge</h1>
                        <p className="text-[12px] text-gray-500">Capacité vs charge planifiée par centre de travail (OF actifs)</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <label className="text-[11px] font-bold text-gray-700">Horizon</label>
                        <select value={horizon} onChange={(e) => setHorizon(e.target.value)}
                                className="appearance-none h-8 pl-2 pr-7 border border-[#c3d3c9] rounded-[3px] text-[13px] bg-white focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-400">
                            {[1, 3, 7, 14, 30].map((h) => <option key={h} value={h}>{h} jour(s)</option>)}
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-2 xl:grid-cols-4 gap-3">
                    {kpis.map((k, i) => (
                        <div key={i} className="bg-white rounded-[4px] border border-gray-300 px-3 py-1.5 flex items-center gap-3">
                            <div className={`w-9 h-9 rounded-[4px] ${k.bg} flex items-center justify-center shrink-0`}>
                                <span className={`w-2.5 h-2.5 rounded-full ${plan.overloaded > 0 && i === 2 ? 'bg-red-500' : 'bg-gray-400'}`}></span>
                            </div>
                            <div className="min-w-0">
                                <p className="text-[11px] text-gray-500 truncate">{k.label}</p>
                                <p className={`text-[16px] font-bold ${k.color} tabular-nums leading-tight`}>{k.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {conflicts.length > 0 && (
                    <div className="bg-red-50 border border-red-300 rounded-[4px] overflow-hidden">
                        <div className="px-4 py-2 bg-red-100 border-b border-red-300 flex items-center gap-2">
                            <svg style={{ width: 16, height: 16 }} className="text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <h2 className="text-[13px] font-bold text-red-800">{conflicts.length} conflit(s) d'ordonnancement détecté(s) — ligne partagée entre deux OF</h2>
                        </div>
                        <table className="min-w-full divide-y divide-red-200 text-sm">
                            <thead><tr>
                                <th className="px-3 py-1.5 text-left text-[11px] font-bold text-red-700 uppercase">Ligne</th>
                                <th className="px-3 py-1.5 text-left text-[11px] font-bold text-red-700 uppercase">OF concernés</th>
                                <th className="px-3 py-1.5 text-left text-[11px] font-bold text-red-700 uppercase">Chevauchement</th>
                                <th className="px-3 py-1.5 text-right text-[11px] font-bold text-red-700 uppercase">Surcharge</th>
                            </tr></thead>
                            <tbody className="divide-y divide-red-100 bg-white">
                                {conflicts.map((c, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-1.5 font-medium text-gray-900">{c.resourceLabel}</td>
                                        <td className="px-3 py-1.5 text-gray-700">{c.ofANumber} <span className="text-gray-400">×</span> {c.ofBNumber}</td>
                                        <td className="px-3 py-1.5 text-gray-700 tabular-nums">{c.overlapStart} → {c.overlapEnd}</td>
                                        <td className="px-3 py-1.5 text-right font-bold text-red-700 tabular-nums">{c.overlapMinutes} min</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                    <div className="px-3 pt-2 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white flex items-center gap-1">
                        {[['centre', 'Par centre de travail'], ['machine', 'Par machine'], ['equipe', 'Par équipe']].map(([k, lbl]) => (
                            <button key={k} type="button" onClick={() => setTab(k)}
                                    className={`px-3 py-1.5 text-[12.5px] font-semibold rounded-t-[4px] -mb-px border-b-2 transition-colors ${tab === k ? 'border-emerald-600 text-emerald-800 bg-white' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                {lbl}
                            </button>
                        ))}
                    </div>

                    {tab === 'centre' && (
                        <CapacityTable empty="Aucun centre de travail. Créez-en + affectez des gammes." rows={plan.rows} columns={[
                            { label: 'Centre', render: (r) => <><span className="font-medium text-gray-900">{r.name}</span><span className="text-emerald-800 font-mono text-[11px] ml-1">{r.code}</span></> },
                            { label: 'Opérations', align: 'right', render: (r) => <span className="text-gray-600">{r.ops}</span> },
                            { label: 'Charge', align: 'right', render: (r) => <span className="font-semibold text-gray-900">{fmt(r.planned_h)} h</span> },
                            { label: 'Capacité', align: 'right', render: (r) => <span className="text-gray-600">{fmt(r.capacity_h)} h</span> },
                            { label: 'Occupation', align: 'left-wide', render: (r) => <OccupationBar status={r.status} occupation={r.occupation} /> },
                        ]} />
                    )}
                    {tab === 'machine' && (
                        <CapacityTable empty="Aucune machine rattachée à un centre de travail." rows={planMachine.rows} columns={[
                            { label: 'Machine', render: (r) => <><span className="font-medium text-gray-900">{r.name}</span><span className="text-emerald-800 font-mono text-[11px] ml-1">{r.code}</span></> },
                            { label: 'Charge', align: 'right', render: (r) => <span className="font-semibold text-gray-900">{fmt(r.planned_h)} h</span> },
                            { label: 'Capacité brute', align: 'right', render: (r) => <span className="text-gray-600">{fmt(r.capacity_h)} h</span> },
                            { label: 'Arrêts', align: 'right', render: (r) => <span className={r.downtime_h > 0 ? 'text-red-600' : 'text-gray-400'}>{fmt(r.downtime_h)} h</span> },
                            { label: 'Capacité nette', align: 'right', render: (r) => <span className="text-gray-700">{fmt(r.net_capacity_h)} h</span> },
                            { label: 'Occupation', align: 'left-wide', render: (r) => <OccupationBar status={r.status} occupation={r.occupation} /> },
                        ]} />
                    )}
                    {tab === 'equipe' && (
                        <CapacityTable empty="Aucune équipe définie sur les centres de travail." rows={planTeam.rows} columns={[
                            { label: 'Équipe', render: (r) => <span className="font-medium text-gray-900">{r.name}</span> },
                            { label: 'Centres', align: 'right', render: (r) => <span className="text-gray-600">{r.centers}</span> },
                            { label: 'Charge', align: 'right', render: (r) => <span className="font-semibold text-gray-900">{fmt(r.planned_h)} h</span> },
                            { label: 'Capacité', align: 'right', render: (r) => <span className="text-gray-600">{fmt(r.capacity_h)} h</span> },
                            { label: 'Occupation', align: 'left-wide', render: (r) => <OccupationBar status={r.status} occupation={r.occupation} /> },
                        ]} />
                    )}

                    <div className="px-3 py-2 border-t border-gray-200 bg-[#f7faf8] text-[11.5px] text-gray-500">
                        Charge = temps prévu des opérations non terminées sur OF lancés/en cours. Capacité = capacité journalière × rendement × horizon.
                        La capacité nette machine déduit les <a href={links.downtimesUrl} className="text-emerald-700 hover:underline">temps d'arrêt</a> déclarés.
                    </div>
                </div>

                {canReplan && (
                    <div className="bg-white rounded-[4px] border border-gray-300 overflow-hidden">
                        <div className="px-3 py-1.5 border-b border-gray-200 bg-gradient-to-b from-gray-50 to-white">
                            <h2 className="text-[13px] font-bold text-gray-900">Replanification des OF actifs</h2>
                            <p className="text-[11px] text-gray-500">Déplacer les dates prévues ou réaffecter la ligne — OF clôturés/annulés exclus.</p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-[12.5px] border-collapse">
                                <thead className="bg-[#3b4248] text-white"><tr>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-left whitespace-nowrap">N° OF</th>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-left">Article</th>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-left hidden lg:table-cell">Client</th>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-center">Statut</th>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-left">Ligne</th>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-left whitespace-nowrap">Fabrication</th>
                                    <th className="px-3 py-1.5 text-[11px] font-semibold uppercase text-left whitespace-nowrap">Fin prévue</th>
                                    <th className="px-3 py-1.5"></th>
                                </tr></thead>
                                <tbody>
                                    {ofActifs.length === 0 && <tr><td colSpan={8} className="px-4 py-10 text-center text-gray-400 text-sm">Aucun OF actif à replanifier.</td></tr>}
                                    {ofActifs.map((of) => <ReplanRow key={of.id} of={of} lignes={lignes} />)}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
                    <span>Fonction : <span className="text-white font-semibold">Plan de charge ({horizon} j)</span></span>
                    <span className="ml-auto">Utilisateur : <span className="text-white font-semibold">{auth?.user?.name}</span></span>
                </div>
            </div>
        </AppLayout>
    );
}
