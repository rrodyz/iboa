@extends('layouts.erp')
@section('title', 'Niveaux de stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Stocks</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- ── Proactive stock alerts ─────────────────────────────────────────────── --}}
    @if($ruptureCount > 0 || $lowStockCount > 0)
    <div class="flex flex-col sm:flex-row gap-3">

        @if($ruptureCount > 0)
        <div class="flex-1 flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 animate-fade-in-down">
            <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-red-800">
                    {{ $ruptureCount }} {{ Str::plural('article', $ruptureCount) }} en rupture de stock
                </p>
                <p class="text-xs text-red-600 mt-0.5">Stock disponible nul ou négatif — approvisionnement urgent requis.</p>
            </div>
            <a href="{{ route('stocks.index', ['low_stock' => 1]) }}"
               class="flex-shrink-0 text-xs font-medium text-red-700 hover:text-red-900 underline whitespace-nowrap mt-0.5">
                Voir →
            </a>
        </div>
        @endif

        @if($lowStockCount > 0)
        <div class="flex-1 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 animate-fade-in-down">
            <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-800">
                    {{ $lowStockCount }} {{ Str::plural('article', $lowStockCount) }} sous le seuil d'alerte
                </p>
                <p class="text-xs text-amber-600 mt-0.5">Quantité disponible inférieure ou égale au stock minimum configuré.</p>
            </div>
            <a href="{{ route('stocks.index', ['low_stock' => 1]) }}"
               class="flex-shrink-0 text-xs font-medium text-amber-700 hover:text-amber-900 underline whitespace-nowrap mt-0.5">
                Filtrer →
            </a>
        </div>
        @endif

    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Niveaux de stock</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $stocks->total() }} articles</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            {{-- [STOCK-PRO] Lien vers le tableau de bord avancé --}}
            <a href="{{ route('stocks.dashboard') }}"
               class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-3 py-2 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Tableau de bord
            </a>
            {{-- Export Excel état de stock (reprend les filtres actifs) --}}
            <a href="{{ route('stocks.export', array_filter([
                    'warehouse_id' => $filters['warehouse_id'] ?? null,
                    'search'       => $filters['search']       ?? null,
                    'low_stock'    => ($filters['low_stock']   ?? null) ? 1 : null,
                ])) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('stocks.export-pdf', array_filter([
                    'warehouse_id' => $filters['warehouse_id'] ?? null,
                    'search'       => $filters['search']       ?? null,
                    'low_stock'    => ($filters['low_stock']   ?? null) ? 1 : null,
                ])) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('stocks.inventaires.create') }}"
               class="border border-teal-600 text-teal-700 hover:bg-teal-50 text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Inventaire
            </a>
            {{-- [PRIO-3] Raccourci dédié au transfert inter-entrepôts --}}
            <a href="{{ route('stocks.movement.create', ['type' => 'transfert']) }}"
               class="border border-blue-600 text-blue-700 hover:bg-blue-50 text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors"
               title="Transférer du stock entre deux entrepôts">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                Transfert
            </a>
            <a href="{{ route('stocks.movement.create') }}"
               class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau mouvement
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Référence, désignation..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">

            <select name="warehouse_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                <option value="">Tous les entrepôts</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>

            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2">
                <input type="checkbox" name="low_stock" value="1"
                       {{ !empty($filters['low_stock']) ? 'checked' : '' }}
                       class="rounded text-teal-600 focus:ring-teal-500">
                Stock bas uniquement
            </label>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'warehouse_id', 'low_stock']))
                <a href="{{ route('stocks.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Quick nav --}}
    <div class="flex gap-2 text-sm">
        <a href="{{ route('stocks.movements') }}"
           class="text-teal-600 hover:text-teal-800 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/>
            </svg>
            Voir les mouvements
        </a>
        <span class="text-gray-300">|</span>
        <a href="{{ route('stocks.inventaires.index') }}"
           class="text-teal-600 hover:text-teal-800 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Inventaires
        </a>
        <span class="text-gray-300">|</span>
        <a href="{{ route('stocks.lots') }}"
           class="text-teal-600 hover:text-teal-800 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            Lots & Traçabilité
        </a>
        <span class="text-gray-300">|</span>
        <a href="{{ route('stocks.warehouses.index') }}"
           class="text-teal-600 hover:text-teal-800 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            Entrepôts
        </a>
        @can('stocks.adjust')
        <span class="text-gray-300">|</span>
        <a href="{{ route('stocks.seuils') }}"
           class="text-orange-600 hover:text-orange-800 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
            Seuils min/max
        </a>
        @endcan
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Référence</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Entrepôt</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock dispo</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Réservé</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Physique</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider hidden xl:table-cell">Min / Max</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stocks as $stock)
                        @php
                            $available = (float) $stock->quantity - (float) $stock->reserved_quantity;
                            $threshold = (float) ($stock->product?->stock_min ?? 0);
                            if ($available <= 0) {
                                $rowClass    = 'bg-red-50';
                                $badgeClass  = 'bg-red-100 text-red-700';
                                $badgeLabel  = 'Rupture';
                                $qtyClass    = 'text-red-600 font-semibold';
                            } elseif ($threshold > 0 && $available <= $threshold) {
                                $rowClass    = 'bg-orange-50';
                                $badgeClass  = 'bg-orange-100 text-orange-700';
                                $badgeLabel  = 'Stock bas';
                                $qtyClass    = 'text-orange-600 font-semibold';
                            } else {
                                $rowClass    = 'hover:bg-gray-50';
                                $badgeClass  = 'bg-emerald-100 text-emerald-700';
                                $badgeLabel  = 'OK';
                                $qtyClass    = 'text-gray-900';
                            }
                        @endphp
                        <tr class="{{ $rowClass }} transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-gray-600">{{ $stock->product?->reference ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('stocks.show', $stock->product_id) }}"
                                   class="font-medium text-gray-900 hover:text-emerald-700 hover:underline">
                                    {{ $stock->product?->name ?? '—' }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                {{ $stock->warehouse?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $qtyClass }}">
                                {{ number_format($available, 2, ',', ' ') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500 hidden lg:table-cell">
                                {{ number_format((float) $stock->reserved_quantity, 2, ',', ' ') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700 hidden lg:table-cell">
                                {{ number_format((float) $stock->quantity, 2, ',', ' ') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500 hidden xl:table-cell">
                                @php $maxQty = (float) ($stock->product?->stock_max ?? 0); @endphp
                                <span class="{{ $threshold > 0 && $available <= $threshold && $available > 0 ? 'text-orange-600 font-medium' : '' }}">
                                    {{ $threshold > 0 ? number_format($threshold, 0, ',', ' ') : '—' }}
                                </span>
                                @if($maxQty > 0)
                                <span class="text-gray-300 mx-0.5">/</span>
                                <span class="text-gray-400">{{ number_format($maxQty, 0, ',', ' ') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">
                                Aucun article en stock trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($stocks->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $stocks->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
