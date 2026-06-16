@extends('layouts.erp')
@section('title', 'Centres de travail')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Centres de travail</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Centres de travail</h1>
            <p class="text-sm text-gray-500 mt-0.5">Unités de capacité & coût horaire — socle des gammes et de la planification</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.work-centers.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau centre
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Code</th>
                        <th class="text-left">Nom</th>
                        <th class="text-left">Machine</th>
                        <th class="text-right">Capacité/j</th>
                        <th class="text-right">Coût/h</th>
                        <th class="text-right">Rendement</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centers as $c)
                    <tr class="{{ $c->is_active ? '' : 'opacity-50' }}">
                        <td class="font-mono text-xs text-indigo-600">{{ $c->code }}</td>
                        <td class="text-gray-800 font-medium">{{ $c->name }}</td>
                        <td class="text-gray-600">{{ $c->machine?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($c->capacity_hours_per_day, 1, ',', ' ') }} h</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($c->cost_per_hour, 0, ',', ' ') }} F</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($c->efficiency_rate, 0, ',', ' ') }} %</td>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $c->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $c->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.work-centers.edit', $c) }}" class="text-indigo-600 hover:underline text-xs font-medium">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.work-centers.destroy', $c) }}" class="inline ml-2" data-confirm="Supprimer ce centre ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun centre de travail. Créez-en un.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($centers->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $centers->links() }}</div>@endif
    </div>
</div>
@endsection
