// ═══════════════════════════════════════════════════════════════════════════
// [A3-UI-V2] Entrypoint React/Inertia — ISOLÉ de resources/js/app.js.
//
// N'importe JAMAIS : Turbo, Alpine, ni aucun fichier de app.js. Chargé
// uniquement par le root Inertia (resources/views/app.blade.php), jamais par
// layouts/erp.blade.php (qui garde app.js). Voir vite.config.js pour
// l'entrypoint séparé et REACT-01A pour la preuve d'isolation.
// ═══════════════════════════════════════════════════════════════════════════
import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) {
            throw new Error(`Page Inertia introuvable : ./Pages/${name}.jsx`);
        }
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
