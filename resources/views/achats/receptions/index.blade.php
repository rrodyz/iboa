@extends('layouts.erp')
@section('title', 'Réceptions')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.commandes.index') }}" class="hover:text-gray-700">Achats</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Réceptions</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Réceptions fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $receptions->total() }} réception(s)</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° réception…"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">

            <select name="supplier_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les fournisseurs</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>

            <select name="status"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                @foreach(['brouillon' => 'Brouillon', 'valide' => 'Validé', 'annule' => 'Annulé'] as $val => $label)
                    <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'supplier_id']))
                    <a href="{{ route('achats.receptions.index') }}"
                       class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">N° Réception</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fournisseur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">BC lié</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Date réception</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($receptions as $reception)
                        @php
                            [$badgeClass, $badgeLabel] = match($reception->status) {
                                'valide'  => ['bg-emerald-100 text-emerald-700', 'Validé'],
                                'annule'  => ['bg-red-100 text-red-700', 'Annulé'],
                                default   => ['bg-amber-100 text-amber-700', 'Brouillon'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 font-mono font-semibold text-gray-900">{{ $reception->number }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $reception->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 hidden md:table-cell font-mono text-xs">
                                {{ $reception->purchaseOrder?->number ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">
                                {{ $reception->received_at?->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('achats.receptions.show', $reception) }}"
                                   class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                    Voir →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-gray-400">
                                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                                    </svg>
                                    <p class="text-sm font-medium text-gray-500">Aucune réception trouvée</p>
                                    <p class="text-xs text-gray-400">Créez une réception depuis un bon de commande fournisseur.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($receptions->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $receptions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
