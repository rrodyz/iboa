@extends('layouts.erp')
@section('title', 'Bobine '.$coil->reference)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.coils.index') }}" class="hover:text-gray-700">Bobines</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $coil->reference }}</span>
@endsection

@section('content')
@php $rate = $coil->initial_weight > 0 ? ($coil->remaining_weight / $coil->initial_weight) * 100 : 0; @endphp
<div class="max-w-4xl mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $coil->reference }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $coil->color ?? 'Sans couleur' }} · Lot {{ $coil->lot_number ?? '—' }}</p>
        </div>
        @can('production.update')
        <a href="{{ route('production.coils.edit', $coil) }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg hover:bg-gray-50">Modifier</a>
        @endcan
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Poids restant</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums mt-1">{{ number_format($coil->remaining_weight,0,',',' ') }} kg</p>
            <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full {{ $rate < 20 ? 'bg-red-400' : 'bg-green-400' }}" style="width: {{ min(100,$rate) }}%"></div>
            </div>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Poids initial</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums mt-1">{{ number_format($coil->initial_weight,0,',',' ') }} kg</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Coût / kg</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums mt-1">{{ number_format($coil->cost_per_kg,2,',',' ') }} F</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Statut</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ str_replace('_',' ',ucfirst($coil->status)) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Caractéristiques</h2>
        <dl class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
            <div><dt class="text-gray-500">Article matière</dt><dd class="text-gray-900">{{ $coil->product?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Fournisseur</dt><dd class="text-gray-900">{{ $coil->supplier?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Épaisseur</dt><dd class="text-gray-900">{{ $coil->thickness ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Largeur</dt><dd class="text-gray-900">{{ $coil->width ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Longueur estimée</dt><dd class="text-gray-900">{{ number_format($coil->estimated_length,0,',',' ') }} m</dd></div>
            <div><dt class="text-gray-500">Réception</dt><dd class="text-gray-900">{{ optional($coil->received_at)->format('d/m/Y') ?? '—' }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Consommations</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Date</th>
                        <th class="text-left">Ordre fab.</th>
                        <th class="text-right">Poids consommé</th>
                        <th class="text-right">Longueur</th>
                        <th class="text-right">Coût</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coil->consumptions as $cons)
                    <tr>
                        <td class="text-gray-600">{{ optional($cons->consumed_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="font-mono text-xs text-indigo-600">{{ $cons->productionOrder?->number ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($cons->weight_consumed,2,',',' ') }} kg</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($cons->length_consumed,2,',',' ') }} m</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($cons->cost,0,',',' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Aucune consommation enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
