@extends('layouts.erp')
@section('title', 'Contrôles qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Contrôles qualité</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Contrôles qualité</h1>
            <p class="text-sm text-gray-500 mt-0.5">Réception · en-cours · produit fini</p>
        </div>
        @can('production.update')
        <a href="{{ route('qualite.inspections.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau contrôle
        </a>
        @endcan
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4"><p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Contrôles</p><p class="text-lg font-bold text-indigo-800 mt-1">{{ $stats['total'] }}</p></div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4"><p class="text-xs font-medium text-red-600 uppercase tracking-wider">Non conformes</p><p class="text-lg font-bold text-red-800 mt-1">{{ $stats['non_conforme'] }}</p></div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4"><p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Qté rejetée</p><p class="text-lg font-bold text-amber-800 tabular-nums mt-1">{{ number_format($stats['rejected'],0,',',' ') }}</p></div>
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
        <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tous types</option>
            @foreach(['reception'=>'Réception','en_cours'=>'En cours','produit_fini'=>'Produit fini'] as $k=>$v)<option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>@endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tous statuts</option>
            @foreach(['conforme'=>'Conforme','non_conforme'=>'Non conforme','partiel'=>'Partiel'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Filtrer</button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Réf.</th><th class="text-left">Type</th><th class="text-left">Source</th><th class="text-right">Contrôlé</th><th class="text-right">Rejeté</th><th class="text-left">Statut</th><th class="text-left">Date</th><th></th></tr></thead>
                <tbody>
                    @forelse($inspections as $i)
                    <tr>
                        <td class="font-mono text-xs text-indigo-600">{{ $i->reference }}</td>
                        <td class="text-gray-600">{{ $i->typeLabel() }}</td>
                        <td class="text-gray-600 text-xs">{{ $i->reception?->number ?? $i->product?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($i->quantity_checked,0,',',' ') }}</td>
                        <td class="text-right tabular-nums {{ $i->quantity_rejected>0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ number_format($i->quantity_rejected,0,',',' ') }}</td>
                        <td>
                            @php $sc = match($i->status){ 'conforme'=>'bg-green-100 text-green-700','partiel'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $i->statusLabel() }}</span>
                        </td>
                        <td class="text-gray-500">{{ optional($i->inspected_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('qualite.inspections.edit', $i) }}" class="text-indigo-600 hover:underline text-xs font-medium">Modifier</a>
                            @if($i->status !== 'conforme')<a href="{{ route('qualite.non-conformities.create', ['quality_inspection_id' => $i->id]) }}" class="text-red-600 hover:underline text-xs ml-2">+ NC</a>@endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun contrôle.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($inspections->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $inspections->links() }}</div>@endif
    </div>
</div>
@endsection
