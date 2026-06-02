@extends('layouts.erp')
@section('title', 'Commandes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Commandes</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-5">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Total TTC filtré</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_ttc']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">En cours</p>
            <p class="text-lg font-bold text-blue-600 tabular-nums">{{ $summary['count_confirmed'] }} <span class="text-xs font-normal text-gray-400">commande(s)</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Livrées</p>
            <p class="text-lg font-bold text-emerald-600 tabular-nums">{{ $summary['count_delivered'] }} <span class="text-xs font-normal text-gray-400">commande(s)</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Facturées</p>
            <p class="text-lg font-bold text-indigo-600 tabular-nums">{{ $summary['count_invoiced'] }} <span class="text-xs font-normal text-gray-400">commande(s)</span></p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Commandes</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $orders->total() }} commande(s)</p>
        </div>
        <a href="{{ route('ventes.commandes.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle commande
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" data-autosubmit class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, client..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="confirme"              {{ ($filters['status'] ?? '') === 'confirme'              ? 'selected' : '' }}>Confirmée</option>
                <option value="en_preparation"     {{ ($filters['status'] ?? '') === 'en_preparation'     ? 'selected' : '' }}>En préparation</option>
                <option value="partiellement_livre" {{ ($filters['status'] ?? '') === 'partiellement_livre' ? 'selected' : '' }}>Part. livrée</option>
                <option value="livre"              {{ ($filters['status'] ?? '') === 'livre'              ? 'selected' : '' }}>Livrée</option>
                <option value="facture"            {{ ($filters['status'] ?? '') === 'facture'            ? 'selected' : '' }}>Facturée</option>
                <option value="annule"             {{ ($filters['status'] ?? '') === 'annule'             ? 'selected' : '' }}>Annulée</option>
            </select>

            <input type="text" name="client_id" value="{{ $filters['client_id'] ?? '' }}" placeholder="ID Client"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hidden">

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search','status','client_id']))
                <a href="{{ route('ventes.commandes.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">
                    ✕
                </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Livraison prévue</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Montant TTC</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('ventes.commandes.show', $order) }}"
                               class="font-mono font-semibold text-blue-600 hover:text-blue-800">
                                {{ $order->number }}
                            </a>
                            @if($order->reference)
                            <p class="text-xs text-gray-400">{{ $order->reference }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900">{{ $order->client?->name ?? '—' }}</span>
                            @if($order->client?->trade_name)
                            <p class="text-xs text-gray-400">{{ $order->client->trade_name }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                            {{ $order->issued_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">
                            {{ $order->delivery_date?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">
                            {{ number_format($order->total_ttc, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-workflow.status-badge :status="$order->status" :label="$order->status_label" size="sm" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                {{-- Voir --}}
                                <a href="{{ route('ventes.commandes.show', $order) }}"
                                   class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- Modifier (draft ou confirmed) --}}
                                @if(in_array($order->status, ['brouillon', 'confirme']))
                                <a href="{{ route('ventes.commandes.edit', $order) }}"
                                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endif
                                {{-- Supprimer (draft seulement) --}}
                                @if($order->status === 'brouillon')
                                <form action="{{ route('ventes.commandes.destroy', $order) }}" method="POST"
                                      onsubmit="return confirm('Supprimer la commande {{ addslashes($order->number) }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucune commande trouvée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $orders->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
