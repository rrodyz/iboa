@extends('layouts.erp')
@section('title', 'BL '.$deliveryNote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-livraison.index') }}" class="hover:text-gray-700">Bons de livraison</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $deliveryNote->number }}</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Workflow bar --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => 'livraison',
        'quote'        => $deliveryNote->order?->quote ?? null,
        'order'        => $deliveryNote->order ?? null,
        'deliveryNote' => $deliveryNote,
        'invoice'      => $deliveryNote->invoices->first() ?? ($deliveryNote->order?->invoices->first() ?? null),
    ])

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $deliveryNote->number }}</h1>
                @switch($deliveryNote->status)
                    @case('brouillon')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Brouillon</span>
                        @break
                    @case('valide')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Validé</span>
                        @break
                    @case('livre')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Livré</span>
                        @break
                    @case('annule')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Annulé</span>
                        @break
                @endswitch
                <span class="text-gray-500 text-sm">{{ $deliveryNote->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                {{-- PDF (toujours disponible) --}}
                <a href="{{ route('ventes.bons-livraison.pdf', $deliveryNote) }}?preview=1" target="_blank"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>
                <a href="{{ route('ventes.bons-livraison.pdf', $deliveryNote) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    PDF
                </a>

                {{-- Brouillon: Modifier + Valider + Annuler --}}
                @if($deliveryNote->status === 'brouillon')
                    <a href="{{ route('ventes.bons-livraison.edit', $deliveryNote) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 border border-blue-300 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Modifier les quantités
                    </a>
                    <form action="{{ route('ventes.bons-livraison.validate', $deliveryNote) }}" method="POST"
                          onsubmit="return confirm('Valider ce bon de livraison ? Le stock sera décrémenté.')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 text-white rounded-lg text-sm font-medium hover:bg-teal-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Valider la livraison
                        </button>
                    </form>
                    <form action="{{ route('ventes.bons-livraison.cancel', $deliveryNote) }}" method="POST"
                          onsubmit="return confirm('Annuler ce bon de livraison ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 border border-red-300 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Annuler
                        </button>
                    </form>
                @endif

                {{-- Validé: Créer Facture --}}
                @if($deliveryNote->status === 'valide')
                    <form action="{{ route('ventes.bons-livraison.invoice', $deliveryNote) }}" method="POST"
                          onsubmit="return confirm('Créer une facture depuis ce bon de livraison ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Créer facture
                        </button>
                    </form>
                @endif

                <a href="{{ route('ventes.bons-livraison.index') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- Letterhead : logo + infos société + badge document --}}
    @include('partials._doc-letterhead', [
        'docType'   => 'BON DE LIVRAISON',
        'docNumber' => $deliveryNote->number,
        'docDate'   => $deliveryNote->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $deliveryNote->status === 'valide'
                        ? ['label' => 'Validé',   'class' => 'bg-green-100 text-green-700']
                        : ['label' => 'Brouillon','class' => 'bg-gray-100 text-gray-700'],
        'docExtra'  => array_values(array_filter([
            $deliveryNote->client    ? ['label' => 'Client',      'value' => $deliveryNote->client->name]          : null,
            $deliveryNote->warehouse ? ['label' => 'Entrepôt',    'value' => $deliveryNote->warehouse->name]       : null,
            $deliveryNote->order     ? ['label' => 'Commande',    'value' => $deliveryNote->order->number]         : null,
        ])),
    ])

    {{-- Info card --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $deliveryNote->client?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $deliveryNote->number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Entrepôt</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->warehouse?->name ?? '—' }}</dd>
                </div>
                @if($deliveryNote->delivery_address)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse livraison</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->delivery_address }}</dd>
                </div>
                @endif
                @if($deliveryNote->carrier)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Transporteur</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $deliveryNote->carrier }}</dd>
                </div>
                @endif
                @if($deliveryNote->tracking_number)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">N° suivi</dt>
                    <dd class="mt-0.5 text-gray-700 font-mono">{{ $deliveryNote->tracking_number }}</dd>
                </div>
                @endif
                @if($deliveryNote->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $deliveryNote->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 h-fit space-y-3">
            <h2 class="text-base font-semibold text-gray-900">Quantités</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total articles</span>
                <span class="font-semibold text-gray-900">{{ $deliveryNote->items->count() }} ligne(s)</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total quantité</span>
                <span class="font-semibold text-gray-900">{{ number_format($deliveryNote->total_quantity, 2, ',', ' ') }}</span>
            </div>
        </div>
    </div>

    {{-- Articles livrés --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Articles livrés</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Réf. produit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">N° Lot</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deliveryNote->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 text-gray-900">
                            {{ $item->description }}
                            @if($item->expiry_date)
                                <p class="text-xs text-orange-500">Exp. : {{ $item->expiry_date->format('d/m/Y') }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs font-mono hidden md:table-cell">{{ $item->product?->reference ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs hidden lg:table-cell">{{ $item->lot_number ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
