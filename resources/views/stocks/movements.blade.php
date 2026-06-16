@extends('layouts.erp')
@section('title', 'Mouvements de stock')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Mouvements</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Mouvements de stock</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $movements->total() }} mouvements</p>
        </div>
        <a href="{{ route('stocks.movements', array_merge(request()->query(), ['export' => 1])) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-lg self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Exporter Excel
        </a>
        <a href="{{ route('stocks.movements-pdf', request()->query()) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium rounded-lg self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Exporter PDF
        </a>
        <a href="{{ route('stocks.movement.create') }}"
           class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau mouvement
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Produit, référence..."
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

            <select name="type"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">
                <option value="">Tous les types</option>
                <option value="entree"             {{ ($filters['type'] ?? '') === 'entree'             ? 'selected' : '' }}>Entrée</option>
                <option value="sortie"             {{ ($filters['type'] ?? '') === 'sortie'             ? 'selected' : '' }}>Sortie</option>
                <option value="transfert"          {{ ($filters['type'] ?? '') === 'transfert'          ? 'selected' : '' }}>Transfert</option>
                <option value="ajustement"         {{ ($filters['type'] ?? '') === 'ajustement'         ? 'selected' : '' }}>Ajustement</option>
                <option value="inventaire"         {{ ($filters['type'] ?? '') === 'inventaire'         ? 'selected' : '' }}>Inventaire</option>
                <option value="retour_client"      {{ ($filters['type'] ?? '') === 'retour_client'      ? 'selected' : '' }}>Retour client</option>
                <option value="retour_fournisseur" {{ ($filters['type'] ?? '') === 'retour_fournisseur' ? 'selected' : '' }}>Retour fournisseur</option>
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">

            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500">

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'warehouse_id', 'type', 'date_from', 'date_to', 'product_id']))
                <a href="{{ route('stocks.movements') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Entrepôt</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Quantité</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Coût unit.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden xl:table-cell">Référence</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden xl:table-cell">Créé par</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($movements as $movement)
                        @php
                            $qty       = (float) $movement->quantity;
                            $isInbound = in_array($movement->type, ['entree', 'retour_client']) || ($movement->type === 'ajustement' && $qty > 0);
                            $qtySign   = $isInbound ? '+' : '−';
                            $qtyClass  = $isInbound ? 'text-emerald-600 font-semibold' : 'text-red-600 font-semibold';

                            $typeBadge = match($movement->type) {
                                'entree'             => ['label' => 'Entrée',        'class' => 'bg-emerald-100 text-emerald-700'],
                                'sortie'             => ['label' => 'Sortie',        'class' => 'bg-red-100 text-red-700'],
                                'transfert'          => ['label' => 'Transfert',     'class' => 'bg-blue-100 text-blue-700'],
                                'ajustement'         => ['label' => 'Ajustement',    'class' => 'bg-teal-100 text-teal-700'],
                                'inventaire'         => ['label' => 'Inventaire',    'class' => 'bg-gray-100 text-gray-700'],
                                'retour_client'      => ['label' => 'Retour client', 'class' => 'bg-purple-100 text-purple-700'],
                                'retour_fournisseur' => ['label' => 'Retour fourn.', 'class' => 'bg-yellow-100 text-yellow-700'],
                                default              => ['label' => $movement->type, 'class' => 'bg-gray-100 text-gray-700'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $movement->occurred_at?->format('d/m/Y') ?? '—' }}
                                <p class="text-xs text-gray-400">{{ $movement->occurred_at?->format('H:i') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900">{{ $movement->product?->name ?? '—' }}</span>
                                @if($movement->product?->reference)
                                    <p class="text-xs text-gray-400 font-mono">{{ $movement->product->reference }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                {{ $movement->warehouse?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $typeBadge['class'] }}">
                                    {{ $typeBadge['label'] }}
                                </span>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $movement->reasonLabel() }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $qtyClass }}">
                                {{ $qtySign }}{{ number_format((float) $movement->quantity, 2, ',', ' ') }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-600 hidden lg:table-cell">
                                @if($movement->unit_cost)
                                    {{ number_format((float) $movement->unit_cost, 0, ',', ' ') }} FCFA
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs hidden xl:table-cell">
                                @if($movement->reference_type && $movement->reference_id)
                                    <span class="font-mono">{{ class_basename($movement->reference_type) }} #{{ $movement->reference_id }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 hidden xl:table-cell">
                                {{ $movement->createdBy?->name ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">
                                Aucun mouvement trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($movements->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $movements->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
