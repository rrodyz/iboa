@extends('layouts.erp')
@section('title', 'Factures fournisseurs')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Factures fournisseurs</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Factures fournisseurs</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $invoices->total() }} facture(s)</p>
        </div>
        <a href="{{ route('achats.factures-fournisseurs.create') }}"
           class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle facture
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, fournisseur..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">

            <select name="supplier_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les fournisseurs</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ ($filters['supplier_id'] ?? '') == $supplier->id ? 'selected' : '' }}>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                <option value="recue"               {{ ($filters['status'] ?? '') === 'recue'               ? 'selected' : '' }}>Reçue</option>
                <option value="validee"             {{ ($filters['status'] ?? '') === 'validee'             ? 'selected' : '' }}>Validée</option>
                <option value="en_litige"           {{ ($filters['status'] ?? '') === 'en_litige'           ? 'selected' : '' }}>En litige</option>
                <option value="partiellement_payee" {{ ($filters['status'] ?? '') === 'partiellement_payee' ? 'selected' : '' }}>Part. payée</option>
                <option value="payee"               {{ ($filters['status'] ?? '') === 'payee'               ? 'selected' : '' }}>Payée</option>
                <option value="annulee"             {{ ($filters['status'] ?? '') === 'annulee'             ? 'selected' : '' }}>Annulée</option>
            </select>

            <label class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="overdue" value="1" {{ !empty($filters['overdue']) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                <span class="text-gray-700">En retard</span>
            </label>

            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'supplier_id', 'status', 'overdue']))
                <a href="{{ route('achats.factures-fournisseurs.index') }}"
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
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fournisseur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Date émission</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Date échéance</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Montant TTC</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $invoice)
                    @php
                        $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                                     && !in_array($invoice->status, ['payee', 'annulee']);
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('achats.factures-fournisseurs.show', $invoice) }}"
                               class="font-mono font-semibold text-amber-600 hover:text-amber-800">
                                {{ $invoice->number }}
                            </a>
                            @if($invoice->supplier_invoice_number)
                            <p class="text-xs text-gray-400">Réf. fourn. : {{ $invoice->supplier_invoice_number }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-900">{{ $invoice->supplier?->name ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                            {{ $invoice->received_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="flex items-center gap-2">
                                <span class="{{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                    {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                                </span>
                                @if($isOverdue)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                    Retard
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">
                            {{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-center">
                            @switch($invoice->status)
                                @case('recue')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Reçue</span>
                                    @break
                                @case('validee')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Validée</span>
                                    @break
                                @case('en_litige')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">En litige</span>
                                    @break
                                @case('partiellement_payee')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Part. payée</span>
                                    @break
                                @case('payee')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Payée</span>
                                    @break
                                @case('annulee')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Annulée</span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ $invoice->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                {{-- Voir --}}
                                <a href="{{ route('achats.factures-fournisseurs.show', $invoice) }}"
                                   class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                {{-- Valider (recue seulement) --}}
                                @if($invoice->status === 'recue')
                                <form action="{{ route('achats.factures-fournisseurs.validate', $invoice) }}" method="POST"
                                      onsubmit="return confirm('Valider la facture {{ addslashes($invoice->number) }} ?')">
                                    @csrf
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Valider">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                                {{-- Modifier (recue seulement) --}}
                                @if($invoice->status === 'recue')
                                <a href="{{ route('achats.factures-fournisseurs.edit', $invoice) }}"
                                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                @endif
                                {{-- Supprimer (recue seulement) --}}
                                @if($invoice->status === 'recue')
                                <form action="{{ route('achats.factures-fournisseurs.destroy', $invoice) }}" method="POST"
                                      onsubmit="return confirm('Supprimer la facture {{ addslashes($invoice->number) }} ?')">
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
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucun résultat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($invoices->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $invoices->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
