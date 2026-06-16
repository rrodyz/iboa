@extends('layouts.erp')
@section('title', 'Bobines (matière première)')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Bobines</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Bobines — matière première</h1>
            <p class="text-sm text-gray-500 mt-0.5">Réception, lot, poids restant & coût au kg</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.coils.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Réceptionner une bobine
        </a>
        @endcan
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Bobines</p>
            <p class="text-lg font-bold text-indigo-800 mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Disponibles</p>
            <p class="text-lg font-bold text-green-800 mt-1">{{ $stats['disponible'] }}</p>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-xl p-4">
            <p class="text-xs font-medium text-sky-600 uppercase tracking-wider">Poids restant</p>
            <p class="text-lg font-bold text-sky-800 tabular-nums mt-1">{{ number_format($stats['poids_dispo'], 0, ',', ' ') }} kg</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Valeur stock</p>
            <p class="text-lg font-bold text-amber-800 tabular-nums mt-1">{{ number_format($stats['valeur'], 0, ',', ' ') }} F</p>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Référence, lot, couleur…"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-56">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tous les statuts</option>
            <option value="disponible"    @selected(request('status')==='disponible')>Disponible</option>
            <option value="en_production" @selected(request('status')==='en_production')>En production</option>
            <option value="epuisee"       @selected(request('status')==='epuisee')>Épuisée</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['q','status']))<a href="{{ route('production.coils.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">✕</a>@endif
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Référence</th>
                        <th class="text-left">Lot</th>
                        <th class="text-left">Couleur</th>
                        <th class="text-right">Ép.×Larg.</th>
                        <th class="text-right">Restant / Initial</th>
                        <th class="text-right">Coût/kg</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($coils as $c)
                    @php $rate = $c->initial_weight > 0 ? ($c->remaining_weight / $c->initial_weight) * 100 : 0; @endphp
                    <tr>
                        <td class="font-mono text-xs text-indigo-600">
                            <a href="{{ route('production.coils.show', $c) }}" class="hover:underline">{{ $c->reference }}</a>
                        </td>
                        <td class="text-gray-600">{{ $c->lot_number ?? '—' }}</td>
                        <td class="text-gray-600">{{ $c->color ?? '—' }}</td>
                        <td class="text-right text-gray-600 tabular-nums">{{ rtrim(rtrim(number_format($c->thickness,2,',',''),'0'),',') }} × {{ number_format($c->width,0,',',' ') }}</td>
                        <td class="text-right tabular-nums">
                            <span class="font-semibold text-gray-900">{{ number_format($c->remaining_weight,0,',',' ') }}</span>
                            <span class="text-gray-400"> / {{ number_format($c->initial_weight,0,',',' ') }} kg</span>
                            <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden w-24 ml-auto">
                                <div class="h-full {{ $rate < 20 ? 'bg-red-400' : 'bg-green-400' }}" style="width: {{ min(100,$rate) }}%"></div>
                            </div>
                        </td>
                        <td class="text-right font-mono tabular-nums text-gray-700">{{ number_format($c->cost_per_kg,2,',',' ') }}</td>
                        <td>
                            @php $sc = match($c->status){ 'disponible'=>'bg-green-100 text-green-700','en_production'=>'bg-sky-100 text-sky-700',default=>'bg-gray-100 text-gray-500' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ str_replace('_',' ',ucfirst($c->status)) }}</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.coils.edit', $c) }}" class="text-indigo-600 hover:underline text-xs font-medium">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.coils.destroy', $c) }}" class="inline ml-2" data-confirm="Supprimer cette bobine ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune bobine. Réceptionnez-en une.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($coils->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $coils->links() }}</div>@endif
    </div>
</div>
@endsection
