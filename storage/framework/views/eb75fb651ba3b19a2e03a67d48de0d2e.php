
<style>
/* ── KPI card hover lift ───────────────────────────────────────────────────── */
.kpi-card { position: relative; transition: box-shadow .25s cubic-bezier(.22,1,.36,1), transform .25s cubic-bezier(.22,1,.36,1); }
.kpi-card:hover { transform: translateY(-3px); }

/* ── [PREMIUM] Accent dégradé par thème : liseré supérieur + glow doux + tint ──
   Chaque carte reçoit une classe kpi-accent-{couleur}. Effet additif, n'altère
   pas le contenu. Inspiré Stripe / Linear / Zoho. */
.kpi-card[class*="kpi-accent-"]::before {
    content:''; position:absolute; inset:0 0 auto 0; height:3px; border-radius:16px 16px 0 0;
    background: linear-gradient(90deg, var(--kpi-c1), var(--kpi-c2));
    opacity:.9;
}
.kpi-card[class*="kpi-accent-"]::after {
    content:''; position:absolute; top:-40%; right:-20%; width:180px; height:180px; border-radius:50%;
    background: radial-gradient(circle, var(--kpi-glow) 0%, transparent 70%);
    opacity:.5; pointer-events:none; transition:opacity .3s;
}
.kpi-card[class*="kpi-accent-"]:hover::after { opacity:.85; }
.kpi-card[class*="kpi-accent-"]:hover { box-shadow: 0 16px 44px -10px var(--kpi-shadow); }

.kpi-accent-sky    { --kpi-c1:#38bdf8; --kpi-c2:#0ea5e9; --kpi-glow:rgba(56,189,248,.22);  --kpi-shadow:rgba(14,165,233,.22); }
.kpi-accent-indigo { --kpi-c1:#818cf8; --kpi-c2:#4f46e5; --kpi-glow:rgba(99,102,241,.22);  --kpi-shadow:rgba(79,70,229,.22); }
.kpi-accent-emerald{ --kpi-c1:#34d399; --kpi-c2:#059669; --kpi-glow:rgba(16,185,129,.22);  --kpi-shadow:rgba(5,150,105,.22); }
.kpi-accent-violet { --kpi-c1:#a78bfa; --kpi-c2:#7c3aed; --kpi-glow:rgba(139,92,246,.22);  --kpi-shadow:rgba(124,58,237,.22); }
.kpi-accent-rose   { --kpi-c1:#fb7185; --kpi-c2:#e11d48; --kpi-glow:rgba(244,63,94,.18);   --kpi-shadow:rgba(225,29,72,.18); }

/* ── Animated pulse dot ───────────────────────────────────────────────────── */
@keyframes pulse-ring { 0%{transform:scale(.8);opacity:.8} 70%{transform:scale(1.8);opacity:0} 100%{transform:scale(.8);opacity:0} }
.pulse-dot::before { content:''; position:absolute; inset:0; border-radius:50%; background:inherit; animation:pulse-ring 2s ease-out infinite; }

/* ── Progress bar animation ───────────────────────────────────────────────── */
@keyframes fillBar { from{width:0} }
.progress-fill { animation: fillBar .9s cubic-bezier(.22,1,.36,1) forwards; }

/* ── Fade up on load ──────────────────────────────────────────────────────── */
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
.fade-up { animation: fadeUp .45s ease both; }

/* ── Table rows ───────────────────────────────────────────────────────────── */
.data-row { transition: background .12s; }
.data-row:hover { background: #f8faff; }

/* ── Chart period toggle active ───────────────────────────────────────────── */
.period-btn       { transition: all .15s; }
.period-btn.active{ background: #4f46e5; color: #fff !important; }

/* ── Scrollable feed ──────────────────────────────────────────────────────── */
.feed-scroll { scrollbar-width: thin; scrollbar-color: #e5e7eb transparent; }
.feed-scroll::-webkit-scrollbar { width: 4px; }
.feed-scroll::-webkit-scrollbar-track { background: transparent; }
.feed-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 99px; }

/* ── Skeleton shimmer ─────────────────────────────────────────────────────── */
@keyframes shimmer { from{background-position:-200% 0} to{background-position:200% 0} }
.skeleton { background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; }

/* ── Hero gradient bg ─────────────────────────────────────────────────────── */
.hero-bg { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #1e40af 100%); }
</style>
<?php /**PATH C:\laragon\www\iboa\resources\views/partials/dashboard/_dashboard-styles.blade.php ENDPATH**/ ?>