@extends('layouts.erp')
@section('title', 'Trésorerie — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Trésorerie</span>
@endsection

@section('content')
@php
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
    $moisLabel = $now->translatedFormat('F Y');
@endphp

<div class="space-y-6">

    {{-- ── En-tête ──────────────────────────────────────────────────────────── --}}
    <x-ui.page-header
        title="Tableau de bord Trésorerie"
        subtitle="Position et flux financiers — {{ $moisLabel }}"
        icon="💳"
        :backUrl="false">
        <x-slot:actions>
            <a href="{{ route('tresorerie.encaissements.create') }}"
               class="btn-primary text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Encaissement
            </a>
            <a href="{{ route('tresorerie.decaissements.create') }}"
               class="btn-secondary text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Décaissement
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- ── KPI principaux ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Position de trésorerie --}}
        <x-ui.stat
            label="Position de trésorerie"
            value="{{ $fmt($positionTotale) }} FCFA"
            sub="{{ $accounts->count() }} compte(s) actif(s)"
            icon="🏦"
            color="{{ $positionTotale >= 0 ? 'emerald' : 'red' }}"
            href="{{ route('tresorerie.caisses.index') }}"
        />

        {{-- Encaissements du mois --}}
        <x-ui.stat
            label="Encaissements {{ $now->translatedFormat('M') }}"
            value="{{ $fmt($encaissMois->total) }} FCFA"
            sub="{{ $encaissMois->cnt }} opération(s)"
            icon="⬇️"
            color="blue"
            href="{{ route('tresorerie.encaissements.index') }}"
        />

        {{-- Décaissements du mois --}}
        <x-ui.stat
            label="Décaissements {{ $now->translatedFormat('M') }}"
            value="{{ $fmt($decaissMois->total) }} FCFA"
            sub="{{ $decaissMois->cnt }} opération(s)"
            icon="⬆️"
            color="amber"
            href="{{ route('tresorerie.decaissements.index') }}"
        />

        {{-- Flux net --}}
        <x-ui.stat
            label="Flux net du mois"
            value="{{ ($fluxNetMois >= 0 ? '+' : '') . $fmt($fluxNetMois) }} FCFA"
            sub="{{ $fluxNetMois >= 0 ? 'Excédent' : 'Déficit' }} {{ $now->translatedFormat('F') }}"
            icon="{{ $fluxNetMois >= 0 ? '📈' : '📉' }}"
            color="{{ $fluxNetMois >= 0 ? 'emerald' : 'red' }}"
        />

    </div>

    {{-- ── Alertes (créances + effets) ─────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- Créances clients --}}
        <x-ui.stat
            label="Créances clients"
            value="{{ $fmt($creancesClients->total) }} FCFA"
            sub="{{ $creancesClients->cnt }} facture(s) en attente"
            icon="📄"
            color="indigo"
            href="{{ route('tresorerie.echeancier-clients') }}"
        />

        {{-- Factures en retard --}}
        <x-ui.stat
            label="Dont en retard"
            value="{{ $fmt($creancesEnRetard->total) }} FCFA"
            sub="{{ $creancesEnRetard->cnt }} facture(s) échues"
            icon="⚠️"
            color="{{ $creancesEnRetard->cnt > 0 ? 'red' : 'gray' }}"
            href="{{ route('tresorerie.echeancier-clients') }}"
        />

        {{-- Effets de commerce --}}
        <x-ui.stat
            label="Effets en attente"
            value="{{ $fmt($effetsEnAttente->total) }} FCFA"
            sub="{{ $effetsEnAttente->cnt }} effet(s) à encaisser"
            icon="🏷️"
            color="{{ $effetsEnAttente->cnt > 0 ? 'violet' : 'gray' }}"
            href="{{ route('tresorerie.effets.index') }}"
        />

    </div>

    {{-- ── Ligne principale : graphe + comptes ─────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Graphe flux 6 mois (2/3) --}}
        <div class="lg:col-span-2 card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Flux de trésorerie — 6 derniers mois</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Encaissements vs Décaissements</p>
                </div>
                <a href="{{ route('tresorerie.previsions.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Voir prévisions →
                </a>
            </div>
            <div id="chart-flux" style="min-height:220px;"></div>
        </div>

        {{-- Répartition par compte (1/3) --}}
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Répartition des soldes</h3>
            @if($accounts->isEmpty())
                <p class="text-sm text-gray-400 text-center py-8">Aucun compte configuré.</p>
            @else
                <div id="chart-comptes" style="min-height:160px;"></div>
                <div class="mt-4 space-y-2">
                    @foreach($accounts as $account)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0
                                @if($loop->index === 0) bg-indigo-500
                                @elseif($loop->index === 1) bg-blue-400
                                @elseif($loop->index === 2) bg-teal-400
                                @else bg-gray-300
                                @endif"></span>
                            <span class="text-gray-700 truncate">{{ $account->name }}</span>
                            <span class="text-gray-400 flex-shrink-0">
                                ({{ ['caisse' => 'Caisse', 'banque' => 'Banque', 'mobile_money' => 'Mobile Money'][$account->type] ?? $account->type }})
                            </span>
                        </div>
                        <span class="font-semibold tabular-nums {{ $account->current_balance < 0 ? 'text-red-600' : 'text-gray-900' }} flex-shrink-0 ml-2">
                            {{ $fmt($account->current_balance) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- ── Ligne inférieure : échéances + derniers encaissements ───────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Échéances dans les 7 jours --}}
        <div class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Échéances dans les 7 jours</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Factures arrivant à terme</p>
                </div>
                <a href="{{ route('tresorerie.echeancier-clients') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Voir tout →
                </a>
            </div>
            @if($echeancesProches->isEmpty())
                <div class="text-center py-8">
                    <span class="text-3xl">✅</span>
                    <p class="text-sm text-gray-500 mt-2">Aucune échéance dans les 7 prochains jours.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($echeancesProches as $ech)
                    @php
                        $due = \Carbon\Carbon::parse($ech->due_at);
                        $daysLeft = (int) now()->startOfDay()->diffInDays($due->startOfDay(), false);
                        $isLate   = $daysLeft < 0;
                        $isToday  = $daysLeft === 0;
                    @endphp
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $ech->client_name }}</p>
                            <p class="text-xs text-gray-500">{{ $ech->number }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-bold tabular-nums text-gray-900">{{ $fmt($ech->remaining_amount) }} FCFA</p>
                            <span class="text-xs font-medium px-1.5 py-0.5 rounded-full
                                @if($isLate) bg-red-100 text-red-700
                                @elseif($isToday) bg-amber-100 text-amber-700
                                @elseif($daysLeft <= 3) bg-orange-100 text-orange-700
                                @else bg-gray-100 text-gray-600
                                @endif">
                                @if($isLate)
                                    {{ abs($daysLeft) }}j de retard
                                @elseif($isToday)
                                    Aujourd'hui
                                @else
                                    J-{{ $daysLeft }}
                                @endif
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Derniers encaissements --}}
        <div class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Derniers encaissements</h3>
                    <p class="text-xs text-gray-500 mt-0.5">5 opérations les plus récentes</p>
                </div>
                <a href="{{ route('tresorerie.encaissements.index') }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Voir tout →
                </a>
            </div>
            @if($derniersEncaissements->isEmpty())
                <div class="text-center py-8">
                    <span class="text-3xl">💤</span>
                    <p class="text-sm text-gray-500 mt-2">Aucun encaissement enregistré.</p>
                    <a href="{{ route('tresorerie.encaissements.create') }}" class="btn-primary text-xs mt-3 inline-flex">
                        Enregistrer un encaissement
                    </a>
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($derniersEncaissements as $enc)
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-base flex-shrink-0">
                            ⬇️
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-900 truncate">{{ $enc->client_name }}</p>
                            <p class="text-xs text-gray-500">{{ $enc->payment_method_name }} · {{ \Carbon\Carbon::parse($enc->payment_date)->format('d/m/Y') }}</p>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-emerald-600 flex-shrink-0">
                            +{{ $fmt($enc->amount) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</div><!-- /space-y-6 -->
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Graphe flux 6 mois ──────────────────────────────────────────────────
    const mois   = @json($chartMois);
    const enc    = @json($chartEnc);
    const dec    = @json($chartDec);
    const flux   = enc.map((v, i) => v - dec[i]);

    if (window.ApexCharts && document.getElementById('chart-flux')) {
        new ApexCharts(document.getElementById('chart-flux'), {
            chart: {
                type: 'bar',
                height: 220,
                stacked: false,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            series: [
                { name: 'Encaissements', data: enc, color: '#10b981' },
                { name: 'Décaissements', data: dec, color: '#f59e0b' },
                { name: 'Flux net',      data: flux, type: 'line', color: '#6366f1' },
            ],
            chart: {
                height: 220,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            stroke: { width: [0, 0, 2], curve: 'smooth' },
            plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
            xaxis: {
                categories: mois,
                labels: { style: { fontSize: '11px', colors: '#6b7280' } },
            },
            yaxis: {
                labels: {
                    formatter: v => v >= 1000000
                        ? (v / 1000000).toFixed(1) + ' M'
                        : v >= 1000 ? Math.round(v / 1000) + ' k' : v,
                    style: { fontSize: '10px', colors: '#9ca3af' },
                },
            },
            legend: { position: 'top', fontSize: '12px' },
            tooltip: {
                y: { formatter: v => new Intl.NumberFormat('fr-FR').format(v) + ' FCFA' },
            },
            grid: { borderColor: '#f3f4f6', strokeDashArray: 4 },
        }).render();
    }

    // ── Doughnut comptes ────────────────────────────────────────────────────
    const labels  = @json($compteLabels);
    const soldes  = @json($compteSoldes);

    if (window.ApexCharts && document.getElementById('chart-comptes') && labels.length > 0) {
        new ApexCharts(document.getElementById('chart-comptes'), {
            chart: {
                type: 'donut',
                height: 160,
                toolbar: { show: false },
                fontFamily: 'inherit',
            },
            series: soldes,
            labels: labels,
            colors: ['#6366f1', '#60a5fa', '#2dd4bf', '#d1d5db'],
            legend: { show: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '11px',
                                color: '#6b7280',
                                formatter: w => {
                                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    return new Intl.NumberFormat('fr-FR').format(total);
                                },
                            },
                        },
                    },
                },
            },
            tooltip: {
                y: { formatter: v => new Intl.NumberFormat('fr-FR').format(v) + ' FCFA' },
            },
            dataLabels: { enabled: false },
        }).render();
    }
});
</script>
@endpush
