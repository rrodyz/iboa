@php
    $quote          ??= null;
    $selectedClient ??= null;
@endphp

{{-- PHP → JS: only data actually used by Alpine (clients omitted — rendered server-side) --}}
<script>
window._quoteFormData = {
    quote:               @json($quote ? $quote->load('items') : null),
    products:            @json($products ?? []),
    oldItems:            @json(old('items', [])),
    oldGlobalDiscount:   @json(old('global_discount_amount', 0)),
};
</script>

<div x-data="quoteForm()" x-ref="root">

    {{-- Validation error banner --}}
    @if($errors->any())
    <div class="mb-4 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="font-medium mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5 text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- ── Section 1 : Informations générales ──────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- Client --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Client <span class="text-red-500">*</span>
                </label>
                <select name="client_id" required
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

            {{-- Date de validité --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date de validité</label>
                <input type="date" name="expires_at"
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
    </div>

    {{-- ── Section 2 : Lignes du devis ─────────────────────────────────── --}}
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
                        <th class="px-3 py-2.5 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- Use item._key (stable) not index as Alpine key --}}
                    <template x-for="(item, index) in items" :key="item._key">
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-400 text-xs" x-text="index + 1"></td>

                            {{-- Description --}}
                            <td class="px-3 py-2">
                                <input type="text" :name="'items[' + index + '][description]'"
                                       x-model="item.description"
                                       placeholder="Description de la prestation..."
                                       :class="item.description.trim() === '' && submitted ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                       class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 min-w-[180px]">
                            </td>

                            {{-- Produit --}}
                            <td class="px-3 py-2">
                                <select :name="'items[' + index + '][product_id]'"
                                        x-model="item.product_id"
                                        @change="onProductChange(index)"
                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">— Produit —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id"
                                                :title="formatFcfa(p.sale_price)"
                                                x-text="(p.reference ? '[' + p.reference + '] ' : '') + p.name">
                                        </option>
                                    </template>
                                </select>
                            </td>

                            {{-- Quantité — step=0.01 pour autoriser les fractions sans forcer l'affichage "1,0" en locale FR --}}
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

                            {{-- Supprimer --}}
                            <td class="px-3 py-2 text-center">
                                <button type="button" @click="removeItem(index)"
                                        x-show="items.length > 1"
                                        title="Supprimer cette ligne"
                                        class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
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

    {{-- ── Récapitulatif ────────────────────────────────────────────────── --}}
    <div class="mt-4 flex justify-end">
        <div class="w-full sm:w-80 bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <div class="flex justify-between text-sm text-gray-600">
                <span>Sous-total HT</span>
                <span class="tabular-nums font-medium" x-text="formatFcfa(subtotalHt)"></span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Total TVA</span>
                <span class="tabular-nums font-medium" x-text="formatFcfa(totalTax)"></span>
            </div>
            <div class="flex items-center justify-between text-sm text-gray-600 gap-3">
                <label class="whitespace-nowrap" for="global_discount_amount">Remise globale (FCFA)</label>
                <input type="number" name="global_discount_amount" id="global_discount_amount"
                       x-model.number="global_discount_amount"
                       min="0" step="1"
                       :max="subtotalHt + totalTax"
                       class="w-32 border border-gray-300 rounded px-2 py-1 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <p x-show="global_discount_amount > subtotalHt + totalTax"
               class="text-xs text-red-500 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                La remise dépasse le total — le montant final sera ramené à 0.
            </p>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold tabular-nums"
                      :class="totalTtc > 0 ? 'text-blue-700' : 'text-gray-400'"
                      x-text="formatFcfa(totalTtc)"></span>
            </div>
        </div>
    </div>

    {{-- ── Boutons ───────────────────────────────────────────────────────── --}}
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

</div>

@push('scripts')
<script>
function quoteForm() {
    const { quote, products, oldItems, oldGlobalDiscount } = window._quoteFormData;

    // ── Item initialisation priority:
    //    1. Existing quote (edit mode)
    //    2. old() items from failed validation (create mode, re-render)
    //    3. Default blank item
    let nextKey = 1;

    function mapItem(i) {
        return {
            _key:             nextKey++,
            product_id:       i.product_id        ?? '',
            description:      i.description       ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,   // [FIX-QTY] entier propre, pas "1,0"
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            tax_rate_value:   parseFloat(i.tax_rate_value)   || 18,
        };
    }

    let initialItems;
    if (quote && quote.items && quote.items.length) {
        initialItems = quote.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18 })];
    }

    const initialDiscount = quote
        ? (parseFloat(quote.global_discount_amount) || 0)
        : (parseFloat(oldGlobalDiscount) || 0);

    return {
        items:                 initialItems,
        global_discount_amount: initialDiscount,
        products:              products,
        submitting:            false,
        submitted:             false,
        _nextKey:              nextKey,

        // ── Computed ──────────────────────────────────────────────────────

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

        get totalTtc() {
            // Guard against negative total
            return Math.max(0, this.subtotalHt + this.totalTax - (this.global_discount_amount || 0));
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
            this.items.push({
                _key:             this._nextKey++,
                product_id:       '',
                description:      '',
                quantity:         1,
                unit_price:       0,
                discount_percent: 0,
                tax_rate_value:   18,
            });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },

        onProductChange(index) {
            const p = this.products.find(p => String(p.id) === String(this.items[index].product_id));
            if (p) {
                // Only overwrite description if it is currently empty
                if (!this.items[index].description.trim()) {
                    this.items[index].description = p.name;
                }
                this.items[index].unit_price     = parseFloat(p.sale_price) || 0;
                this.items[index].tax_rate_value = parseFloat(p.tax_rate?.rate) || 18;
            }
        },

        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(n || 0)) + ' FCFA';
        },
    };
}
</script>
@endpush
