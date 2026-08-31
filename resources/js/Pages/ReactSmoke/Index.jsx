// [A3-UI-V2 — REACT-01A] Page de validation de l'infra Inertia/React —
// aucune donnée métier. Prouve : auth partagée, permission réelle en shared
// prop, flash bridge, navigation Blade<->React.
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ examplePermission, hasExamplePermission, dashboardUrl, smokeUrl, flashActionUrl }) {
    return (
        <AppLayout>
            <Head title="A3 UI V2 — Smoke" />

            <div className="max-w-2xl mx-auto bg-white rounded-[4px] border border-gray-300 p-6 space-y-4">
                <h1 className="text-[22px] font-bold text-gray-900">A3 UI V2</h1>
                <p className="text-sm text-gray-500">
                    Page de validation REACT-01A — infrastructure Inertia + React isolée de Blade/Turbo.
                </p>

                <div className="text-sm space-y-1">
                    <p>
                        Permission testée (<code>{examplePermission}</code>) :{' '}
                        <strong className={hasExamplePermission ? 'text-emerald-700' : 'text-gray-500'}>
                            {hasExamplePermission ? 'accordée' : 'absente'}
                        </strong>
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => router.post(flashActionUrl)}
                    className="inline-flex items-center px-3 py-1.5 bg-emerald-700 text-white rounded-[4px] text-sm font-medium hover:bg-emerald-800"
                >
                    Déclencher un flash success
                </button>

                <div className="flex items-center gap-3 pt-2 border-t border-gray-200">
                    <a href={dashboardUrl} className="text-sm text-emerald-700 hover:underline">
                        ← Dashboard Blade (full reload)
                    </a>
                    <Link href={smokeUrl} className="text-sm text-gray-500 hover:underline">
                        Recharger cette page (Inertia)
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
