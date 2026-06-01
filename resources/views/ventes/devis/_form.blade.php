@php
    $quote            ??= null;
    $selectedClient   ??= null;
    $clientExemptions ??= [];
@endphp

{{-- PHP → JS : données brutes utilisées par Alpine --}}
<script>
window._quoteFormData = {
    quote:             @json($quote ? $quote->load('items') : null),
    products:          @json($products ?? []),
    clients:           @json($clients ?? []),
    oldItems:          @json(old('items', [])),
    oldGlobalDiscount: @json(old('global_discount_amount', 0)),
    oldGlobalPct:      @json(old('global_discount_percent', 0)),
    selectedClientId:  @json(old('client_id', $quote?->client_id ?? $selectedClient)),
    clientExemptions:  @json($clientExemptions),
};
</script>

<div x-data="quoteForm()" x-ref="root" x-cloak>

{{-- ── Bannière erreurs ────────────────────────────────────────────────── --}}
<x-validation-errors />

{{-- ══════════════════════════════════════════════════════════════════════
     Section 1 — Informations générales
════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
    <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Client --}}
        <div class="lg:col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">
                Client <span class="text-red-500">*</span>
            </label>
            <select name="client_id" x-model="client_id" @change="onClientChange()" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('client_id') ? 'border-red-400 bg-red-50' : '' }}">
                <option value="">Sélectionner un client...</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}"
                        {{ old('client_id', $quote?->client_id ?? $selectedClient) == $client->id ? 'selected' : '' }}>
                        {{ $client->trade_name ?? $client->name }}
                    </option>
                @endforeach
            </select>
            @error('client_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            {{-- Badge exonération TVA --}}
            <div x-show="isClientTaxExempt"
                 x-cloak
                 class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2.5 py-1 w-fit">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Client exonéré de TVA — TVA forcée à 0% sur toutes les lignes
            </div>
        </div>

        {{-- Date d'émission --}}
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                Date d'émission <span class="text-red-500">*</span>
            </label>
            <input type="date" name="issued_at" required
                   value="{{ old('issued_at', $quote?->issued_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('issued_at') ? 'border-red-400 bg-red-50' : '' }}">
            @error('issued_at')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Date de validité + badge --}}
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1 flex items-center gap-2">
                Date de validité
                <span x-show="validityLabel"
                      x-text="validityLabel?.text"
                      :class="validityLabel?.cls"
                      class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium">
                </span>
            </label>
            <input type="date" name="expires_at" x-model="expires_at"
                   value="{{ old('expires_at', $quote?->expires_at?->format('Y-m-d')) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ $errors->has('expires_at') ? 'border-red-400 bg-red-50' : '' }}">
            @error('expires_at')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Référence --}}
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Référence interne</label>
            <input type="text" name="reference" maxlength="50"
                   value="{{ old('reference', $quote?->reference) }}"
                   placeholder="Ex: REF-2024-001"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Notes --}}
        <div class="lg:col-span-3">
            <label class="block text-xs font-medium text-gray-700 mb-1">Notes / Conditions</label>
            <textarea name="notes" rows="2"
                      placeholder="Notes visibles sur le devis, conditions de paiement..."
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $quote?->notes) }}</textarea>
        </div>

    </div>

    {{-- ── Carte client (apparaît dès qu'un client est sélectionné) ─────── --}}
    <div x-show="selectedClient" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 flex flex-wrap items-start gap-4">

        {{-- Identité --}}
        <div class="flex items-center gap-2 min-w-0">
            <div class="w-8 h-8 rounded-full bg-blue-200 text-blue-700 flex items-center justify-center font-bold text-sm flex-shrink-0"
                 x-text="selectedClient ? (selectedClient.trade_name || selectedClient.name || '?')[0].toUpperCase() : ''"></div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-blue-900 truncate"
                   x-text="selectedClient?.trade_name || selectedClient?.name"></p>
                <p x-show="selectedClient?.city" class="text-xs text-blue-600"
                   x-text="selectedClient?.city"></p>
            </div>
        </div>

        {{-- Contacts --}}
        <div class="flex flex-wrap gap-3 text-xs text-blue-700">
            <span x-show="selectedClient?.phone" class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <span x-text="selectedClient?.phone"></span>
            </span>
            <span x-show="selectedClient?.email" class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span x-text="selectedClient?.email"></span>
            </span>
            <span x-show="selectedClient?.payment_terms" class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span x-text="selectedClient?.payment_terms"></span>
            </span>
        </div>

        {{-- Suggestion remise par défaut --}}
        <div x-show="selectedClient?.default_discount > 0"
             class="ml-auto flex items-center gap-2 bg-white border border-amber-200 rounded-lg px-3 py-1.5 text-xs">
            <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 10V5a2 2 0 012-2z"/>
            </svg>
            <span class="text-amber-700">
                Remise par défaut :
                <strong x-text="selectedClient?.default_discount + ' %'"></strong>
            </span>
            <button type="button" @click="applyClientDiscount()"
                    class="ml-1 bg-amber-100 hover:bg-amber-200 text-amber-800 font-medium rounded px-2 py-0.5 transition-colors">
                Appliquer
            </button>
        </div>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     Section 2 — Lignes du devis
════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-4">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">Lignes du devis</h2>
        <span class="text-xs text-gray-400" x-text="items.length + ' ligne(s)'"></span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase w-8">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase w-44">Produit</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-20">Qté</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-32">Prix unit. HT</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-20">Rem. %</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-20">TVA %</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-28">Total HT</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-28">Total TTC</th>
                    <th class="px-3 py-2.5 w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="(item, index) in items" :key="item._key">
                    <tr class="hover:bg-gray-50 group">
                        <td class="px-3 py-2 text-gray-400 text-xs" x-text="index + 1"></td>

                        {{-- Description --}}
                        <td class="px-3 py-2">
                            <input type="text" :name="'items[' + index + '][description]'"
                                   x-model="item.description"
                                   placeholder="Description de la prestation..."
                                   :class="item.description.trim() === '' && submitted ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                   class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 min-w-[180px]">
                        </td>

                        {{-- Produit — Combobox avec recherche (partial reutilisable) --}}
                        @include('ventes.partials._product_combobox', ['accentColor' => 'blue', 'formName' => 'quote'])

                        {{-- Quantité --}}
                        <td class="px-3 py-2">
                            <input type="number" :name="'items[' + index + '][quantity]'"
                                   x-model.number="item.quantity"
                                   min="1" step="1" inputmode="numeric"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </td>

                        {{-- Prix unitaire --}}
                        <td class="px-3 py-2">
                            <input type="number" :name="'items[' + index + '][unit_price]'"
                                   x-model.number="item.unit_price"
                                   min="0" step="1"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </td>

                        {{-- Remise % --}}
                        <td class="px-3 py-2">
                            <input type="number" :name="'items[' + index + '][discount_percent]'"
                                   x-model.number="item.discount_percent"
                                   min="0" max="100" step="1" inputmode="numeric"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </td>

                        {{-- TVA % --}}
                        <td class="px-3 py-2">
                            <input type="number" :name="'items[' + index + '][tax_rate_value]'"
                                   x-model.number="item.tax_rate_value"
                                   min="0" max="100" step="1" inputmode="numeric"
                                   class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </td>

                        {{-- Total HT --}}
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700 font-medium text-xs whitespace-nowrap"
                            x-text="formatFcfa(lineHt(item))"></td>

                        {{-- Total TTC --}}
                        <td class="px-3 py-2 text-right tabular-nums text-gray-900 font-semibold text-xs whitespace-nowrap"
                            x-text="formatFcfa(lineTtc(item))"></td>

                        {{-- Actions ligne --}}
                        <td class="px-2 py-2">
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                {{-- Dupliquer --}}
                                <button type="button" @click="duplicateItem(index)"
                                        title="Dupliquer cette ligne"
                                        class="text-gray-300 hover:text-blue-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                                {{-- Supprimer --}}
                                <button type="button" @click="removeItem(index)"
                                        x-show="items.length > 1"
                                        title="Supprimer cette ligne"
                                        class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="px-5 py-3 border-t border-gray-100">
        <button type="button" @click="addItem()"
                class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Ajouter une ligne
        </button>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     Section 3 — Conditions & Mentions (collapsible)
════════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl border border-gray-200 mt-4" x-data="{ open: {{ ($quote?->terms || $quote?->footer_note || old('terms') || old('footer_note')) ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
            class="w-full flex items-center justify-between px-5 py-3.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors rounded-xl">
        <span class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Conditions générales &amp; Mentions
        </span>
        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-collapse class="px-5 pb-5 grid grid-cols-1 lg:grid-cols-2 gap-4 border-t border-gray-100 pt-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                Conditions générales de vente
                <span class="font-normal text-gray-400">(visibles sur le PDF)</span>
            </label>
            <textarea name="terms" rows="4"
                      placeholder="Ex : Paiement à 30 jours. Tout retard de paiement entraîne des pénalités..."
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('terms', $quote?->terms) }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">
                Mention pied de page
                <span class="font-normal text-gray-400">(bas du PDF)</span>
            </label>
            <textarea name="footer_note" rows="4"
                      placeholder="Ex : Merci de votre confiance. Capital social : 10 000 000 FCFA..."
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('footer_note', $quote?->footer_note) }}</textarea>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     Section 4 — Récapitulatif
════════════════════════════════════════════════════════════════════════ --}}
<div class="mt-4 flex flex-col sm:flex-row sm:justify-end gap-4">

    {{-- Résumé lignes (gauche) --}}
    <div class="flex-1 sm:max-w-xs bg-gray-50 rounded-xl border border-gray-100 p-4 text-xs text-gray-500 space-y-1.5 self-end">
        <p class="font-medium text-gray-700 text-sm mb-2">Résumé</p>
        <div class="flex justify-between">
            <span x-text="items.length + ' ligne(s)'"></span>
            <span class="tabular-nums" x-text="formatFcfa(subtotalHt) + ' HT'"></span>
        </div>
        <div x-show="totalLineDiscounts > 0" class="flex justify-between text-green-600">
            <span>Économies sur lignes</span>
            <span class="tabular-nums" x-text="'−' + formatFcfa(totalLineDiscounts)"></span>
        </div>
        <div class="flex justify-between">
            <span>TVA</span>
            <span class="tabular-nums" x-text="formatFcfa(totalTax)"></span>
        </div>
        <div x-show="effectiveGlobalDiscount > 0" class="flex justify-between text-orange-600">
            <span>Remise globale</span>
            <span class="tabular-nums" x-text="'−' + formatFcfa(effectiveGlobalDiscount)"></span>
        </div>
    </div>

    {{-- Total (droite) --}}
    <div class="w-full sm:w-80 bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <div class="flex justify-between text-sm text-gray-600">
            <span>Sous-total HT</span>
            <span class="tabular-nums font-medium" x-text="formatFcfa(subtotalHt)"></span>
        </div>
        <div class="flex justify-between text-sm text-gray-600">
            <span>Total TVA</span>
            <span class="tabular-nums font-medium" x-text="formatFcfa(totalTax)"></span>
        </div>

        {{-- Remise globale avec toggle % / FCFA --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm text-gray-600 whitespace-nowrap">Remise globale</span>
                {{-- Toggle --}}
                <div class="flex rounded-lg border border-gray-200 text-xs overflow-hidden">
                    <button type="button"
                            @click="discountMode = 'amount'"
                            :class="discountMode === 'amount' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                            class="px-2.5 py-1 font-medium transition-colors">
                        FCFA
                    </button>
                    <button type="button"
                            @click="discountMode = 'percent'"
                            :class="discountMode === 'percent' ? 'bg-blue-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'"
                            class="px-2.5 py-1 font-medium transition-colors border-l border-gray-200">
                        %
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Champ montant (UX uniquement, pas de name — soumis via hidden) --}}
                <div x-show="discountMode === 'amount'" class="flex-1">
                    <input type="number" id="global_discount_amount"
                           x-model.number="global_discount_amount"
                           min="0" step="1"
                           :max="subtotalHt + totalTax"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                {{-- Champ pourcentage (UX uniquement, pas de name — soumis via hidden) --}}
                <div x-show="discountMode === 'percent'" class="flex-1">
                    <div class="relative">
                        <input type="number" id="global_discount_percent"
                               x-model.number="global_discount_percent"
                               min="0" max="100" step="0.5"
                               class="w-full border border-gray-300 rounded px-2 py-1.5 pr-6 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none">%</span>
                    </div>
                </div>
                {{-- Montant calculé (en mode %) --}}
                <span x-show="discountMode === 'percent' && global_discount_percent > 0"
                      class="text-xs text-gray-400 whitespace-nowrap tabular-nums"
                      x-text="'= ' + formatFcfa(effectiveGlobalDiscount)"></span>
            </div>
            {{-- Hidden : valeurs soumises au serveur (toujours présentes) --}}
            <input type="hidden" name="global_discount_amount"  :value="effectiveGlobalDiscount">
            <input type="hidden" name="global_discount_percent" :value="discountMode === 'percent' ? global_discount_percent : 0">
        </div>

        <p x-show="effectiveGlobalDiscount > subtotalHt + totalTax"
           class="text-xs text-red-500 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            La remise dépasse le total — le montant final sera ramené à 0.
        </p>

        <div class="border-t border-gray-200 pt-3 flex justify-between items-center">
            <span class="text-base font-bold text-gray-900">Total TTC</span>
            <span class="text-xl font-bold tabular-nums"
                  :class="totalTtc > 0 ? 'text-blue-700' : 'text-gray-400'"
                  x-text="formatFcfa(totalTtc)"></span>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     Boutons d'action
════════════════════════════════════════════════════════════════════════ --}}
<div class="mt-4 flex items-center justify-end gap-3">
    <a href="{{ route('ventes.devis.index') }}"
       class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
        Annuler
    </a>
    <button type="submit"
            @click="submitted = true"
            :disabled="submitting || totalTtc < 0"
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
        <svg x-show="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <span x-text="submitting ? 'Enregistrement...' : '{{ $quote ? 'Mettre à jour' : 'Enregistrer le devis' }}'"></span>
    </button>
