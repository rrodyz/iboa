// ── Turbo Drive — doit être importé EN PREMIER ───────────────────────────────
import * as Turbo from '@hotwired/turbo';

// Désactiver le prefetch AVANT Turbo.start() pour bloquer l'observateur dès
// l'initialisation. La meta "turbo-prefetch" dans le layout couvre également
// chaque tentative individuelle (double protection).
Turbo.config.drive.prefetch = false;

Turbo.start();

// ── Turbo : gérer les erreurs réseau de navigation ───────────────────────────
// Filet de sécurité : si un fetch de navigation échoue malgré tout
// (réseau coupé, serveur arrêté), on absorbe l'erreur proprement.
document.addEventListener('turbo:fetch-request-error', (event) => {
    event.preventDefault();
    window.toast?.('Erreur réseau — veuillez réessayer.', 'error');
});

import Alpine from 'alpinejs';
window.Alpine = Alpine;

// ── ApexCharts — bundlé par Vite, exposé globalement ─────────────────────────
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;

// Helper pour les vues inline qui ont besoin d'ApexCharts.
// Problème : le bundle Vite charge en <script type="module"> (défer implicite).
// Les <script> inline classiques dans <body> s'exécutent AVANT le module
// → window.ApexCharts ET window.whenApexReady ne sont pas encore définis.
//
// Solution : queue d'attente sur window.__pendingApex.
// - Avant le chargement du bundle : les vues poussent leurs callbacks dans
//   un tableau (l'API push() de Array fait l'affaire).
// - Après chargement : on remplace l'array par un "dispatcher" qui exécute
//   immédiatement chaque push() (cas d'une navigation Turbo subséquente).
//
// Usage dans une vue Blade :
//   <script>
//     (window.__pendingApex = window.__pendingApex || []).push(function () {
//       new ApexCharts(document.querySelector('#chart'), {...}).render();
//     });
//   </script>
(function () {
    const pending = window.__pendingApex || [];
    // Remplace par un objet qui exécute les callbacks immédiatement
    window.__pendingApex = {
        push: function (cb) {
            try { cb(); } catch (e) { console.error('Apex callback error:', e); }
        }
    };
    // Exécute les callbacks accumulés avant le chargement du bundle
    pending.forEach(cb => { try { cb(); } catch (e) { console.error('Apex pending error:', e); } });
})();

// Alias pour compatibilité (whenApexReady était l'API précédente)
window.whenApexReady = (cb) => window.__pendingApex.push(cb);

// ═══════════════════════════════════════════════════════════════════════════════
// Registre de nettoyage Turbo
// Les scripts de pages enregistrent leurs cleanups ici.
// turbo:before-cache les exécute tous avant mise en cache, puis vide le tableau.
// ═══════════════════════════════════════════════════════════════════════════════
window.__turboCleanups = [];

// ── DataTables : config française ────────────────────────────────────────────
const dtFr = {
    processing:     'Traitement en cours…',
    search:         'Rechercher :',
    lengthMenu:     'Afficher _MENU_ éléments',
    info:           'Affichage de _START_ à _END_ sur _TOTAL_ éléments',
    infoEmpty:      'Affichage de 0 à 0 sur 0 élément',
    infoFiltered:   '(filtré depuis _MAX_ éléments au total)',
    infoPostFix:    '',
    loadingRecords: 'Chargement en cours…',
    zeroRecords:    'Aucun élément à afficher',
    emptyTable:     'Aucune donnée disponible',
    paginate: { first:'Premier', previous:'Précédent', next:'Suivant', last:'Dernier' },
    aria: {
        sortAscending:  ' : activer pour trier la colonne par ordre croissant',
        sortDescending: ' : activer pour trier la colonne par ordre décroissant',
    },
    buttons: {
        copy:'Copier', csv:'CSV', excel:'Excel', pdf:'PDF', print:'Imprimer',
        colvis:'Colonnes', copyTitle:'Copié dans le presse-papier',
        copySuccess: { 1:'1 ligne copiée', _:'%d lignes copiées' },
    },
};

