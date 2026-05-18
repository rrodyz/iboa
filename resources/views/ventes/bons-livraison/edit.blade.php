@extends('layouts.erp')
@section('title', 'Modifier BL '.$deliveryNote->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-livraison.index') }}" class="hover:text-gray-700">Bons de livraison</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.bons-livraison.show', $deliveryNote) }}" class="hover:text-gray-700">{{ $deliveryNote->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Ajuster les quantités livrées</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                BL <span class="font-mono font-semibold">{{ $deliveryNote->number }}</span>
                — {{ $deliveryNote->client?->name }}
            </p>
        </div>
        <a href="{{ route('ventes.bons-livraison.show', $deliveryNote) }}"
           class="text-sm text-gray-500 hover:text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg">
            ← Retour
        </a>
    </div>

    {{-- Info banner --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
        <strong>Livraison partielle :</strong> Réduisez les quantités pour n'expédier qu'une partie de la commande.
        Un nouveau bon de livraison pourra être créé ultérieurement pour les quantités restantes.
        Les quantités ne peuvent pas dépasser les reliquats de la commande.
    </div>

    {{-- Validation errors --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-red-800">Veuillez corriger les erreurs suivantes :</p>
                <ul class="mt-1 text-sm text-red-700 list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    {{-- Form --}}
    <form action="{{ route('ventes.bons-livraison.update', $deliveryNote) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">Article</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide w-32">Qté commandée</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide w-32">Déjà livrée</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide w-36">Qté ce BL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($deliveryNote->items as $i => $item)
                    @php
                        $orderItem    = $item->orderItem;
                        $orderedQty   = $orderItem ? (float) $orderItem->quantity          : (float) $item->quantity;
                        $deliveredQty = $orderItem ? (float) $orderItem->delivered_quantity : 0;
                        $maxQty       = max(0, $orderedQty - $deliveredQty);
                    @endphp
                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900 text-sm">{{ $item->description }}</p>
                            @if($item->product?->reference)
                                <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $item->product->reference }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm text-gray-700 tabular-nums">
                            {{ number_format($orderedQty, 2, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums {{ $deliveredQty > 0 ? 'text-teal-600 font-medium' : 'text-gray-400' }}">
                            {{ number_format($deliveredQty, 2, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <input type="number"
                                   name="items[{{ $i }}][quantity]"
                                   value="{{ old("items.{$i}.quantity", $item->quantity) }}"
                                   min="0"
                                   max="{{ $maxQty }}"
                                   step="0.001"
                                   class="w-28 text-right rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500 @error("items.{$i}.quantity") border-red-400 @enderror"
                                   {{ $maxQty <= 0 ? 'disabled' : '' }}>
                            @if($maxQty <= 0)
                                <p class="text-xs text-gray-400 mt-0.5">Déjà livré</p>
                            @endif
                            @error("items.{$i}.quantity")
                                <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p>
                            @enderror
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('ventes.bons-livraison.show', $deliveryNote) }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                Annuler
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Enregistrer les quantités
            </button>
        </div>
    </form>

</div>
@endsection
