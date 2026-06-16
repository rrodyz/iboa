@extends('layouts.erp')
@section('title', 'Non-conformités')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Non-conformités</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Non-conformités (CAPA)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Registre des non-conformités & actions correctives</p>
        </div>
        @can('production.update')
        <a href="{{ route('qualite.non-conformities.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle NC
        </a>
        @endcan
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4"><p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Ouvertes</p><p class="text-lg font-bold text-amber-800 mt-1">{{ $stats['ouvertes'] }}</p></div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4"><p class="text-xs font-medium text-red-600 uppercase tracking-wider">Critiques ouvertes</p><p class="text-lg font-bold text-red-800 mt-1">{{ $stats['critiques'] }}</p></div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4"><p class="text-xs font-medium text-green-600 uppercase tracking-wider">Clôturées</p><p class="text-lg font-bold text-green-800 mt-1">{{ $stats['cloturees'] }}</p></div>
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tous statuts</option>
            @foreach(['ouverte'=>'Ouverte','en_cours'=>'En cours','cloturee'=>'Clôturée'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
        </select>
        <select name="severity" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Toutes gravités</option>
            @foreach(['mineure'=>'Mineure','majeure'=>'Majeure','critique'=>'Critique'] as $k=>$v)<option value="{{ $k }}" @selected(request('severity')===$k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Filtrer</button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Réf.</th><th class="text-left">Titre</th><th class="text-left">Gravité</th><th class="text-left">Statut</th><th class="text-left">Responsable</th><th class="text-left">Échéance</th><th></th></tr></thead>
                <tbody>
                    @forelse($items as $nc)
                    <tr>
                        <td class="font-mono text-xs text-indigo-600">{{ $nc->reference }}</td>
                        <td class="text-gray-800">{{ $nc->title }}</td>
                        <td>
                            @php $vc = match($nc->severity){ 'mineure'=>'bg-gray-100 text-gray-600','majeure'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $vc }}">{{ $nc->severityLabel() }}</span>
                        </td>
                        <td>
                            @php $sc = match($nc->status){ 'ouverte'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-sky-100 text-sky-700',default=>'bg-green-100 text-green-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $nc->statusLabel() }}</span>
                        </td>
                        <td class="text-gray-600 text-xs">{{ $nc->responsible?->full_name ?? '—' }}</td>
                        <td class="text-gray-500 text-xs">{{ optional($nc->due_date)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('qualite.non-conformities.edit', $nc) }}" class="text-indigo-600 hover:underline text-xs font-medium">Traiter</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucune non-conformité. 👍</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $items->links() }}</div>@endif
    </div>
</div>
@endsection
