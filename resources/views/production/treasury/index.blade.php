@extends('layouts.erp')
@section('title', 'Prévision trésorerie production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Prévision trésorerie</span>
@endsection

@section('content')
<div class="space-y-5">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Prévision trésorerie production</h1>
        <p class="text-sm text-gray-500 mt-0.5">Besoin de financement, achats matières à venir et marge réalisée</p>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Coûts engagés (OF actifs)</p>
            <p class="text-xl font-bold text-indigo-800 tabular-nums mt-1">{{ number_format($forecast['engaged_cost'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $forecast['active_count'] }} OF lancés / en cours</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Achats matières à prévoir</p>
            <p class="text-xl font-bold text-amber-800 tabular-nums mt-1">{{ number_format($forecast['material_need'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400 mt-0.5">Déficit bobines (MRP)</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Besoin de financement</p>
            <p class="text-xl font-bold text-red-800 tabular-nums mt-1">{{ number_format($forecast['financing_need'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400 mt-0.5">Engagé + achats</p>
        </div>
        <div class="{{ $forecast['realized_margin'] >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border rounded-2xl p-4">
            <p class="text-xs font-medium {{ $forecast['realized_margin'] >= 0 ? 'text-green-600' : 'text-red-600' }} uppercase tracking-wider">Marge réalisée</p>
            <p class="text-xl font-bold {{ $forecast['realized_margin'] >= 0 ? 'text-green-800' : 'text-red-800' }} tabular-nums mt-1">{{ number_format($forecast['realized_margin'], 0, ',', ' ') }} F</p>
            <p class="text-xs text-gray-400 mt-0.5">OF terminés</p>
        </div>
    </div>

    {{-- Détail OF actifs --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Engagements par OF actif</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">N° OF</th><th class="text-left">Client</th><th class="text-left">Statut</th><th class="text-right">Coût engagé</th></tr></thead>
                <tbody>
                    @forelse($forecast['breakdown'] as $row)
                    <tr>
                        <td class="font-mono text-xs text-indigo-600">{{ $row['number'] }}</td>
                        <td class="text-gray-800">{{ $row['client'] }}</td>
                        <td class="text-gray-600">{{ $row['status'] }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($row['cost'], 0, ',', ' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">Aucun OF actif.</td></tr>
                    @endforelse
                </tbody>
                @if($forecast['breakdown']->isNotEmpty())
                <tfoot>
                    <tr class="font-semibold bg-gray-50">
                        <td colspan="3" class="text-right text-gray-500">Total engagé</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($forecast['engaged_cost'], 0, ',', ' ') }} F</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400">Les coûts engagés proviennent des coûts de revient calculés sur les OF lancés/en cours. Calculez le coût (onglet OF) pour affiner la prévision.</p>
</div>
@endsection
