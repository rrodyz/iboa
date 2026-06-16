@extends('layouts.erp')
@section('title', 'Valorisation du stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Valorisation</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Valorisation du stock</h1>
        <p class="text-xs text-gray-400">Calculé au {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Valeur totale du stock</p>
            <p class="text-3xl font-bold text-emerald-700 tabular-nums">{{ number_format($totalValue, 0, ',', ' ') }} FCFA</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Références valorisées</p>
            <p class="text-3xl font-bold text-gray-900">{{ $stocks->count() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Entrepôts</p>
            <p class="text-3xl font-bold text-gray-900">{{ $byWarehouse->count() }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <select name="warehouse_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                <option value="">Tous entrepôts</option>
                @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
            <select name="family_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                <option value="">Toutes familles</option>
                @foreach($families as $fam)
                <option value="{{ $fam->id }}" {{ $familyId == $fam->id ? 'selected' : '' }}>{{ $fam->name }}</option>
                @endforeach
            </select>
            <select name="method" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                <option value="">Toutes méthodes</option>
                <option value="cmp"  {{ $method === 'cmp'  ? 'selected' : '' }}>CMP (Coût moyen pondéré)</option>
                <option value="fifo" {{ $method === 'fifo' ? 'selected' : '' }}>FIFO</option>
                <option value="lifo" {{ $method === 'lifo' ? 'selected' : '' }}>LIFO</option>
            </select>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Filtrer</button>
            @if($warehouseId || $familyId || $method)
            <a href="{{ route('stocks.valuation') }}" class="border border-gray-300 text-gray-600 text-sm px-3 py-2 rounded-lg">✕</a>
            @endif
        </div>
    </form>

    {{-- By warehouse --}}
    @foreach($byWarehouse as $warehouseIdKey => $warehouseStocks)
    @php
        $wh = $warehouseStocks->first()->warehouse;
        $whTotal = $warehouseStocks->sum(fn($s) => (float)$s->quantity * (float)$s->avg_cost);
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 bg-gray-50">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <h2 class="font-semibold text-gray-900">{{ $wh?->name ?? 'Entrepôt #' . $warehouseIdKey }}</h2>
                <span class="text-xs text-gray-400">{{ $warehouseStocks->count() }} article(s)</span>
            </div>
            <span class="text-base font-bold text-emerald-700 tabular-nums">{{ number_format($whTotal, 0, ',', ' ') }} FCFA</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Référence</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Produit</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Famille</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Méthode</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Coût unitaire</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Valeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($warehouseStocks as $stock)
                    @php
                        $value = (float)$stock->quantity * (float)$stock->avg_cost;
                        $pct   = $whTotal > 0 ? round($value / $whTotal * 100, 1) : 0;
                        $methodLabel = match($stock->product?->valuation_method) {
                            'fifo' => 'FIFO',
                            'lifo' => 'LIFO',
                            default => 'CMP',
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-2.5">
                            <span class="font-mono text-xs text-gray-500">{{ $stock->product?->reference ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-2.5 font-medium text-gray-900">
                            <a href="{{ route('stocks.show', $stock->product_id) }}" class="hover:text-emerald-700 hover:underline">
                                {{ $stock->product?->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $stock->product?->family?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700">{{ $methodLabel }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-700">
                            {{ number_format((float)$stock->quantity, 2, ',', ' ') }}
                            <span class="text-xs text-gray-400">{{ $stock->product?->unit?->abbreviation }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-600">
                            {{ $stock->avg_cost ? number_format((float)$stock->avg_cost, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="tabular-nums font-semibold text-gray-900">{{ number_format($value, 0, ',', ' ') }}</span>
                            @if($pct > 0)
                            <span class="ml-1.5 text-xs text-gray-400">({{ $pct }}%)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        <td colspan="6" class="px-4 py-2.5 text-right text-xs font-bold text-gray-600 uppercase">Sous-total</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-bold text-emerald-700">{{ number_format($whTotal, 0, ',', ' ') }} FCFA</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endforeach

    @if($stocks->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-16 text-center text-gray-400">
        Aucun stock valorisé pour les filtres sélectionnés.
    </div>
    @else
    {{-- Grand total --}}
    <div class="flex justify-end">
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-6 py-4 text-right">
            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide mb-1">Valeur totale du stock</p>
            <p class="text-3xl font-bold text-emerald-800 tabular-nums">{{ number_format($totalValue, 0, ',', ' ') }} FCFA</p>
        </div>
    </div>
    @endif

</div>
@endsection
