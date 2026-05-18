@extends('layouts.erp')
@section('title', 'Factures')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Factures</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Factures</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $invoices->total() }} facture(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('ventes.factures.index', array_merge(request()->query(), ['export' => 1])) }}"
               class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Excel
            </a>
            <a href="{{ route('ventes.factures.export-pdf', request()->query()) }}"
               class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                PDF
            </a>
            <a href="{{ route('ventes.factures.create') }}"
               class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle facture
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, client..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

            <select name="client_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">— Tous les clients —</option>
                @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ ($filters['client_id'] ?? '') == $c->id ? 'selected' : '' }}>
                    {{ $c->trade_name ?? $c->name }}
                </option>
                @endforeach
            </select>

            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon"           {{ ($filters['status'] ?? '') === 'brouillon'           ? 'selected' : '' }}>Brouillon</option>
                <option value="emise"               {{ ($filters['status'] ?? '') === 'emise'               ? 'selected' : '' }}>Émise</option>
                <option value="envoyee"             {{ ($filters['status'] ?? '') === 'envoyee'             ? 'selected' : '' }}>Envoyée</option>
                <option value="partiellement_payee" {{ ($filters['status'] ?? '') === 'partiellement_payee' ? 'selected' : '' }}>Partiellement payée</option>
                <option value="payee"               {{ ($filters['status'] ?? '') === 'payee'               ? 'selected' : '' }}>Payée</option>
                <option value="en_retard"           {{ ($filters['status'] ?? '') === 'en_retard'           ? 'selected' : '' }}>En retard</option>
                <option value="annulee"             {{ ($filters['status'] ?? '') === 'annulee'             ? 'selected' : '' }}>Annulée</option>
            </select>

            <select name="overdue" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Toutes</option>
                <option value="1" {{ ($filters['overdue'] ?? '') === '1' ? 'selected' : '' }}>En retard seulement</option>
            </select>

            {{-- [UX-3] Filtre par type de facture --}}
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">Tous les types</option>
                <option value="standard"   {{ ($filters['type'] ?? '') === 'standard'   ? 'selected' : '' }}>Standard</option>
                <option value="proforma"   {{ ($filters['type'] ?? '') === 'proforma'   ? 'selected' : '' }}>Proforma</option>
                <option value="acompte"    {{ ($filters['type'] ?? '') === 'acompte'    ? 'selected' : '' }}>Acompte</option>
                <option value="partielle"  {{ ($filters['type'] ?? '') === 'partielle'  ? 'selected' : '' }}>Partielle</option>
                <option value="recurrente" {{ ($filters['type'] ?? '') === 'recurrente' ? 'selected' : '' }}>Récurrente</option>
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" placeholder="Du"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" placeholder="Au"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

            <div class="flex gap-2 lg:col-span-2">
                <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search','status','type','overdue','client_id','date_from','date_to']))
                <a href="{{ route('ventes.factures.index') }}"
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
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Émission</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Échéance</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Montant TTC</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Reste à payer</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $invoice)
                    @php
                        $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                            && !in_array($invoice->status, ['payee', 'annulee']);
                        $statusBadges = [
                            'brouillon'           => 'bg-gray-100 text-gray-700',
                            'emise'               => 'bg-blue-100 text-blue-700',
                            'envoyee'             => 'bg-indigo-100 text-indigo-700',
                            'partiellement_payee' => 'bg-orange-100 text-orange-700',
                            'payee'               => 'bg-green-100 text-green-700',
                            'en_retard'           => 'bg-red-100 text-red-700',
                            'annulee'             => 'bg-red-100 text-red-700',
                        ];
                        $statusLabels = [
                            'brouillon'           => 'Brouillon',
                            'emise'               => 'Émise',
                            'envoyee'             => 'Envoyée',
                            'partiellement_payee' => 'Part. payée',
                            'payee'               => 'Payée',
                            'en_retard'           => 'En retard',
                            'annulee'             => 'Annulée',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors {{ $isOverdue ? 'bg-red-50/30' : '' }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('ventes.factures.show', $invoice) }}"
                               class="font-mono font-semibold text-indigo-600 hover:text-indigo-800">
                                {{ $invoice->number }}
                            </a>
                            {{-- [UX-3] Badge type — n'affiche pas "standard" (cas par défaut) --}}
                            @if($invoice->type && $invoice->type !== 'standard')
                                @php
                                    $typeBadges = [
                                        'proforma'   => 'bg-teal-100 text-teal-700',
                                        'acompte'    => 'bg-amber-100 text-amber-700',
                                        'partielle'  => 'bg-cyan-100 text-cyan-700',
                                        'recurrente' => 'bg-purple-100 text-purple-700',
                                    ];
                                    $typeLabels = [
                                        'proforma'   => 'Proforma',
                                        'acompte'    => 'Acompte',
                                        'partielle'  => 'Partielle',
                                        'recurrente' => 'Récurrente',
                                    ];
                                @endphp
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold {{ $typeBadges[$invoice->type] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $typeLabels[$invoice->type] ?? $invoice->type }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $invoice->client?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            @if($invoice->due_at)
                                <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $invoice->due_at->format('d/m/Y') }}
                                </span>
                                @if($isOverdue)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700">RETARD</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">
                            {{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums hidden lg:table-cell">
                            @if($invoice->remaining_amount > 0)
                                <span class="font-semibold text-red-600">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadges[$invoice->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('ventes.factures.show', $invoice) }}"
                                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('ventes.factures.pdf', $invoice) }}" target="_blank"
                                   class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="PDF">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </a>
                                @if($invoice->status === 'brouillon')
                                <a href="{{ route('ventes.factures.edit', $invoice) }}"
                                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors" title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('ventes.factures.validate', $invoice) }}" method="POST"
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
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune facture trouvée.</td>
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
