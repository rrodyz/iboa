@extends('layouts.erp')
@section('title', 'Demande ' . $pr->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.demandes-achat.index') }}" class="hover:text-gray-700">Demandes d'achat</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $pr->number }}</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $pr->number }}</h1>
                @php $color = $pr->statusColor(); @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                    {{ $pr->statusLabel() }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Par {{ $pr->requestedBy?->name ?? '—' }}
                @if($pr->department) · {{ $pr->department }} @endif
                @if($pr->needed_at) · Date souhaitée : {{ $pr->needed_at->format('d/m/Y') }} @endif
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            {{-- Submit --}}
            @if($pr->canBeSubmitted())
            @can('purchase_requests.submit')
            <form action="{{ route('achats.demandes-achat.submit', $pr) }}" method="POST"
                  onsubmit="return confirm('Soumettre cette demande pour approbation ?')">
                @csrf
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center gap-1.5 transition-colors">
                    Soumettre
                </button>
            </form>
            @endcan
            @endif

            {{-- Approve / Reject --}}
            @if($pr->canBeApproved())
            @can('purchase_requests.approve')
            <form action="{{ route('achats.demandes-achat.approve', $pr) }}" method="POST"
                  onsubmit="return confirm('Approuver cette demande ?')">
                @csrf
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Approuver
                </button>
            </form>

            {{-- Reject modal trigger --}}
            <div x-data="{ open: false }">
                <button type="button" @click="open = true"
                        class="border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Rejeter
                </button>
                {{-- Reject modal --}}
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" style="display:none;">
                    <div @click.stop class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">Motif du rejet</h3>
                        <form action="{{ route('achats.demandes-achat.reject', $pr) }}" method="POST">
                            @csrf
                            <textarea name="reason" rows="3" required
                                      placeholder="Expliquer pourquoi la demande est rejetée..."
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 mb-4"></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-4 py-2 rounded-lg">
                                    Annuler
                                </button>
                                <button type="submit"
                                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                                    Confirmer le rejet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
            @endif

            {{-- Convert to PO --}}
            @if($pr->canBeConverted())
            @can('purchase_orders.create')
            <div x-data="{ open: false }">
                <button type="button" @click="open = true"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center gap-1.5 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Convertir en commande
                </button>

                {{-- Modal fournisseur --}}
                <div x-show="open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                    <div @click.stop class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900">Sélectionner un fournisseur</h3>
                        <p class="text-sm text-gray-500">Choisissez le fournisseur pour la commande à créer depuis <strong>{{ $pr->number }}</strong>.</p>
                        <form action="{{ route('achats.demandes-achat.convert', $pr) }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur <span class="text-red-500">*</span></label>
                                <select name="supplier_id" required
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="">— Sélectionner —</option>
                                    @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-4 py-2 rounded-lg">
                                    Annuler
                                </button>
                                <button type="submit"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                                    Créer la commande
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
            @endif

            {{-- Delete --}}
            @if($pr->isEditable())
            <form action="{{ route('achats.demandes-achat.destroy', $pr) }}" method="POST"
                  onsubmit="return confirm('Supprimer cette demande ?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="border border-gray-300 text-gray-600 hover:bg-red-50 hover:border-red-300 hover:text-red-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Supprimer
                </button>
            </form>
            @endif

            <a href="{{ route('achats.demandes-achat.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                ← Retour
            </a>
        </div>
    </div>

    {{-- Main content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Items --}}
        <div class="lg:col-span-2 space-y-5">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Articles demandés</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Article</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Qté</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Prix estimé</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($pr->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                @if($item->product)
                                <p class="font-medium text-gray-900">{{ $item->product->name }}</p>
                                <p class="text-xs text-gray-400">{{ $item->product->reference }}</p>
                                @else
                                <p class="text-gray-700">{{ $item->description ?: '—' }}</p>
                                @endif
                                @if($item->notes)
                                <p class="text-xs text-gray-400 mt-0.5">{{ $item->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                {{ number_format($item->quantity, 0, ',', ' ') }}
                                @if($item->unit) <span class="text-gray-400 text-xs">{{ $item->unit->abbreviation }}</span> @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-500">
                                {{ $item->estimated_price > 0 ? number_format($item->estimated_price, 0, ',', ' ') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900">
                                {{ $item->line_total > 0 ? number_format($item->line_total, 0, ',', ' ') : '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($pr->total_estimated > 0)
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <div class="text-sm font-bold text-gray-900">
                        Total estimé : {{ number_format($pr->total_estimated, 0, ',', ' ') }} FCFA
                    </div>
                </div>
                @endif
            </div>

            @if($pr->rejection_reason)
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-red-700 mb-1">Motif du rejet</h3>
                <p class="text-sm text-red-600">{{ $pr->rejection_reason }}</p>
            </div>
            @endif
        </div>

        {{-- Right panel --}}
        <div class="space-y-5">

            {{-- Converted to PO --}}
            @if($pr->purchaseOrder)
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-indigo-700 mb-2">Commande générée</h2>
                <a href="{{ route('achats.commandes.show', $pr->purchaseOrder) }}"
                   class="font-mono text-indigo-600 hover:underline font-semibold">
                    {{ $pr->purchaseOrder->number }}
                </a>
            </div>
            @endif

            {{-- Workflow --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Flux d'approbation</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ in_array($pr->status, ['brouillon','soumis','approuve','converti']) ? 'bg-amber-400' : 'bg-gray-200' }}"></div>
                        <span class="text-gray-700">Créé</span>
                        <span class="ml-auto text-gray-400 text-xs">{{ $pr->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ in_array($pr->status, ['soumis','approuve','converti']) ? 'bg-blue-400' : 'bg-gray-200' }}"></div>
                        <span class="text-gray-700">Soumis</span>
                        @if($pr->submitted_at)<span class="ml-auto text-gray-400 text-xs">{{ $pr->submitted_at->format('d/m/Y') }}</span>@endif
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ in_array($pr->status, ['approuve','converti']) ? 'bg-green-400' : ($pr->status === 'rejete' ? 'bg-red-400' : 'bg-gray-200') }}"></div>
                        <span class="text-gray-700">{{ $pr->status === 'rejete' ? 'Rejeté' : 'Approuvé' }}</span>
                        @if($pr->approved_at)<span class="ml-auto text-gray-400 text-xs">{{ $pr->approved_at->format('d/m/Y') }}</span>@endif
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $pr->status === 'converti' ? 'bg-indigo-400' : 'bg-gray-200' }}"></div>
                        <span class="text-gray-700">Converti en commande</span>
                    </div>
                </div>
            </div>

            @if($pr->notes)
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Notes</h2>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $pr->notes }}</p>
            </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Informations</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Demandeur</span>
                        <span class="text-gray-700">{{ $pr->requestedBy?->name ?? '—' }}</span>
                    </div>
                    @if($pr->approvedBy)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Approuvé par</span>
                        <span class="text-gray-700">{{ $pr->approvedBy->name }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-500">Créé le</span>
                        <span class="text-gray-700">{{ $pr->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pièces jointes --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <x-attachments.manager model="PurchaseRequest" :id="$pr->id" />
    </div>

</div>
@endsection