</div>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function quoteForm() {
    const { quote, products, clients, oldItems, oldGlobalDiscount, oldGlobalPct, selectedClientId, clientExemptions } = window._quoteFormData;

    // ── Map clients pour accès rapide par ID
    const clientsMap = {};
    clients.forEach(c => { clientsMap[String(c.id)] = c; });

    // ── Item initialisation priority:
    //    1. Existing quote (edit mode)
    //    2. old() items from failed validation (create mode)
    //    3. Default blank item
    let nextKey = 1;

    function mapItem(i) {
        return {
            _key:             nextKey++,
            product_id:       i.product_id        ?? '',
            description:      i.description       ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            // [TVA-FIX] ?? 0 au lieu de || 18 : 0% ne doit pas devenir 18%
            tax_rate_value:   i.tax_rate_value != null ? parseFloat(i.tax_rate_value) : 0,
            // [PRODUCT-SEARCH] État du combobox de recherche produit
            _ps_open:   false,
            _ps_search: '',
            _ps_rect:   null,
        };
    }

    let initialItems;
    if (quote && quote.items && quote.items.length) {
        initialItems = quote.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        // [TVA-FIX] Défaut à 0% — l'utilisateur choisit explicitement
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 0 })];
    }

    const initialDiscount = quote
        ? (parseFloat(quote.global_discount_amount) || 0)
        : (parseFloat(oldGlobalDiscount) || 0);

    const initialPct = quote
        ? (parseFloat(quote.global_discount_percent) || 0)
        : (parseFloat(oldGlobalPct) || 0);

    // Détermine le mode initial
    const initialMode = initialPct > 0 ? 'percent' : 'amount';

    return {
        items:                   initialItems,
        client_id:               String(selectedClientId || ''),
        expires_at:              '{{ old('expires_at', $quote?->expires_at?->format('Y-m-d') ?? '') }}',
        global_discount_amount:  initialDiscount,
        global_discount_percent: initialPct,
        discountMode:            initialMode,
        products,
        clientsMap,
        submitting:  false,
        submitted:   false,
        _nextKey:    nextKey,

        // ── Computed : client sélectionné ─────────────────────────────────
        get selectedClient() {
            return this.client_id ? this.clientsMap[String(this.client_id)] || null : null;
        },

        // ── Computed : badge validité ──────────────────────────────────────
        get validityLabel() {
            if (!this.expires_at) return null;
            const diff = Math.ceil((new Date(this.expires_at) - new Date()) / 86400000);
            if (diff < 0)  return { text: 'Expiré',              cls: 'text-red-700 bg-red-100' };
            if (diff === 0) return { text: 'Expire aujourd\'hui', cls: 'text-orange-700 bg-orange-100' };
            if (diff <= 7)  return { text: 'Dans ' + diff + 'j', cls: 'text-orange-600 bg-orange-100' };
            if (diff <= 30) return { text: 'Dans ' + diff + 'j', cls: 'text-amber-600 bg-amber-100' };
            return { text: 'Dans ' + diff + 'j', cls: 'text-green-700 bg-green-100' };
        },

        // ── Computed : totaux ──────────────────────────────────────────────
        get subtotalHt() {
            return this.items.reduce((sum, i) => {
                return sum + Math.round(i.quantity * i.unit_price * (1 - i.discount_percent / 100));
            }, 0);
        },

        get totalTax() {
            return this.items.reduce((sum, i) => {
                const ht = i.quantity * i.unit_price * (1 - i.discount_percent / 100);
                return sum + Math.round(ht * i.tax_rate_value / 100);
            }, 0);
        },

        get totalLineDiscounts() {
            return this.items.reduce((sum, i) => {
                return sum + Math.round(i.quantity * i.unit_price * i.discount_percent / 100);
            }, 0);
        },

        // Remise globale effective selon le mode
        get effectiveGlobalDiscount() {
            if (this.discountMode === 'percent') {
                return Math.round(this.subtotalHt * (this.global_discount_percent || 0) / 100);
            }
            return this.global_discount_amount || 0;
        },

        get totalTtc() {
            return Math.max(0, this.subtotalHt + this.totalTax - this.effectiveGlobalDiscount);
        },

        // ── Per-line helpers ───────────────────────────────────────────────
        lineHt(item) {
            return Math.round(item.quantity * item.unit_price * (1 - item.discount_percent / 100));
        },

        lineTtc(item) {
            return Math.round(this.lineHt(item) * (1 + item.tax_rate_value / 100));
        },

        // ── Actions ───────────────────────────────────────────────────────
        addItem() {
            // [TVA-FIX] Défaut à 0% — pas de TVA automatique
            this.items.push({
                _key:             this._nextKey++,
                product_id:       '',
                description:      '',
                quantity:         1,
                unit_price:       0,
                discount_percent: 0,
                tax_rate_value:   0,
                _ps_open:         false,
                _ps_search:       '',
                _ps_rect:         null,
            });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        duplicateItem(index) {
            const src = this.items[index];
            this.items.splice(index + 1, 0, {
                ...src,
                _key: this._nextKey++,
            });
        },

        get isClientTaxExempt() {
            if (!this.client_id) return false;
            return !!(clientExemptions || {})[this.client_id];
        },

        onProductChange(index) {
            const p = this.products.find(p => String(p.id) === String(this.items[index].product_id));
            if (p) {
                if (!this.items[index].description.trim()) {
                    this.items[index].description = p.name;
                }
                this.items[index].unit_price = parseFloat(p.sale_price) || 0;
                // [TVA-FIX] Client exonéré → 0%, sinon taux du produit (ou 0 si absent)
                this.items[index].tax_rate_value = this.isClientTaxExempt
                    ? 0
                    : (p.tax_rate?.rate != null ? parseFloat(p.tax_rate.rate) : 0);
            }
        },

        onClientChange() {
            // [TVA-FIX] Si client exonéré : mettre toutes les lignes à 0%
            if (this.isClientTaxExempt) {
                this.items = this.items.map(item => ({ ...item, tax_rate_value: 0 }));
            }
            // Passage exonéré → normal : NE PAS appliquer 18% automatiquement
        },

        applyClientDiscount() {
            const disc = parseFloat(this.selectedClient?.default_discount || 0);
            if (disc > 0) {
                this.items.forEach(i => { i.discount_percent = disc; });
            }
        },

        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(n || 0)) + ' FCFA';
        },
    };
}
</script>
@endpush
