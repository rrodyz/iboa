@php $invoice ??= null; $selectedClient ??= null; $clientWithholding ??= []; $clientExemptions ??= []; @endphp
<script>
window._invoiceFormData = {
    invoice:           @json($invoice ? $invoice->load('items') : null),
    products:          @json($products ?? []),
    oldItems:          @json(old('items', [])),
    oldGlobalDiscount: @json(old('global_discount_amount', 0)),
    oldType:           @json(old('type')),
    selectedClient:    @json($selectedClient),
    oldClientId:       @json(old('client_id')),
    clientWithholding: @json($clientWithholding),
    clientExemptions:  @json($clientExemptions),
};
</script>
<div x-data="invoiceFormVentes()" x-cloak>

    {{-- Header fields --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
        <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Client --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                <select name="client_id" required
                        x-model="clientId"
                        @change="onClientChange()"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Sélectionner un client...</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                            {{ old('client_id', $invoice?->client_id ?? $selectedClient) == $client->id ? 'selected' : '' }}>
                            {{ $client->name }}{{ $client->trade_name ? ' — '.$client->trade_name : '' }}
                        </option>
                    @endforeach
                </select>
                @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

            {{-- Date émission --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date d'émission <span class="text-red-500">*</span></label>
                <input type="date" name="issued_at"
                       value="{{ old('issued_at', isset($invoice) ? $invoice->issued_at?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                       required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('issued_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Date échéance --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date d'échéance</label>
                <input type="date" name="due_at"
                       value="{{ old('due_at', isset($invoice) ? $invoice->due_at?->format('Y-m-d') : '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('due_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Conditions de paiement --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Conditions de paiement</label>
                <select name="payment_terms"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">— Choisir —</option>
                    @foreach(['immediate' => 'Paiement immédiat', '30' => '30 jours net', '60' => '60 jours net', '90' => '90 jours net', 'end_of_month' => 'Fin de mois'] as $val => $label)
                        <option value="{{ $val }}" {{ old('payment_terms', $invoice?->payment_terms ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_terms') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Type — x-model keeps Alpine invoiceType in sync so the recurrence panel shows/hides correctly.
                 The initial value comes from the data bridge (respects old('type') after validation failure). --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
                <select name="type" x-model="invoiceType"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="standard">Standard</option>
                    <option value="proforma">Proforma</option>
                    <option value="acompte">Acompte</option>
                    <option value="partielle">Partielle</option>
                    <option value="recurrente">Récurrente</option>
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Devise --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Devise</label>
                <select name="currency_code"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="XOF" {{ old('currency_code', $invoice?->currency_code ?? 'XOF') === 'XOF' ? 'selected' : '' }}>FCFA (XOF)</option>
                    <option value="EUR" {{ old('currency_code', $invoice?->currency_code ?? '') === 'EUR' ? 'selected' : '' }}>Euro (EUR)</option>
                    <option value="USD" {{ old('currency_code', $invoice?->currency_code ?? '') === 'USD' ? 'selected' : '' }}>Dollar (USD)</option>
                    <option value="GBP" {{ old('currency_code', $invoice?->currency_code ?? '') === 'GBP' ? 'selected' : '' }}>Livre (GBP)</option>
                </select>
                @error('currency_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Récurrence (visible uniquement si type = recurrente) --}}
            <div x-show="invoiceType === 'recurrente'" x-cloak class="lg:col-span-2">
                <div class="grid grid-cols-3 gap-3 p-3 bg-indigo-50 rounded-lg border border-indigo-100">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fréquence</label>
                        <select name="recurring_frequency"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                            <option value="monthly"   {{ old('recurring_frequency', $invoice?->recurring_frequency ?? 'monthly') === 'monthly'   ? 'selected' : '' }}>Mensuelle</option>
                            <option value="quarterly" {{ old('recurring_frequency', $invoice?->recurring_frequency ?? '') === 'quarterly' ? 'selected' : '' }}>Trimestrielle</option>
                            <option value="yearly"    {{ old('recurring_frequency', $invoice?->recurring_frequency ?? '') === 'yearly'    ? 'selected' : '' }}>Annuelle</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Prochaine émission</label>
                        <input type="date" name="next_recurring_date"
                               value="{{ old('next_recurring_date', isset($invoice) ? $invoice->next_recurring_date?->format('Y-m-d') : '') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_recurring" value="0">
                            <input type="checkbox" name="is_recurring" value="1"
                                   {{ old('is_recurring', $invoice?->is_recurring ?? false) ? 'checked' : '' }}
                                   class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <span class="text-xs font-medium text-gray-700">Activer la récurrence</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes', $invoice?->notes ?? '') }}</textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-4">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de facture</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase w-8">#</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase w-44">Produit</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-20">Qté</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-28">Prix Unit.</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-20">Remise%</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-20">TVA%</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-28">Total HT</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase w-28">Total TTC</th>
                        <th class="px-3 py-2.5 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="(item, index) in items" :key="item._key">
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-400 text-xs" x-text="index + 1"></td>
                            <td class="px-3 py-2">
                                <input type="text" :name="'items[' + index + '][description]'"
                                       x-model="item.description" placeholder="Description..."
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 min-w-[180px]">
                            </td>
                            @include('ventes.partials._product_combobox', ['accentColor' => 'indigo', 'formName' => 'invoice'])
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][quantity]'"
                                       x-model.number="item.quantity" min="1" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][unit_price]'"
                                       x-model.number="item.unit_price" min="0" step="1"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][discount_percent]'"
                                       x-model.number="item.discount_percent" min="0" max="100" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                {{-- Quand le client est exonéré : badge 0% non modifiable
                                     Sinon : sélecteur avec les taux configurés --}}
                                <template x-if="isClientTaxExempt">
                                    <div class="w-full border border-amber-200 bg-amber-50 rounded px-2 py-1.5 text-sm text-right text-amber-700 font-medium cursor-not-allowed select-none">
                                        0 %
                                        <input type="hidden" :name="'items[' + index + '][tax_rate_value]'" value="0">
                                    </div>
                                </template>
                                <template x-if="!isClientTaxExempt">
                                    <select :name="'items[' + index + '][tax_rate_value]'"
                                            x-model.number="item.tax_rate_value"
                                            class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="0">0 %</option>
                                        @foreach($taxRatesVente ?? [] as $tr)
                                            <option value="{{ $tr->rate }}">{{ $tr->rate }} % — {{ $tr->name }}</option>
                                        @endforeach
                                        {{-- Fallback si $taxRatesVente non injecté --}}
                                        @if(empty($taxRatesVente ?? []))
                                            <option value="18">18 % — TVA standard</option>
                                        @endif
                                    </select>
                                </template>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-700 font-medium text-xs whitespace-nowrap"
                                x-text="formatFcfa(lineHt(item))"></td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-900 font-semibold text-xs whitespace-nowrap"
                                x-text="formatFcfa(lineTtc(item))"></td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                        class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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
                    class="flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter une ligne
            </button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="mt-4 flex justify-end">
        <div class="w-full sm:w-96 bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <div class="flex justify-between text-sm text-gray-600">
                <span>Montant HT</span>
                <span class="tabular-nums font-medium" x-text="formatFcfa(subtotalHt)"></span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>TVA</span>
                <span class="tabular-nums font-medium" x-text="formatFcfa(totalTax)"></span>
            </div>
            <div class="flex items-center justify-between text-sm text-gray-600 gap-3">
                <label class="whitespace-nowrap">Remise globale (FCFA)</label>
                <input type="number" name="global_discount_amount"
                       x-model.number="global_discount_amount" min="0" step="1"
                       :max="subtotalHt + totalTax"
                       class="w-32 border border-gray-300 rounded px-2 py-1 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <template x-if="discountExceedsTotal">
                <p class="text-xs text-amber-600 text-right">⚠ La remise dépasse le total</p>
            </template>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-sm font-bold text-gray-900">Montant TTC</span>
                <span class="text-sm font-bold text-gray-900 tabular-nums" x-text="formatFcfa(totalTtc)"></span>
            </div>

            {{-- Retenues à la source (depuis les taxes du client) --}}
            <template x-if="withholdings.length > 0">
                <div class="space-y-1.5 pt-1">
                    <template x-for="w in withholdings" :key="w.short_name">
                        <div class="flex justify-between text-sm text-amber-700">
                            <span x-text="'Retenue ' + (w.short_name || w.name) + ' ' + w.rate.toLocaleString('fr-FR', {maximumFractionDigits:2}) + '%'"></span>
                            <span class="tabular-nums font-medium" x-text="'-' + formatFcfa(w.amount)"></span>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Net à payer --}}
            <div class="border-t-2 border-indigo-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">NET À PAYER</span>
                <span class="text-base font-bold text-indigo-700 tabular-nums" x-text="formatFcfa(netToPay)"></span>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="mt-4 flex items-center justify-end gap-3">
        <a href="{{ route('ventes.factures.index') }}"
           class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Annuler
        </a>
        <button type="submit"
                @click="submitting = true"
                :disabled="submitting"
                x-text="submitting ? 'Enregistrement...' : '{{ $invoice ? 'Mettre à jour' : 'Enregistrer la facture' }}'"
                class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Enregistrer la facture
        </button>
    </div>

</div>

@push('scripts')
<script>
function invoiceFormVentes() {
    const {
        invoice, products, oldItems, oldGlobalDiscount, oldType,
        selectedClient, oldClientId, clientWithholding, clientExemptions
    } = window._invoiceFormData;

    let _nextKey = 1;

    /**
     * [TVA-FIX] Utiliser `?? 0` (nullish) plutôt que `|| 18` (falsy).
     * `|| 18` transformait 0% (falsy) en 18%, forçant la TVA même sur les
     * clients exonérés ou les articles à 0%.
     */
    function mapItem(i) {
        return {
            _key:             _nextKey++,
            product_id:       i.product_id       ?? '',
            description:      i.description      ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            // Nullish coalescing : 0 est conservé, seul null/undefined → 0 par défaut
            tax_rate_value:   i.tax_rate_value != null ? parseFloat(i.tax_rate_value) : 0,
            // [PRODUCT-SEARCH] Combobox état
            _ps_open:   false,
            _ps_search: '',
            _ps_rect:   null,
        };
    }

    // Priority: existing invoice items > old() items from failed submission > default blank
    let initialItems;
    if (invoice && invoice.items && invoice.items.length) {
        initialItems = invoice.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        // [TVA-FIX] Défaut à 0% — l'utilisateur choisit explicitement la TVA
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 0 })];
    }

    const resolvedType    = oldType || (invoice ? (invoice.type || 'standard') : 'standard');
    const initialClientId = String(oldClientId ?? invoice?.client_id ?? selectedClient ?? '');

    return {
        items:                  initialItems,
        global_discount_amount: parseFloat(invoice ? invoice.global_discount_amount : oldGlobalDiscount) || 0,
        invoiceType:            resolvedType,
        products:               products,
        clientId:               initialClientId,
        clientWithholding:      clientWithholding  || {},
        clientExemptions:       clientExemptions   || {},
        submitting:             false,
        _nextKey,

        /** true si le client sélectionné est exonéré de TVA */
        get isClientTaxExempt() {
            if (!this.clientId) return false;
            return !!this.clientExemptions[this.clientId];
        },

        /** Appelé quand l'utilisateur change de client */
        onClientChange() {
            if (this.isClientTaxExempt) {
                // Mettre toutes les lignes à 0%
                this.items = this.items.map(item => ({ ...item, tax_rate_value: 0 }));
            }
            // Si passage d'exonéré → non-exonéré : NE PAS appliquer 18% automatiquement
            // L'utilisateur choisit lui-même le taux via le sélecteur.
        },

        get subtotalHt() {
            return this.items.reduce((sum, i) => {
                return sum + Math.round(i.quantity * i.unit_price * (1 - i.discount_percent / 100));
            }, 0);
        },
        get totalTax() {
            return this.items.reduce((sum, i) => {
                const ht = i.quantity * i.unit_price * (1 - i.discount_percent / 100);
                const taxRate = this.isClientTaxExempt ? 0 : (parseFloat(i.tax_rate_value) || 0);
                return sum + Math.round(ht * taxRate / 100);
            }, 0);
        },
        get totalTtc() {
            return Math.max(0, this.subtotalHt + this.totalTax - (this.global_discount_amount || 0));
        },
        get withholdings() {
            const rates = this.clientWithholding[this.clientId] || [];
            return rates.map(r => ({
                name:       r.name,
                short_name: r.short_name,
                rate:       parseFloat(r.rate) || 0,
                amount:     Math.round(this.subtotalHt * (parseFloat(r.rate) || 0) / 100),
            }));
        },
        get totalWithholding() {
            return this.withholdings.reduce((sum, w) => sum + w.amount, 0);
        },
        get netToPay() {
            return Math.max(0, this.totalTtc - this.totalWithholding);
        },
        get discountExceedsTotal() {
            return (this.global_discount_amount || 0) > this.subtotalHt + this.totalTax;
        },
        lineHt(item) {
            return Math.round(item.quantity * item.unit_price * (1 - item.discount_percent / 100));
        },
        lineTtc(item) {
            const taxRate = this.isClientTaxExempt ? 0 : (parseFloat(item.tax_rate_value) || 0);
            return Math.round(this.lineHt(item) * (1 + taxRate / 100));
        },
        addItem() {
            // [TVA-FIX] Défaut à 0 — pas de TVA automatique
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
            this.items.splice(index, 1);
        },
        onProductChange(index) {
            const p = this.products.find(p => String(p.id) === String(this.items[index].product_id));
            if (p) {
                if (!this.items[index].description.trim()) {
                    this.items[index].description = p.name;
                }
                this.items[index].unit_price = parseFloat(p.sale_price) || 0;

                // [TVA-FIX] Si client exonéré → toujours 0%
                // Sinon → taux du produit, ou 0 si le produit n'en a pas (plus de fallback 18%)
                if (this.isClientTaxExempt) {
                    this.items[index].tax_rate_value = 0;
                } else {
                    this.items[index].tax_rate_value = p.tax_rate?.rate != null
                        ? parseFloat(p.tax_rate.rate)
                        : 0;
                }
            }
        },
        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0 }).format(Math.round(n)) + ' FCFA';
        }
    };
}
</script>
@endpush