// ── DataTables : initialisation (appelée sur turbo:load) ─────────────────────
window.initDataTables = function () {
    if (typeof $ === 'undefined' || !$.fn.DataTable) return;

    document.querySelectorAll('table[data-dt="simple"]').forEach(function (el) {
        if ($.fn.DataTable.isDataTable(el)) return;
        $(el).DataTable({
            language:   dtFr,
            paging:     false,
            searching:  false,
            info:       false,
            ordering:   true,
            dom:        'rt',
            columnDefs: [{ orderable: false, targets: '_all' }],
        });
        const dt = $(el).DataTable();
        dt.columns().every(function (i) {
            const th = el.querySelectorAll('thead th')[i];
            if (!th || !th.hasAttribute('data-sortable')) this.orderable(false);
        });
    });

    document.querySelectorAll('table[data-dt="full"]').forEach(function (el) {
        if ($.fn.DataTable.isDataTable(el)) return;
        $(el).DataTable({
            language:   dtFr,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Tous']],
            ordering:   true,
            dom:        'Bfrtip',
            buttons: [
                {
                    extend:    'excelHtml5',
                    text:      '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>Excel',
                    className: 'dt-btn-excel',
                    title:     () => document.title.split('—')[0].trim(),
                },
                {
                    extend:    'pdfHtml5',
                    text:      '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>PDF',
                    className: 'dt-btn-pdf',
                    title:     () => document.title.split('—')[0].trim(),
                    orientation: 'landscape',
                    pageSize:    'A4',
                },
                {
                    extend:    'print',
                    text:      '<svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Imprimer',
                    className: 'dt-btn-print',
                    title:     () => document.title.split('—')[0].trim(),
                },
            ],
        });
    });
};

// ── Turbo : nettoyage avant mise en cache ─────────────────────────────────────
// Enregistré UNE SEULE FOIS ici → pas de listener stacking
document.addEventListener('turbo:before-cache', function () {
    // Détruire les instances DataTables
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        document.querySelectorAll('table[data-dt]').forEach(function (el) {
            if ($.fn.DataTable.isDataTable(el)) $(el).DataTable().destroy();
        });
    }
    // Exécuter tous les cleanups de page (charts ApexCharts, etc.)
    window.__turboCleanups.forEach(fn => { try { fn(); } catch (e) {} });
    window.__turboCleanups = [];
});

// ── Turbo : init au chargement de chaque page ─────────────────────────────────
document.addEventListener('turbo:load', function () {
    window.initDataTables();

    // ── Scroll horizontal auto pour les tables simples (non-DataTables) ──────
    document.querySelectorAll('table:not(.dataTable)').forEach(function (tbl) {
        if (tbl.closest('[data-dt]') || tbl.parentElement.classList.contains('tbl-rx')) return;
        var wrap = document.createElement('div');
        wrap.className = 'tbl-rx';
        tbl.parentNode.insertBefore(wrap, tbl);
        wrap.appendChild(tbl);
    });
});

// ═══════════════════════════════════════════════════════════════════════════════
// Alpine.js
// ═══════════════════════════════════════════════════════════════════════════════

