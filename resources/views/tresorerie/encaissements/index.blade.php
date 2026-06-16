@extends('layouts.erp')
@section('title', 'Encaissements clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.index') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Encaissements</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
<div class="space-y-5">

    {{-- KPI summary bar --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-500">Total encaissé (filtré)</p>
            <p class="text-lg font-bold text-emerald-600 tabular-nums">{{ $fmt($summary['total_amount']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-500">Ce mois-ci</p>
            <p class="text-lg font-bold text-blue-600 tabular-nums">{{ $fmt($summary['this_month']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-500">Nombre d'encaissements</p>
            <p class="text-lg font-bold text-gray-900 tabular-nums">{{ $summary['count'] }}</p>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Encaissements clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $payments->total() }} encaissement(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="{{ route('tresorerie.encaissements.index', array_merge(request()->query(), ['export' => 1])) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('tresorerie.encaissements.export-pdf', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('tresorerie.encaissements.create') }}"
               class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau paiement
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4" data-autosubmit>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Réf, client..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 lg:col-span-2">

            <select name="client_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Tous les clients</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ ($filters['client_id'] ?? '') == $client->id ? 'selected' : '' }}>
                        {{ $client->trade_name ?? $client->name }}
                    </option>
                @endforeach
            </select>

            <select name="payment_method_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Tous les modes</option>
                @foreach($paymentMethods as $pm)
                    <option value="{{ $pm->id }}" {{ ($filters['payment_method_id'] ?? '') == $pm->id ? 'selected' : '' }}>
                        {{ $pm->name }}
                    </option>
                @endforeach
            </select>

            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div class="flex gap-2 mt-3">
            <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                Filtrer
            </button>
            @if(count($filters) > 0)
            <a href="{{ route('tresorerie.encaissements.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">
                ✕ Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Montant</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Mode paiement</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Référence</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Factures imputées</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <span class="font-mono font-semibold text-green-700">{{ $payment->number }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            {{ $payment->payment_date?->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-green-700">
                            {{ number_format($payment->amount, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            @if($payment->paymentMethod)
                                @php
                                    $pmType = $payment->paymentMethod->type;
                                    $pmClass = match($pmType) {
                                        'especes'      => 'bg-gray-100 text-gray-700',
                                        'virement'     => 'bg-blue-100 text-blue-700',
                                        'cheque'       => 'bg-indigo-100 text-indigo-700',
                                        'mobile_money' => 'bg-purple-100 text-purple-700',
                                        default        => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $pmClass }}">
                                    {{ $payment->paymentMethod->name }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 hidden lg:table-cell font-mono text-xs">
                            {{ $payment->reference ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center hidden lg:table-cell">
                            @if($payment->allocations->count() > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    {{ $payment->allocations->count() }} facture(s)
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">Non imputé</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @switch($payment->status)
                                @case('confirme')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Confirmé</span>
                                    @break
                                @case('en_attente')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">En attente</span>
                                    @break
                                @case('rejete')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Rejeté</span>
                                    @break
                                @case('annule')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Annulé</span>
                                    @break
                                @default
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ $payment->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('tresorerie.encaissements.show', $payment) }}"
                               class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors inline-flex" title="Voir">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400 text-sm">
                            Aucun encaissement trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $payments->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
