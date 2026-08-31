// [A3-UI-V2 — REACT-01A] Layout minimal de validation. Pas la vraie sidebar
// ERP (1045 lignes Blade) — juste de quoi prouver utilisateur/nav/contenu/
// flash/permissions/CSS fonctionnent. La vraie sidebar arrive en lot ultérieur.
import { usePage } from '@inertiajs/react';

export default function AppLayout({ children }) {
    const { auth, flash } = usePage().props;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="bg-white border-b border-gray-300 px-4 py-2.5 flex items-center justify-between">
                <span className="text-[17px] font-bold text-gray-900">A3 ERP — UI V2</span>
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
