@extends('layouts.erp')
@section('title', 'Avoirs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Avoirs</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-5">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Total avoirs TTC</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_ttc']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Crédit restant</p>
            <p class="text-lg font-bold text-purple-600 tabular-nums">{{ $fmt($summary['remaining_credit']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">En attente</p>
            <p class="text-lg font-bold text-blue-600 tabular-nums">{{ $summary['count_pending'] }} <span class="text-xs font-normal text-gray-400">avoir(s)</span></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <p class="text-xs text-gray-500">Utilisés</p>
            <p class="text-lg font-bold text-emerald-600 tabular-nums">{{ $summary['count_used'] }} <span class="text-xs font-normal text-gray-400">avoir(s)</span></p>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Avoirs</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $creditNotes->total() }} avoir(s)</p>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" data-autosubmit class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="N° avoir, client..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"             {{ ($filters['status'] ?? '') === 'brouillon'             ? 'selected' : '' }}>Brouillon</option>
                <option value="en_attente_validation" {{ ($filters['status'] ?? '') === 'en_attente_validation' ? 'selected' : '' }}>⏳ En attente de validation</option>
                <option value="valide"                {{ ($filters['status'] ?? '') === 'valide'                ? 'selected' : '' }}>Validé</option>
                <option value="applique"  {{ ($filters['status'] ?? '') === 'applique'  ? 'selected' : '' }}>Appliqué</option>
                <option value="annule"    {{ ($filters['status'] ?? '') === 'annule'    ? 'selected' : '' }}>Annulé</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Filtrer</button>
                @if(request()->hasAny(['search','status']))
                <a href="{{ route('ventes.avoirs.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg">✕</a>
                @endif
            </div>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N° Avoir</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Facture liée</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant TTC</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Solde restant</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($creditNotes as $cn)
                    @php
                        $badges = [
                            'brouillon' => 'bg-gray-100 text-gray-600',
                            'valide'    => 'bg-purple-100 text-purple-700',
                            'applique'  => 'bg-green-100 text-green-700',
                            'annule'    => 'bg-red-100 text-red-600',
                        ];
                        $labels = ['brouillon' => 'Brouillon', 'valide' => 'Validé', 'applique' => 'Appliqué', 'annule' => 'Annulé'];
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-mono font-semibold text-gray-900">
                            <a href="{{ route('ventes.avoirs.show', $cn) }}" class="hover:text-purple-600">{{ $cn->number }}</a>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $cn->client?->name ?? '—' }}</td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            @if($cn->invoice)
                            <a href="{{ route('ventes.factures.show', $cn->invoice) }}" class="font-mono text-xs text-indigo-600 hover:text-indigo-800">
                                {{ $cn->invoice->number }}
                            </a>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs hidden lg:table-cell">{{ $cn->issued_at?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-purple-700">
                            {{ number_format($cn->total_ttc, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-sm hidden lg:table-cell
                            {{ $cn->remaining_credit > 0 ? 'text-orange-600 font-semibold' : 'text-gray-400' }}">
                            {{ number_format($cn->remaining_credit, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-center">
                            <x-workflow.status-badge :status="$cn->status" :label="$cn->status_label" size="sm" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('ventes.avoirs.show', $cn) }}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                <a href="{{ route('ventes.avoirs.pdf', $cn) }}" target="_blank" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded" title="PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun avoir trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($creditNotes->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $creditNotes->appends($filters)->links() }}</div>
        @endif
    </div>

</div>
@endsection
