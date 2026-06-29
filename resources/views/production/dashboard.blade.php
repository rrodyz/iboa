@extends('layouts.erp')
@section('title', 'Tableau de bord production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Production</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tableau de bord production</h1>
            <p class="text-sm text-gray-500 mt-0.5">Fabrication tôles bac — du {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            <button class="px-4 py-1.5 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Filtrer</button>
            @can('production.report.view')
            <a href="{{ route('production.reports') }}" class="px-4 py-1.5 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">Rapports</a>
            @endcan
        </form>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">OF en production</p>
            <p class="text-2xl font-bold text-sky-700 mt-1">{{ $kpis['of_en_cours'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $kpis['of_total'] }} au total</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Mètres produits</p>
            <p class="text-2xl font-bold text-orange-700 tabular-nums mt-1">{{ number_format($kpis['meters'], 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $kpis['of_termine'] }} OF terminés</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Coût matière</p>
            <p class="text-2xl font-bold text-indigo-700 tabular-nums mt-1">{{ number_format($kpis['material_cost'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400 mt-0.5">Coût moyen {{ number_format($avgCost, 0, ',', ' ') }} F/m</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Rendement matière</p>
            <p class="text-2xl font-bold text-emerald-700 tabular-nums mt-1">{{ $kpis['yield'] !== null ? number_format($kpis['yield'], 1, ',', ' ').' %' : '—' }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Chutes {{ number_format($kpis['waste_weight'], 0, ',', ' ') }} kg</p>
        </div>
    </div>

    {{-- [§10] KPIs complémentaires --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">OF en retard</p>
            <p class="text-2xl font-bold {{ $kpis['of_en_retard'] > 0 ? 'text-red-600' : 'text-gray-700' }} mt-1">{{ $kpis['of_en_retard'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">livraison dépassée</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">MP critiques</p>
            <p class="text-2xl font-bold {{ $kpis['mp_critiques'] > 0 ? 'text-amber-600' : 'text-gray-700' }} mt-1">{{ $kpis['mp_critiques'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">sous le stock min</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">PF disponibles</p>
            <p class="text-2xl font-bold text-green-700 tabular-nums mt-1">{{ number_format($kpis['pf_disponibles'], 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">en stock</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Production du jour</p>
            <p class="text-2xl font-bold text-orange-700 tabular-nums mt-1">{{ number_format($kpis['meters_today'], 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">mètres aujourd'hui</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Avariés générés</p>
            <p class="text-2xl font-bold text-red-700 tabular-nums mt-1">{{ number_format($kpis['avaries'], 0, ',', ' ') }}</p>
            <p class="text-xs text-gray-400 mt-0.5">sur la période</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Ventes du jour</p>
            <p class="text-2xl font-bold text-blue-700 tabular-nums mt-1">{{ number_format($kpis['ventes_jour'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400 mt-0.5">factures émises ce jour</p>
        </div>
    </div>

    {{-- TRS — Taux de Rendement Synthétique (§16 CDC) --}}
    @php
        $trsColor = ($trs['trs'] ?? 0) >= 85 ? 'green' : (($trs['trs'] ?? 0) >= 65 ? 'amber' : 'red');
        $dispColor = ($trs['disponibilite'] ?? 0) >= 90 ? 'green' : (($trs['disponibilite'] ?? 0) >= 75 ? 'amber' : 'red');
        $perfColor = ($trs['performance'] ?? 0) >= 90 ? 'green' : (($trs['performance'] ?? 0) >= 75 ? 'amber' : 'red');
        $qualColor = ($trs['qualite'] ?? 0) >= 98 ? 'green' : (($trs['qualite'] ?? 0) >= 95 ? 'amber' : 'red');
    @endphp
    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-semibold text-gray-900">TRS — Taux de Rendement Synthétique</h2>
                <p class="text-xs text-gray-400 mt-0.5">Disponibilité × Performance × Qualité (référence industrie : ≥ 85 %)</p>
            </div>
            <div class="text-center">
                <div class="text-4xl font-black text-{{ $trsColor }}-600">{{ $trs['trs'] ?? '—' }}<span class="text-lg font-normal ml-0.5">%</span></div>
                <div class="text-xs font-medium text-{{ $trsColor }}-500 mt-0.5">
                    @if(($trs['trs'] ?? 0) >= 85) Excellent @elseif(($trs['trs'] ?? 0) >= 65) À améliorer @else Critique @endif
                </div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            @foreach([
                ['Disponibilité', $trs['disponibilite'] ?? 0, $dispColor, 'Arrêts : '.$trs['downtime_h'].' h / '.$trs['theoretical_h'].' h théoriques'],
                ['Performance',   $trs['performance'] ?? 0,   $perfColor,  'Mètres réels vs planifiés'],
                ['Qualité',       $trs['qualite'] ?? 0,       $qualColor,  'Produits bons / produits totaux'],
            ] as [$label, $val, $color, $hint])
            <div class="bg-{{ $color }}-50 rounded-xl p-4 text-center border border-{{ $color }}-100">
                <div class="text-xs text-{{ $color }}-600 font-medium uppercase tracking-wider mb-1">{{ $label }}</div>
                <div class="text-2xl font-bold text-{{ $color }}-700">{{ number_format($val, 1) }} %</div>
                {{-- progress bar --}}
                <div class="mt-2 h-1.5 bg-{{ $color }}-100 rounded-full overflow-hidden">
                    <div class="h-full bg-{{ $color }}-500 rounded-full" style="width:{{ min(100, $val) }}%"></div>
                </div>
                <div class="text-xs text-gray-400 mt-1.5">{{ $hint }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Coût standard vs réel (§11 CDC) --}}
    @if($coutComparaison->isNotEmpty())
    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">Coût standard vs Coût réel</h2>
                <p class="text-xs text-gray-400 mt-0.5">§11 CDC — coût de revient par mètre linéaire</p>
            </div>
        </div>
        <div class="tbl-scroll">
            <table class="tbl w-full">
                <thead>
                    <tr>
                        <th class="text-left">Produit</th>
                        <th class="text-right">Coût réel / m</th>
                        <th class="text-right">Coût std / m</th>
                        <th class="text-right">Écart</th>
                        <th class="text-right">Écart %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($coutComparaison as $row)
                    @php $ecartColor = ($row['ecart'] ?? 0) > 0 ? 'red' : 'green'; @endphp
                    <tr>
                        <td class="font-medium text-gray-900 max-w-[200px] truncate">{{ $row['product'] }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($row['cout_reel'], 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-gray-500">{{ $row['cout_std'] > 0 ? number_format($row['cout_std'], 0, ',', ' ') : '—' }}</td>
                        <td class="text-right tabular-nums font-medium text-{{ $ecartColor }}-600">
                            {{ ($row['ecart'] > 0 ? '+' : '') . number_format($row['ecart'], 0, ',', ' ') }}
                        </td>
                        <td class="text-right tabular-nums text-{{ $ecartColor }}-600 text-xs">
                            {{ $row['ecart_pct'] !== null ? ($row['ecart_pct'] > 0 ? '+' : '') . number_format($row['ecart_pct'], 1) . ' %' : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- [§10] Stock disponible par dépôt --}}
    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Stock disponible par dépôt</h2></div>
        <div class="tbl-scroll">
            <table class="tbl w-full">
                <thead><tr><th class="text-left">Dépôt</th><th class="text-left">Type</th><th class="text-right">Disponible</th></tr></thead>
                <tbody>
                    @forelse($stockParDepot as $row)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $row->n }}</td>
                        <td class="text-gray-500 text-xs">{{ \App\Models\Warehouse::TYPES[$row->t] ?? ($row->t ?? '—') }}</td>
                        <td class="text-right tabular-nums">{{ number_format((float) $row->dispo, 0, ',', ' ') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-gray-400 py-6">Aucun stock</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Production journalière --}}
        <div class="lg:col-span-2 bg-white border border-gray-100 shadow-sm rounded-2xl p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Production journalière (mètres)</h2>
            @php $max = collect($chartDaily['data'])->max() ?: 1; @endphp
            @if(count($chartDaily['data']))
            <div class="flex items-end gap-1.5 h-48">
                @foreach($chartDaily['data'] as $i => $val)
                <div class="flex-1 flex flex-col items-center justify-end group">
                    <span class="text-[10px] text-gray-500 mb-1 opacity-0 group-hover:opacity-100 tabular-nums">{{ number_format($val, 0, ',', ' ') }}</span>
                    <div class="w-full bg-orange-400 hover:bg-orange-500 rounded-t" style="height: {{ max(2, round($val / $max * 100)) }}%"></div>
                    <span class="text-[9px] text-gray-400 mt-1 -rotate-45 origin-top-left whitespace-nowrap">{{ $chartDaily['labels'][$i] }}</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-400 py-16">Aucune production sur la période.</p>
            @endif
        </div>

        {{-- OF par statut --}}
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-6">
            <h2 class="font-semibold text-gray-900 mb-4">OF par statut</h2>
            @php $labels = ['brouillon'=>['Brouillon','bg-gray-400'],'lance'=>['Lancé','bg-amber-400'],'en_cours'=>['En cours','bg-sky-400'],'termine'=>['Terminé','bg-green-500'],'annule'=>['Annulé','bg-red-400']]; $tot = $byStatus->sum() ?: 1; @endphp
            <div class="space-y-3">
                @foreach($labels as $k => [$lbl, $color])
                @php $v = $byStatus[$k] ?? 0; @endphp
                <div>
                    <div class="flex justify-between text-sm mb-1"><span class="text-gray-600">{{ $lbl }}</span><span class="font-semibold text-gray-900 tabular-nums">{{ $v }}</span></div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full {{ $color }}" style="width: {{ round($v / $tot * 100) }}%"></div></div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top clients --}}
    <div class="bg-white border border-gray-100 shadow-sm rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Top clients — mètres produits</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Client</th><th class="text-right">Mètres</th><th class="text-left w-1/2">Part</th></tr></thead>
                <tbody>
                    @php $maxC = $topClients->max('m') ?: 1; @endphp
                    @forelse($topClients as $c)
                    <tr>
                        <td class="text-gray-800">{{ $c->client }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($c->m, 2, ',', ' ') }}</td>
                        <td><div class="h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-orange-400" style="width: {{ round($c->m / $maxC * 100) }}%"></div></div></td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-10 text-center text-gray-400">Aucune donnée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
