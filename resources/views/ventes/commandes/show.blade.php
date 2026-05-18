@extends('layouts.erp')
@section('title', 'Commande '.$order->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Commandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $order->number }}</span>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Workflow bar --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => 'commande',
        'quote'        => $order->quote ?? null,
        'order'        => $order,
        'deliveryNote' => $order->deliveryNotes->first() ?? null,
        'invoice'      => $order->invoices->first() ?? null,
    ])

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $order->number }}</h1>
                @switch($order->status)
                    @case('brouillon')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Brouillon</span>
                        @break
                    @case('confirme')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Confirmé</span>
                        @break
                    @case('en_preparation')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">En préparation</span>
                        @break
                    @case('partiellement_livre')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">Part. livré</span>
                        @break
                    @case('livre')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Livré</span>
                        @break
                    @case('facture')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Facturé</span>
                        @break
                    @case('annule')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Annulé</span>
                        @break
                @endswitch
                <span class="text-gray-500 text-sm">{{ $order->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                {{-- Brouillon: Confirmer + Modifier + Annuler --}}
                @if($order->status === 'brouillon')
                    <form action="{{ route('ventes.commandes.confirm', $order) }}" method="POST"
                          onsubmit="return confirm('Confirmer cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Confirmer
                        </button>
                    </form>
                    <a href="{{ route('ventes.commandes.edit', $order) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Modifier
                    </a>
                    <form action="{{ route('ventes.commandes.cancel', $order) }}" method="POST"
                          onsubmit="return confirm('Annuler cette commande ?')">
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

                {{-- Confirmé: Créer BL + Créer Facture + Annuler --}}
                @if($order->status === 'confirme')
                    <form action="{{ route('ventes.commandes.delivery-note', $order) }}" method="POST"
                          onsubmit="return confirm('Créer un bon de livraison depuis cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 text-white rounded-lg text-sm font-medium hover:bg-teal-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6"/>
                            </svg>
                            Créer BL
                        </button>
                    </form>
                    <form action="{{ route('ventes.commandes.invoice', $order) }}" method="POST"
                          onsubmit="return confirm('Créer une facture directe depuis cette commande ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Créer facture
                        </button>
                    </form>
                    <form action="{{ route('ventes.commandes.cancel', $order) }}" method="POST"
                          onsubmit="return confirm('Annuler cette commande ?')">
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

                {{-- En préparation / Partiellement livré: Créer BL supplémentaire + Créer Facture --}}
                @if(in_array($order->status, ['en_preparation', 'partiellement_livre']))
                    <form action="{{ route('ventes.commandes.delivery-note', $order) }}" method="POST"
                          onsubmit="return confirm('Créer un bon de livraison complémentaire ?')">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-teal-600 text-white rounded-lg text-sm font-medium hover:bg-teal-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12h12l1-12M10 12v6m4-6v6"/>
                            </svg>
                            Nouveau BL
                        </button>
                    </form>
                    <form action="{{ route('ventes.commandes.invoice', $order) }}" method="POST"
                          onsubmit="return confirm('Créer une facture depuis cette commande ?')">
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

                {{-- Livré: Créer Facture --}}
                @if($order->status === 'livre')
                    <form action="{{ route('ventes.commandes.invoice', $order) }}" method="POST"
                          onsubmit="return confirm('Créer une facture depuis cette commande ?')">
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

                <a href="{{ route('ventes.commandes.index') }}"
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
    @php
        $statusMapCmd = [
            'brouillon'           => ['label' => 'Brouillon',      'class' => 'bg-gray-100 text-gray-700'],
            'confirme'            => ['label' => 'Confirmé',       'class' => 'bg-blue-100 text-blue-700'],
            'en_preparation'      => ['label' => 'En préparation', 'class' => 'bg-yellow-100 text-yellow-700'],
            'partiellement_livre' => ['label' => 'Part. livré',    'class' => 'bg-orange-100 text-orange-700'],
            'livre'               => ['label' => 'Livré',          'class' => 'bg-green-100 text-green-700'],
            'facture'             => ['label' => 'Facturé',        'class' => 'bg-purple-100 text-purple-700'],
            'annule'              => ['label' => 'Annulé',         'class' => 'bg-red-100 text-red-700'],
        ];
    @endphp
    @include('partials._doc-letterhead', [
        'docType'   => 'COMMANDE',
        'docNumber' => $order->number,
        'docDate'   => $order->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusMapCmd[$order->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $order->client        ? ['label' => 'Client',            'value' => $order->client->name]                  : null,
            $order->delivery_date ? ['label' => 'Livraison prévue',  'value' => $order->delivery_date->format('d/m/Y')] : null,
        ])),
    ])

    {{-- 2 colonnes: info + résumé --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $order->client?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $order->number }}</dd>
                    @if($order->reference)
                    <dd class="text-gray-500 text-xs">Réf : {{ $order->reference }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date commande</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $order->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Livraison prévue</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $order->delivery_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                @if($order->delivery_address)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse livraison</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $order->delivery_address }}</dd>
                </div>
                @endif
                @if($order->quote)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Devis d'origine</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('ventes.devis.show', $order->quote) }}" class="text-blue-600 hover:underline font-mono">{{ $order->quote->number }}</a>
                    </dd>
                </div>
                @endif
                @if($order->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap">{{ $order->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="font-medium tabular-nums">{{ number_format($order->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($order->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($order->global_discount_amount > 0)
            <div class="flex justify-between text-sm text-gray-600">
                <span>Remise globale</span>
                <span class="font-medium tabular-nums text-orange-600">— {{ number_format($order->global_discount_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-blue-700 tabular-nums">{{ number_format($order->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($order->invoiced_amount > 0)
            <div class="flex justify-between text-sm text-gray-500 border-t border-gray-100 pt-2">
                <span>Déjà facturé</span>
                <span class="tabular-nums">{{ number_format($order->invoiced_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Lignes --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de commande</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Prix Unit.</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Remise%</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">TVA%</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total HT</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($order->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $item->description }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ $item->discount_percent > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 text-right text-gray-900 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bons de livraison liés --}}
    @if($order->deliveryNotes->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Bons de livraison</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->deliveryNotes as $dn)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-teal-600">{{ $dn->number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $dn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($dn->status === 'valide')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Validé</span>
                            @elseif($dn->status === 'livre')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Livré</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Brouillon</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('ventes.bons-livraison.show', $dn) }}" class="text-teal-600 hover:text-teal-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Factures liées --}}
    @if($order->invoices->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Factures</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant TTC</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($order->invoices as $invoice)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-semibold text-indigo-600">{{ $invoice->number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 text-center">
                            @switch($invoice->status)
                                @case('brouillon') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Brouillon</span> @break
                                @case('emise') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Émise</span> @break
                                @case('envoyee') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">Envoyée</span> @break
                                @case('partiellement_payee') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Part. payée</span> @break
                                @case('payee') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Payée</span> @break
                                @case('en_retard') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">En retard</span> @break
                                @case('annulee') <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-600">Annulée</span> @break
                                @default <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ $invoice->status }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('ventes.factures.show', $invoice) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif


</div>
@endsection