// ── Toast notification manager ────────────────────────────────────────────────
Alpine.data('toastManager', () => ({
    toasts: [],
    _toastHandler: null,   // référence gardée pour le cleanup dans destroy()

    init() {
        // Flash server-side — lus depuis les <meta name="flash-*"> posées dans le body.
        // Cette approche évite les scripts inline réactivés par Turbo (source d'erreurs).
        ['success', 'error', 'warning', 'info'].forEach(type => {
            const meta = document.querySelector(`meta[name="flash-${type}"]`);
            if (meta?.content) {
                this.add(meta.content, type);
                meta.remove(); // Supprime après lecture pour ne pas re-afficher
            }
            // Compatibilité ascendante : vieille API window.__flash_* (si jamais présente)
            const key = `__flash_${type}`;
            if (window[key]) { this.add(window[key], type); window[key] = null; }
        });

        // Écoute les toasts déclenchés via window.toast()
        this._toastHandler = (e) => {
            this.add(e.detail.msg ?? e.detail.message ?? e.detail, e.detail.type ?? 'info');
        };
        window.addEventListener('toast', this._toastHandler);
    },

    // Lifecycle Alpine.data : appelé quand l'élément est retiré du DOM
    destroy() {
        if (this._toastHandler) {
            window.removeEventListener('toast', this._toastHandler);
            this._toastHandler = null;
        }
    },

    add(message, type = 'success', duration = 4500) {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, message, type, visible: true, duration });
        setTimeout(() => this.dismiss(id), duration);
    },

    dismiss(id) {
        const t = this.toasts.find(t => t.id === id);
        if (t) t.visible = false;
        setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 350);
    },

    icon(type) {
        return {
            success: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
            error:   `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
            warning: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>`,
            info:    `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>`,
        }[type] ?? '';
    },

    colors(type) {
        return {
            success: { bg:'bg-white', border:'border-l-4 border-l-emerald-500', icon:'text-emerald-500', bar:'bg-emerald-400' },
            error:   { bg:'bg-white', border:'border-l-4 border-l-red-500',     icon:'text-red-500',     bar:'bg-red-400'     },
            warning: { bg:'bg-white', border:'border-l-4 border-l-amber-500',   icon:'text-amber-500',   bar:'bg-amber-400'   },
            info:    { bg:'bg-white', border:'border-l-4 border-l-blue-500',    icon:'text-blue-500',    bar:'bg-blue-400'    },
        }[type] ?? { bg:'bg-white', border:'border-l-4 border-l-gray-400', icon:'text-gray-500', bar:'bg-gray-400' };
    },
}));

// ── Number counter animation ──────────────────────────────────────────────────
Alpine.data('counter', (target, duration = 900) => ({
    displayed: 0,
    init() {
        if (!target) return;
        const start   = performance.now();
        const to      = parseFloat(target);
        const easeOut = t => 1 - Math.pow(1 - t, 3);
        const tick    = (now) => {
            const e = Math.min((now - start) / duration, 1);
            this.displayed = Math.round(to * easeOut(e));
            if (e < 1) requestAnimationFrame(tick); else this.displayed = to;
        };
        requestAnimationFrame(tick);
    },
    formatted() { return new Intl.NumberFormat('fr-FR').format(this.displayed); },
}));

// ── Global helper : toast depuis n'importe où ─────────────────────────────────
window.toast = (message, type = 'success') => {
    window.dispatchEvent(new CustomEvent('toast', { detail: { msg: message, type } }));
};

// ── Sidebar global state — store persistant entre les navigations Turbo ───────
Alpine.store('sidebar', {
    open:      false,
    collapsed: false,
});

// ── Dark mode — store Alpine global ──────────────────────────────────────────
// La classe 'dark' sur <html> est appliquée AVANT Alpine (inline script dans <head>)
// pour éviter le flash. Le store synchronise l'état réactif avec le DOM.
Alpine.store('darkMode', {
    dark: document.documentElement.classList.contains('dark'),
    toggle() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('erp_dark', this.dark);
    },
});

// ── Recently visited — tracked via localStorage ────────────────────────────────
// Enregistre la page courante à chaque navigation Turbo
document.addEventListener('turbo:load', function () {
    try {
        const title   = document.title.split('—').slice(-1)[0]?.trim() || document.title;
        const url     = window.location.pathname;
        const skip    = ['login', 'register', 'logout', 'password'];
        if (skip.some(s => url.includes(s))) return;

        // Détecter le module pour l'icône
        const icons = {
            '/factures': '🧾', '/devis': '📋', '/commandes': '🛒',
            '/clients': '🏢', '/fournisseurs': '🏭', '/produits': '📦',
            '/crm': '👤', '/stocks': '📊', '/tresorerie': '💰',
            '/comptabilite': '📒', '/rh': '👥', '/reports': '📈',
        };
        const icon = Object.entries(icons).find(([k]) => url.includes(k))?.[1] || '📄';

        let visits = JSON.parse(localStorage.getItem('erp_recent_visits') || '[]');
        visits = visits.filter(v => v.url !== url);
        visits.unshift({ url, label: title, icon });
        if (visits.length > 10) visits.pop();
        localStorage.setItem('erp_recent_visits', JSON.stringify(visits));
    } catch(e) {}
});

Alpine.start();
