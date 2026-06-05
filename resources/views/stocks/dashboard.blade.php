@extends('layouts.erp')
@section('title', 'Stock — Tableau de bord')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Stock</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tableau de bord stock</h1>
            <p class="text-sm text-gray-500">Vue d'ensemble · valorisation, ruptures, alertes, DLC</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('stocks.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">Liste détaillée</a>
            <a href="{{ route('stocks.movements') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">Mouvements</a>
            <a href="{{ route('stocks.transfers.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">🔁 Transferts</a>
            <a href="{{ route('stocks.dashboard.abc') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-3 py-2 rounded-lg">📊 ABC</a>
            @can('stocks.write')
            <a href="{{ route('stocks.movement.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-2 rounded-lg">+ Mouvement</a>
            @endcan
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Valorisation totale</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ $fmt($kpis['total_valuation']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA · qté × CMP</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Valeur réservée</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-blue-700">{{ $fmt($kpis['reserved_value']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">FCFA · devis / commandes</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Articles actifs</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ $kpis['active_products'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">références</p>
        </div>

        <a href="{{ route('stocks.dashboard.restock') }}" class="bg-white rounded-xl border-2 border-orange-200 hover:border-orange-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-orange-600 uppercase">⚠ Réappro à déclencher</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-orange-700">{{ $kpis['reorder_count'] }}</p>
            <p class="text-xs text-orange-500 mt-0.5">qté ≤ point de réappro</p>
        </a>

        <div class="bg-white rounded-xl border-2 border-red-200 p-5">
            <p class="text-xs font-medium text-red-600 uppercase">🛑 Ruptures</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-red-700">{{ $kpis['rupture_count'] }}</p>
            <p class="text-xs text-red-500 mt-0.5">qté disponible ≤ 0</p>
        </div>

        <div class="bg-white rounded-xl border border-amber-200 p-5">
            <p class="text-xs font-medium text-amber-600 uppercase">Sous seuil min</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ $kpis['below_min_count'] }}</p>
            <p class="text-xs text-amber-500 mt-0.5">qté &lt; stock_min</p>
        </div>

        <a href="{{ route('stocks.dashboard.dormant') }}" class="bg-white rounded-xl border-2 border-gray-200 hover:border-gray-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-gray-600 uppercase">💤 Dormants</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-700">{{ $kpis['dormant_count'] }}</p>
            <p class="text-xs text-gray-500 mt-0.5">aucun mouvement &gt; 90 j</p>
        </a>

        <a href="{{ route('stocks.dashboard.expiry') }}" class="bg-white rounded-xl border-2 border-rose-200 hover:border-rose-300 p-5 transition-colors block">
            <p class="text-xs font-medium text-rose-600 uppercase">🗓 DLC</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-rose-700">
                {{ $kpis['expiring_count'] }}<span class="text-sm text-rose-400 ml-1">+ {{ $kpis['expired_count'] }} périmés</span>
            </p>
            <p class="text-xs text-rose-500 mt-0.5">lots &lt; 30 j</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- Top valorisation --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Top 10 par valorisation</h2>
                <a href="{{ route('stocks.valuation') }}" class="text-xs text-blue-600 hover:underline">Voir tout →</a>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Article</th>
                        <th class="px-4 py-2 text-right">Qté</th>
                        <th class="px-4 py-2 text-right">Valeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($topValuation as $row)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-xs text-blue-700">{{ $row->reference }}</a>
                            <p class="text-xs text-gray-700">{{ $row->name }}</p>
                            <p class="text-xs text-gray-400">{{ $row->warehouse_name }}</p>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ number_format($row->quantity, 2, ',', ' ') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums font-medium">{{ $fmt($row->valuation) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Aucune donnée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Top mouvements ce mois --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Top 10 mouvementés — {{ now()->translatedFormat('F Y') }}</h2>
                <a href="{{ route('stocks.movements') }}" class="text-xs text-blue-600 hover:underline">Tous →</a>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Article</th>
                        <th class="px-4 py-2 text-right">Entrées</th>
                        <th class="px-4 py-2 text-right">Sorties</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($topMoved as $row)
                    <tr>
                        <td class="px-4 py-2">
                            <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-xs text-blue-700">{{ $row->reference }}</a>
                            <p class="text-xs text-gray-700">{{ $row->name }}</p>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-emerald-700">+ {{ number_format($row->qty_in, 2, ',', ' ') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-red-600">− {{ number_format($row->qty_out, 2, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Aucun mouvement ce mois.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Aperçu alertes réappro --}}
        <div class="bg-white rounded-xl border border-orange-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-orange-100 bg-orange-50 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-orange-800">⚠ Alertes réappro</h2>
                <a href="{{ route('stocks.dashboard.restock') }}" class="text-xs text-orange-700 hover:underline">Tout →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($alertsPreview as $r)
                <div class="px-4 py-3">
                    <p class="font-mono text-xs text-blue-700">{{ $r->reference }}</p>
                    <p class="text-sm text-gray-900 truncate">{{ $r->name }}</p>
                    <p class="text-xs text-orange-600 mt-0.5">Dispo : {{ number_format($r->available_qty, 0, ',', ' ') }} / réappro à {{ $r->reorder_point }}</p>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-gray-400 text-sm">Aucune alerte.</div>
                @endforelse
            </div>
        </div>

        {{-- Aperçu dormants --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">💤 Articles dormants</h2>
                <a href="{{ route('stocks.dashboard.dormant') }}" class="text-xs text-gray-600 hover:underline">Tout →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($dormantPreview as $r)
                <div class="px-4 py-3">
                    <p class="font-mono text-xs text-blue-700">{{ $r->reference }}</p>
                    <p class="text-sm text-gray-900 truncate">{{ $r->name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($r->days_idle === null)
                            <span class="italic">jamais mouvementé</span>
                        @else
                            {{ (int) $r->days_idle }} j inactif
                        @endif
                        · {{ $fmt($r->immobilized_value) }} FCFA
                    </p>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-gray-400 text-sm">Aucun.</div>
                @endforelse
            </div>
        </div>

        {{-- Aperçu DLC --}}
        <div class="bg-white rounded-xl border border-rose-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-rose-100 bg-rose-50 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-rose-800">🗓 DLC proches</h2>
                <a href="{{ route('stocks.dashboard.expiry') }}" class="text-xs text-rose-700 hover:underline">Tout →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($expiringPreview as $r)
                <div class="px-4 py-3">
                    <p class="font-mono text-xs text-blue-700">{{ $r->reference }}</p>
                    <p class="text-sm text-gray-900 truncate">{{ $r->name }} <span class="text-xs text-gray-500">· lot {{ $r->lot_number ?? '—' }}</span></p>
                    <p class="text-xs text-rose-600 mt-0.5">DLC {{ \Carbon\Carbon::parse($r->expiry_date)->format('d/m/Y') }} ({{ (int) $r->days_left }} j)</p>
                </div>
                @empty
                <div class="px-4 py-8 text-center text-gray-400 text-sm">Aucun lot proche.</div>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
