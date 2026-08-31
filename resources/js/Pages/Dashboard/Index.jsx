import AppLayout from '../../Layouts/AppLayout';
import KpiCard from '../../Components/Dashboard/KpiCard';
import CounterCard from '../../Components/Dashboard/CounterCard';
import { CaAnnuelChart, TresorerieChart, CaParFamilleChart } from '../../Components/Dashboard/DashboardCharts';
import RecentActivityTable from '../../Components/Dashboard/RecentActivityTable';
import AlertsTable from '../../Components/Dashboard/AlertsTable';
import { fmt } from '../../Utils/format';
import { usePage } from '@inertiajs/react';

// [REACT-01B] Portage 1:1 de resources/views/dashboard.blade.php — mêmes
// données, mêmes libellés, mêmes liens, même permission (hasReportsView =
// reports.view côté serveur, calculée par DashboardController, jamais
// recalculée ici). Aucun nouveau KPI, aucune formule modifiée.

export default function Dashboard({ hasReportsView, pendingCount, company, kpis, counters, charts, recentActivity, alertesVigilance, links }) {
    const { auth } = usePage().props;
    const fy = company?.fiscalYear;

    return (
        <AppLayout>
            <div className="space-y-3">
                {/* Titre + actions */}
                <div className="flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h1 className="text-[22px] font-bold text-gray-900 leading-tight">Tableau de bord</h1>
                        <p className="text-[12.5px] text-gray-500">Vue synthétique de l'activité de l'entreprise</p>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <button onClick={() => window.location.reload()}
                                className="h-8 inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                            Actualiser
                        </button>
                        {hasReportsView && (
                            <>
                                <a href={links.directionDashboardUrl}
                                   className="h-8 inline-flex items-center gap-1.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-[12px] font-medium px-3 rounded-[4px] transition-colors">
                                    Personnaliser
                                </a>
                                <a href={links.newInvoiceUrl}
                                   className="h-8 inline-flex items-center gap-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold px-3 rounded-[4px] transition-colors">
                                    Ajouter un widget +
                                </a>
                            </>
                        )}
                    </div>
                </div>

                {/* Bandeau documents en attente */}
                {pendingCount > 0 && (
                    <div className="flex items-center justify-between gap-3 bg-amber-50 border border-amber-200 rounded-[4px] px-4 py-2.5">
                        <div>
                            <p className="text-[13px] font-bold text-gray-900">Documents en attente de validation</p>
                            <p className="text-[12px] text-gray-600">Vous avez {pendingCount} document{pendingCount > 1 ? 's' : ''} en attente de validation.</p>
                        </div>
                        <a href={links.validationsUrl}
                           className="h-8 inline-flex items-center border border-amber-300 bg-white hover:bg-amber-100 text-[12px] font-medium text-gray-800 px-3 rounded-[4px] transition-colors whitespace-nowrap">
                            Voir les documents
                        </a>
                    </div>
                )}

                {hasReportsView ? (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                            <KpiCard label="Chiffre d'affaires (HT)" value={kpis.caHtMois} trend={kpis.trendCaHt}
                                     iconClass="bg-blue-100 text-blue-600"
                                     iconPath="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            <KpiCard label="Trésorerie disponible" value={kpis.soldeTresorerie} trend={null}
                                     iconClass="bg-emerald-100 text-emerald-600"
                                     iconPath="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <KpiCard label="Encaissements (mois)" value={kpis.encaissementsMois} trend={kpis.trendEncaissements}
                                     iconClass="bg-indigo-100 text-indigo-600"
                                     iconPath="M16 17l-4 4m0 0l-4-4m4 4V3" />
                            <KpiCard label="Décaissements (mois)" value={kpis.decaissementsMois} trend={kpis.trendDecaissements} inverse
                                     iconClass="bg-orange-100 text-orange-600"
                                     iconPath="M8 7l4-4m0 0l4 4m-4-4v18" />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2">
                            <CounterCard label="Commandes en attente" value={counters.nbCommandesEnCours}
                                         sub={fmt(counters.montantCommandesEnCours) + ' F'} subClass="text-gray-500"
                                         url={counters.commandesUrl} iconClass="bg-blue-50 text-blue-600"
                                         iconPath="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            <CounterCard label="OF en cours" value={counters.ofEnCours}
                                         sub={counters.ofEnRetard > 0 ? `Dont ${counters.ofEnRetard} en retard` : 'Aucun retard'}
                                         subClass={counters.ofEnRetard > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600'}
                                         url={counters.ofUrl} iconClass="bg-emerald-50 text-emerald-600"
                                         iconPath="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            <CounterCard label="Stock critique" value={counters.stockCritique}
                                         sub="Articles à réappro." subClass={counters.stockCritique > 0 ? 'text-red-600 font-semibold' : 'text-gray-500'}
                                         url={counters.stockUrl} iconClass="bg-amber-50 text-amber-600"
                                         iconPath="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            <CounterCard label="Alertes qualité" value={counters.alertesQualite}
                                         sub={counters.alertesQualite > 0 ? 'Non traitées' : 'Rien à signaler'}
                                         subClass={counters.alertesQualite > 0 ? 'text-red-600 font-semibold' : 'text-emerald-600'}
                                         url={counters.qualiteUrl} iconClass="bg-red-50 text-red-500"
                                         iconPath="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </div>

                        <div className="grid grid-cols-1 xl:grid-cols-3 gap-2">
                            <CaAnnuelChart caAnnuel={charts.caAnnuel} />
                            <TresorerieChart treso30={charts.treso30} treso30Labels={charts.treso30Labels} />
                            <CaParFamilleChart caParFamille={charts.caParFamille} />
                        </div>

                        <div className="grid grid-cols-1 xl:grid-cols-2 gap-2">
                            <RecentActivityTable items={recentActivity} auditUrl={links.auditUrl} />
                            <AlertsTable items={alertesVigilance} validationsUrl={links.validationsUrl} />
                        </div>
                    </>
                ) : (
                    <div className="bg-white border border-gray-300 rounded-[4px] p-8 text-center text-gray-500 text-[13px]">
                        Bienvenue. Vos validations en attente s'affichent ci-dessus — les indicateurs financiers sont réservés aux profils habilités.
                    </div>
                )}

                {/* Barre de contexte pied de page */}
                <div className="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
                    <span>Société : <span className="text-white font-semibold">{company?.name}</span></span>
                    <span className="border-l border-white/10 pl-6">Site : <span className="text-white font-semibold">01</span></span>
                    <span className="border-l border-white/10 pl-6">Exercice : <span className="text-white font-semibold">{fy?.year ?? new Date().getFullYear()}</span></span>
                    {fy && <span className="border-l border-white/10 pl-6">Période du <span className="text-white font-semibold">{fy.startsAt}</span> au <span className="text-white font-semibold">{fy.endsAt}</span></span>}
                    <span className="ml-auto">Utilisateur : <span className="text-white font-semibold">{auth?.user?.name}</span></span>
                    <span className="border-l border-white/10 pl-6 inline-flex items-center gap-1.5"><span className="w-2 h-2 rounded-full bg-emerald-400"></span> En ligne</span>
                </div>
            </div>
        </AppLayout>
    );
}
