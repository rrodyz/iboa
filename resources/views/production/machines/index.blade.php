@extends('layouts.erp')
@section('title', 'Machines de production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Machines</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Machines de production</h1>
            <p class="text-sm text-gray-500 mt-0.5">Découpe, profilage — coût horaire & disponibilité</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.machines.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle machine
        </a>
        @endcan
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
        <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tous les types</option>
            <option value="decoupe"   @selected(request('type')==='decoupe')>Découpe</option>
            <option value="profilage" @selected(request('type')==='profilage')>Profilage</option>
            <option value="mixte"     @selected(request('type')==='mixte')>Mixte</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request('type'))<a href="{{ route('production.machines.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">✕</a>@endif
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Code</th>
                        <th class="text-left">Nom</th>
                        <th class="text-left">Type</th>
                        <th class="text-right">Coût horaire</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($machines as $m)
                    <tr class="{{ $m->is_active ? '' : 'opacity-50' }}">
                        <td class="font-mono text-xs text-indigo-600">{{ $m->code }}</td>
                        <td class="text-gray-800 font-medium">{{ $m->name }}</td>
                        <td class="text-gray-600">{{ ucfirst($m->type) }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($m->hourly_cost, 0, ',', ' ') }} F</td>
                        <td>
                            @php $sc = match($m->status){ 'active'=>'bg-green-100 text-green-700','maintenance'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ ucfirst($m->status) }}</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.machines.edit', $m) }}" class="text-indigo-600 hover:underline text-xs font-medium">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.machines.destroy', $m) }}" class="inline ml-2" data-confirm="Supprimer cette machine ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucune machine. Créez-en une.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($machines->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $machines->links() }}</div>@endif
    </div>
</div>
@endsection
