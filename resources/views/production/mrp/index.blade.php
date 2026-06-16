@extends('layouts.erp')
@section('title', 'MRP — Réapprovisionnement bobines')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Réappro (MRP)</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">MRP — Réapprovisionnement bobines</h1>
            <p class="text-sm text-gray-500 mt-0.5">Déficits de matière première (poids disponible &lt; seuil minimum produit)</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Produits en déficit</p>
            <p class="text-lg font-bold text-red-800 mt-1">{{ $stats['count'] }}</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Déficit total</p>
            <p class="text-lg font-bold text-amber-800 tabular-nums mt-1">{{ number_format($stats['deficit'], 0, ',', ' ') }} kg</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Coût estimé</p>
            <p class="text-lg font-bold text-indigo-800 tabular-nums mt-1">{{ number_format($stats['estimated'], 0, ',', ' ') }} F</p>
        </div>
    </div>

    <form method="POST" action="{{ route('production.mrp.generate') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        @csrf
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Déficits matière</h2>
            @can('production.update')
            @if($shortfalls->isNotEmpty())
            <button class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Générer demande d'achat
            </button>
            @endif
            @endcan
        </div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left w-8"></th>
                        <th class="text-left">Matière</th>
                        <th class="text-right">Disponible</th>
                        <th class="text-right">Seuil min</th>
                        <th class="text-right">Déficit</th>
                        <th class="text-right">Coût/kg moy.</th>
                        <th class="text-right">Coût estimé</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shortfalls as $s)
                    <tr>
                        <td><input type="checkbox" name="product_ids[]" value="{{ $s['product_id'] }}" checked class="rounded border-gray-300 text-indigo-600"></td>
                        <td class="text-gray-800 font-medium">{{ $s['product'] }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($s['available'], 0, ',', ' ') }} kg</td>
                        <td class="text-right tabular-nums text-gray-500">{{ number_format($s['min'], 0, ',', ' ') }} kg</td>
                        <td class="text-right tabular-nums font-semibold text-red-600">{{ number_format($s['deficit'], 0, ',', ' ') }} kg</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($s['avg_cost_per_kg'], 2, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($s['estimated'], 0, ',', ' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun déficit — stock matière au-dessus des seuils. 👍</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <p class="text-xs text-gray-400">Le seuil minimum provient du champ « stock min » de chaque produit-matière (en kg). Définissez-le sur les articles bobines pour activer le suivi.</p>
</div>
@endsection
