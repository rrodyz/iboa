@php $invoice ??= null; $selectedClient ??= null; $clientWithholding ??= []; @endphp
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
                            <td class="px-3 py-2">
                                <select :name="'items[' + index + '][product_id]'"
                                        x-model="item.product_id" @change="onProductChange(index)"
                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">— Produit —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id"
                                                x-text="(p.reference ? '[' + p.reference + '] ' : '') + p.name"
                                                :title="formatFcfa(p.sale_price)"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][quantity]'"
                                       x-model.number="item.quantity" min="0.0001" step="any"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][unit_price]'"
                                       x-model.number="item.unit_price" min="0" step="1"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][discount_percent]'"
                                       x-model.number="item.discount_percent" min="0" max="100" step="0.01"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][tax_rate_value]'"
                                       x-model.number="item.tax_rate_value" min="0" max="100" step="0.01"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
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
    const { invoice, products, oldItems, oldGlobalDiscount, oldType, selectedClient, oldClientId, clientWithholding } = window._invoiceFormData;

    let _nextKey = 1;
    function mapItem(i) {
        return {
            _key:             _nextKey++,
            product_id:       i.product_id       ?? '',
            description:      i.description      ?? '',
            quantity:         parseFloat(i.quantity)         || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            tax_rate_value:   parseFloat(i.tax_rate_value)   || 18,
        };
    }

    // Priority: existing invoice items > old() items from failed submission > default blank
    let initialItems;
    if (invoice && invoice.items && invoice.items.length) {
        initialItems = invoice.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18 })];
    }

    // invoiceType: old('type') takes priority — ensures the Récurrente panel reopens
    // after a validation failure when the user had selected that type.
    const resolvedType = oldType || (invoice ? (invoice.type || 'standard') : 'standard');

    // clientId initial : old('client_id') > invoice.client_id > selectedClient (URL) > ''
    const initialClientId = String(
        (oldClientId ?? invoice?.client_id ?? selectedClient ?? '')
    );

    return {
        items:                 initialItems,
        global_discount_amount: parseFloat(invoice ? invoice.global_discount_amount : oldGlobalDiscount) || 0,
        invoiceType:           resolvedType,
        products:              products,
        clientId:              initialClientId,
        clientWithholding:     clientWithholding || {},
        submitting:            false,
        _nextKey,

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
            return Math.round(this.lineHt(item) * (1 + item.tax_rate_value / 100));
        },
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
            this.items.splice(index, 1);
        },
        onProductChange(index) {
            const p = this.products.find(p => String(p.id) === String(this.items[index].product_id));
            if (p) {
                // Only auto-fill description if the field is currently empty
                if (!this.items[index].description.trim()) {
                    this.items[index].description = p.name;
                }
                this.items[index].unit_price     = parseFloat(p.sale_price) || 0;
                this.items[index].tax_rate_value = parseFloat(p.tax_rate?.rate) || 18;
            }
        },
        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0 }).format(Math.round(n)) + ' FCFA';
        }
    };
}
</script>
@endpush
