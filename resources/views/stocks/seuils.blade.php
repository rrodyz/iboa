@extends('layouts.erp')
@section('title', 'Seuils stock — min / max / réappro')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Seuils min / max</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Seuils de stock — édition en masse</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Configurez le stock minimum, maximum et le point de réapprovisionnement pour chaque article.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('stocks.dashboard.restock') }}"
               class="border border-orange-300 text-orange-700 hover:bg-orange-50 text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Alertes réappro
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $search ?? '' }}"
                   placeholder="Référence, désignation..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">

            <select name="family_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400">
                <option value="">Toutes les familles</option>
                @foreach($families as $fam)
                    <option value="{{ $fam->id }}" {{ $familyId == $fam->id ? 'selected' : '' }}>
                        {{ $fam->name }}
                    </option>
                @endforeach
            </select>

            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2">
                <input type="checkbox" name="alert_only" value="1"
                       {{ !empty($alertOnly) ? 'checked' : '' }}
                       class="rounded text-orange-500 focus:ring-orange-400">
                Alertes réappro uniquement
            </label>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'family_id', 'alert_only']))
                <a href="{{ route('stocks.seuils') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Info banner --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 flex items-start gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <strong>Stock min</strong> — déclenche l'alerte visuelle (badge « Stock bas »).
            <strong class="ml-2">Point réappro</strong> — déclenche l'alerte sur la page Alertes réappro (peut être supérieur au min).
            <strong class="ml-2">Stock max</strong> — quantité cible lors du réapprovisionnement.
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('stocks.seuils.update') }}" method="POST">
        @csrf

        @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm mb-4">
            ✓ {{ session('success') }}
        </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Article</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Stock actuel</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <span class="text-amber-600">Stock min</span>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <span class="text-orange-600">Point réappro</span>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <span class="text-emerald-600">Stock max</span>
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($products as $product)
                        @php
                            $totalQty       = $product->stocks->sum('quantity');
                            $totalReserved  = $product->stocks->sum('reserved_quantity');
                            $available      = $totalQty - $totalReserved;
                            $min            = $product->stock_min;
                            $max            = $product->stock_max;
                            $reorder        = $product->reorder_point;

                            if ($available <= 0) {
                                $statusClass = 'bg-red-100 text-red-700';
                                $statusLabel = '🛑 Rupture';
                            } elseif ($reorder && $available <= $reorder) {
                                $statusClass = 'bg-orange-100 text-orange-700';
                                $statusLabel = '⚠ Réappro';
                            } elseif ($min && $available <= $min) {
                                $statusClass = 'bg-amber-100 text-amber-700';
                                $statusLabel = '⚡ Stock bas';
                            } else {
                                $statusClass = 'bg-emerald-100 text-emerald-700';
                                $statusLabel = '✓ OK';
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('stocks.show', $product) }}" class="font-mono text-xs text-blue-700 hover:underline">{{ $product->reference }}</a>
                                <p class="text-sm text-gray-900 font-medium">{{ $product->name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $product->family?->name ?? '' }}
                                    @if($product->unit) · {{ $product->unit->abbreviation ?? $product->unit->name }} @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums hidden md:table-cell">
                                <span class="text-sm font-semibold {{ $available <= 0 ? 'text-red-600' : ($min && $available <= $min ? 'text-orange-600' : 'text-gray-800') }}">
                                    {{ number_format($available, 0, ',', ' ') }}
                                </span>
                                @if($totalReserved > 0)
                                <p class="text-xs text-gray-400">{{ number_format($totalReserved, 0, ',', ' ') }} rés.</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <input type="number" name="seuils[{{ $product->id }}][stock_min]"
                                       value="{{ $min !== null ? (int) $min : '' }}"
                                       min="0" step="1" placeholder="—"
                                       class="w-24 text-center border border-amber-200 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-400 focus:border-amber-400 bg-amber-50">
                            </td>
                            <td class="px-4 py-3 text-center">
                                <input type="number" name="seuils[{{ $product->id }}][reorder_point]"
                                       value="{{ $reorder !== null ? (int) $reorder : '' }}"
                                       min="0" step="1" placeholder="—"
                                       class="w-24 text-center border border-orange-200 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-orange-400 focus:border-orange-400 bg-orange-50">
                            </td>
                            <td class="px-4 py-3 text-center">
                                <input type="number" name="seuils[{{ $product->id }}][stock_max]"
                                       value="{{ $max !== null ? (int) $max : '' }}"
                                       min="0" step="1" placeholder="—"
                                       class="w-24 text-center border border-emerald-200 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 bg-emerald-50">
                            </td>
                            <td class="px-4 py-3 text-center hidden lg:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400 text-sm">
                                Aucun article stockable trouvé.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($products->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $products->appends(request()->query())->links() }}
            </div>
            @endif
        </div>

        @if($products->count() > 0)
        <div class="flex items-center justify-between pt-3">
            <p class="text-xs text-gray-500">
                {{ $products->total() }} article(s) · Les champs vides seront remis à zéro (aucune alerte).
            </p>
            <button type="submit"
                    class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Enregistrer les seuils
            </button>
        </div>
        @endif
    </form>

</div>
@endsection
