{{--
    Dashboard — bloc <style> spécifique au dashboard.
    Animations KPI hover lift, fade-in-up séquencé, toggle période active.
--}}
<style>
/* ── KPI card hover lift ───────────────────────────────────────────────────── */
.kpi-card { transition: box-shadow .2s, transform .2s; }
.kpi-card:hover { box-shadow: 0 12px 40px -8px rgba(79,70,229,.16); transform: translateY(-2px); }

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
