@extends('layouts.erp')
@section('title', 'Encaissement ' . $payment->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.encaissements.index') }}" class="hover:text-gray-700">Encaissements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $payment->number }}</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $payment->number }}</h1>
                @switch($payment->status)
                    @case('confirme')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Confirmé</span>
                        @break
                    @case('en_attente')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">En attente</span>
                        @break
                    @case('rejete')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Rejeté</span>
                        @break
                    @case('annule')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Annulé</span>
                        @break
                @endswitch
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Encaissement du {{ $payment->payment_date?->format('d/m/Y') }}
                — {{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="text-xs text-gray-500">Montant reçu</p>
                <p class="text-2xl font-bold text-green-700 tabular-nums">
                    {{ number_format($payment->amount, 0, ',', ' ') }} FCFA
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Info card --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Détails du paiement</h2>

            <dl class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500">Client</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $payment->client?->trade_name ?? $payment->client?->name ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Date</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Mode de paiement</dt>
                    <dd>
                        @if($payment->paymentMethod)
                            @php
                                $pmClass = match($payment->paymentMethod->type) {
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
                    </dd>
                </div>
                @if($payment->reference)
                <div>
                    <dt class="text-xs text-gray-500">Référence</dt>
                    <dd class="font-mono text-sm font-medium text-gray-900">{{ $payment->reference }}</dd>
                </div>
                @endif
                @if($payment->phone_number)
                <div>
                    <dt class="text-xs text-gray-500">N° téléphone</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->phone_number }}</dd>
                </div>
                @endif
                @if($payment->cashAccount)
                <div>
                    <dt class="text-xs text-gray-500">Compte de trésorerie</dt>
                    <dd class="font-medium text-gray-900">{{ $payment->cashAccount->name }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-500">Montant imputé</dt>
                    <dd class="font-semibold text-green-700 tabular-nums">{{ number_format($payment->allocated_amount, 0, ',', ' ') }} FCFA</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Montant non imputé</dt>
                    <dd class="font-semibold tabular-nums {{ $payment->unallocated_amount > 0 ? 'text-orange-600' : 'text-gray-400' }}">
                        {{ number_format($payment->unallocated_amount, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                @if($payment->notes)
                <div>
                    <dt class="text-xs text-gray-500">Notes</dt>
                    <dd class="text-sm text-gray-700">{{ $payment->notes }}</dd>
                </div>
                @endif
                <div>
                    <dt class="text-xs text-gray-500">Enregistré par</dt>
                    <dd class="text-sm text-gray-700">{{ $payment->createdBy?->name ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Allocations --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Factures imputées</h2>

            @if($payment->allocations->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            <th class="pb-2 text-left">Facture</th>
                            <th class="pb-2 text-left hidden md:table-cell">Date émission</th>
                            <th class="pb-2 text-right">Montant facture</th>
                            <th class="pb-2 text-right">Montant imputé</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($payment->allocations as $alloc)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 pr-4">
                                @if($alloc->invoice)
                                    <a href="{{ route('ventes.factures.show', $alloc->invoice) }}"
                                       class="font-mono font-semibold text-indigo-600 hover:text-indigo-800">
                                        {{ $alloc->invoice->number }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Facture supprimée</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-gray-600 hidden md:table-cell">
                                {{ $alloc->invoice?->issued_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="py-3 pr-4 text-right tabular-nums text-gray-700">
                                {{ number_format($alloc->invoice?->total_ttc ?? 0, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-3 text-right tabular-nums font-semibold text-green-700">
                                {{ number_format($alloc->amount, 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200">
                            <td colspan="3" class="pt-3 text-sm font-semibold text-gray-700 text-right pr-4">Total imputé :</td>
                            <td class="pt-3 text-right tabular-nums font-bold text-green-700">
                                {{ number_format($payment->allocations->sum('amount'), 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Summary bar --}}
            @php
                $allocated   = $payment->allocated_amount;
                $total       = $payment->amount;
                $pct         = $total > 0 ? min(100, round($allocated / $total * 100)) : 0;
            @endphp
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Imputation</span>
                    <span>{{ $pct }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                </div>
                <div class="flex justify-between text-xs mt-1">
                    <span class="text-green-700 font-medium">{{ number_format($allocated, 0, ',', ' ') }} FCFA imputés</span>
                    <span class="{{ $payment->unallocated_amount > 0 ? 'text-orange-600' : 'text-gray-400' }} font-medium">
                        {{ number_format($payment->unallocated_amount, 0, ',', ' ') }} FCFA non imputés
                    </span>
                </div>
            </div>

            @else
                <div class="py-12 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">Aucune imputation — ce paiement n'est pas encore lettrée sur une facture.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Back --}}
    <div>
        <a href="{{ route('tresorerie.encaissements.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour à la liste
        </a>
    </div>

</div>
@endsection
