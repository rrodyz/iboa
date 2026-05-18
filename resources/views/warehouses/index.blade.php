@extends('layouts.erp')
@section('title', 'Entrepôts & Dépôts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Entrepôts</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Entrepôts & Dépôts</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $warehouses->total() }} entrepôt(s) configuré(s)</p>
        </div>
        @can('stocks.adjust')
        <a href="{{ route('stocks.warehouses.create') }}"
           class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors self-start sm:self-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvel entrepôt
        </a>
        @endcan
    </div>

    {{-- Search --}}
    <form method="GET" class="flex gap-3">
        <div class="relative flex-1 max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Nom, code, ville…"
                   class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
            Chercher
        </button>
        @if($search)
        <a href="{{ route('stocks.warehouses.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">✕</a>
        @endif
    </form>

    {{-- Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($warehouses as $wh)
        <div class="bg-white rounded-xl border border-gray-200 hover:border-emerald-300 hover:shadow-sm transition-all group">
            <div class="p-5">
                {{-- Top row --}}
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">{{ $wh->code }}</span>
                            @if($wh->is_default)
                            <span class="text-xs font-medium text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">Défaut</span>
                            @endif
                            @if(!$wh->is_active)
                            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded">Inactif</span>
                            @endif
                        </div>
                        <h3 class="mt-1.5 text-base font-semibold text-gray-900 truncate">{{ $wh->name }}</h3>
                        @if($wh->city)
                        <p class="text-xs text-gray-500 mt-0.5 truncate">
                            <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $wh->city }}@if($wh->address) — {{ $wh->address }}@endif
                        </p>
                        @endif
                    </div>
                    {{-- Icon --}}
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-900">{{ number_format($wh->product_stocks_count) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Articles</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xl font-bold text-gray-900">{{ number_format($wh->stock_movements_count) }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Mouvements</p>
                    </div>
                </div>

                @if($wh->manager_name || $wh->phone)
                <div class="flex items-center gap-3 text-xs text-gray-500 mb-4 flex-wrap">
                    @if($wh->manager_name)
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        {{ $wh->manager_name }}
                    </span>
                    @endif
                    @if($wh->phone)
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $wh->phone }}
                    </span>
                    @endif
                </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
                    <a href="{{ route('stocks.warehouses.show', $wh) }}"
                       class="flex-1 text-center text-xs font-medium text-emerald-700 hover:text-emerald-900 hover:bg-emerald-50 py-1.5 rounded-lg transition-colors">
                        Voir le stock
                    </a>
                    @can('stocks.adjust')
                    <a href="{{ route('stocks.warehouses.edit', $wh) }}"
                       class="flex-1 text-center text-xs font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 py-1.5 rounded-lg transition-colors">
                        Modifier
                    </a>
                    @if(!$wh->is_default)
                    <form action="{{ route('stocks.warehouses.destroy', $wh) }}" method="POST"
                          onsubmit="return confirm('Supprimer cet entrepôt ?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-xs font-medium text-red-500 hover:text-red-700 hover:bg-red-50 py-1.5 px-3 rounded-lg transition-colors">
                            Supprimer
                        </button>
                    </form>
                    @endif
                    @endcan
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full py-20 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <p class="text-sm">Aucun entrepôt trouvé</p>
            @can('stocks.adjust')
            <a href="{{ route('stocks.warehouses.create') }}" class="mt-3 inline-block text-sm text-emerald-600 hover:underline">
                Créer le premier entrepôt →
            </a>
            @endcan
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($warehouses->hasPages())
    <div>{{ $warehouses->appends(['search' => $search])->links() }}</div>
    @endif

</div>
@endsection
