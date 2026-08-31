// [A3-UI-V2 — REACT-01A/C/D] Layout minimal de validation. Pas la vraie
// sidebar ERP (1045 lignes Blade) — juste de quoi prouver utilisateur/nav/
// contenu/flash/permissions/CSS fonctionnent, plus une nav minimale (Phase 17
// REACT-01C, étendue Phase 23 REACT-01D) vers les pages déjà migrées + les
// modules Blade restants. La vraie sidebar complète arrive en lot ultérieur.
import { usePage, Link } from '@inertiajs/react';

export default function AppLayout({ children }) {
    const { auth, flash } = usePage().props;
    const nav = usePage().props.nav ?? {};

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-300 px-4 py-2.5 flex items-center justify-between gap-4 flex-wrap">
                <div className="flex items-center gap-4 flex-wrap">
                    <span className="text-[17px] font-bold text-gray-900">A3 ERP — UI V2</span>
                    <nav className="flex items-center gap-3 text-[12.5px] font-medium text-gray-600">
                        {nav.dashboardUrl && <Link href={nav.dashboardUrl} className="hover:text-emerald-700">Dashboard</Link>}
                        {nav.productionDashboardUrl && <Link href={nav.productionDashboardUrl} className="hover:text-emerald-700">Production</Link>}
                        {nav.mtoUrl && <Link href={nav.mtoUrl} className="hover:text-emerald-700">MTO</Link>}
                        {nav.mtsUrl && <Link href={nav.mtsUrl} className="hover:text-emerald-700">MTS</Link>}
                        {nav.mrpUrl && <Link href={nav.mrpUrl} className="hover:text-emerald-700">MRP</Link>}
                        {nav.ofUrl && <a href={nav.ofUrl} className="hover:text-emerald-700">OF</a>}
                        {nav.planningUrl && <a href={nav.planningUrl} className="hover:text-emerald-700">Planning</a>}
                    </nav>
                </div>
                <span className="text-sm text-gray-600">{auth?.user?.name ?? '—'}</span>
            </header>

            {flash?.success && (
                <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm px-4 py-2 m-3 rounded-[4px]">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-2 m-3 rounded-[4px]">
                    {flash.error}
                </div>
            )}
            {flash?.warning && (
                <div className="bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2 m-3 rounded-[4px]">
                    {flash.warning}
                </div>
            )}
            {flash?.info && (
                <div className="bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-2 m-3 rounded-[4px]">
                    {flash.info}
                </div>
            )}

            <main className="p-4">{children}</main>
        </div>
    );
}
