@extends('layouts.erp')
@section('title', 'Ordres de fabrication')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Ordres de fabrication</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ordres de fabrication</h1>
            <p class="text-sm text-gray-500 mt-0.5">Lancement, suivi & clôture de la production tôle bac</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.orders.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvel OF
        </a>
        @endcan
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Brouillons</p>
            <p class="text-lg font-bold text-gray-800 mt-1">{{ $stats['brouillon'] }}</p>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-xl p-4">
            <p class="text-xs font-medium text-sky-600 uppercase tracking-wider">En production</p>
            <p class="text-lg font-bold text-sky-800 mt-1">{{ $stats['en_cours'] }}</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Terminés</p>
            <p class="text-lg font-bold text-green-800 mt-1">{{ $stats['termine'] }}</p>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
            <p class="text-xs font-medium text-orange-600 uppercase tracking-wider">Mètres produits</p>
            <p class="text-lg font-bold text-orange-800 tabular-nums mt-1">{{ number_format($stats['metres'], 0, ',', ' ') }} m</p>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="N° OF…" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-40">
        <select name="client_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-48">
            <option value="">Tous les clients</option>
            @foreach($clients as $c)<option value="{{ $c->id }}" @selected(request('client_id')==$c->id)>{{ $c->trade_name ?? $c->name }}</option>@endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Tous les statuts</option>
            @foreach(['brouillon'=>'Brouillon','lance'=>'Lancé','en_cours'=>'En cours','termine'=>'Terminé','annule'=>'Annulé'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Filtrer</button>
        @if(request()->hasAny(['q','client_id','status']))<a href="{{ route('production.orders.index') }}" class="px-3 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">✕</a>@endif
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N° OF</th>
                        <th class="text-left">Client</th>
                        <th class="text-left">Produit</th>
                        <th class="text-left">Type / Ép.</th>
                        <th class="text-right">Qté</th>
                        <th class="text-left">Ligne</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr class="{{ $o->status === 'annule' ? 'opacity-50' : '' }}">
                        <td class="font-mono text-xs text-indigo-600"><a href="{{ route('production.orders.show', $o) }}" class="hover:underline">{{ $o->number }}</a></td>
                        <td class="text-gray-800">{{ $o->client?->trade_name ?? $o->client?->name ?? '—' }}</td>
                        <td class="text-gray-600">{{ $o->product?->name ?? '—' }}</td>
                        <td class="text-gray-600">{{ $o->sheet_type ?? '—' }}{{ $o->thickness ? ' · '.rtrim(rtrim(number_format($o->thickness,2,',',''),'0'),',').' mm' : '' }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($o->quantity_requested, 0, ',', ' ') }}</td>
                        <td class="text-gray-500 text-xs">{{ $o->productionLine?->name ?? '—' }}</td>
                        <td>
                            @php $sc = match($o->status){ 'brouillon'=>'bg-gray-100 text-gray-600','lance'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-sky-100 text-sky-700','termine'=>'bg-green-100 text-green-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $o->statusLabel() }}</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('production.orders.show', $o) }}" class="text-indigo-600 hover:underline text-xs font-medium">Ouvrir</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucun ordre de fabrication.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $orders->links() }}</div>@endif
    </div>
</div>
@endsection
