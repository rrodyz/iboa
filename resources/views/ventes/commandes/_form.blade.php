@php $order ??= null; $selectedClient ??= null; @endphp
<script>
window._orderFormData = {
    order:             @json($order ? $order->load('items') : null),
    products:          @json($products ?? []),
    oldItems:          @json(old('items', [])),
    oldGlobalDiscount: @json(old('global_discount_amount', 0)),
    selectedClient:    @json($selectedClient),
};
</script>
<div x-data="orderFormVentes()">

    {{-- Header fields --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
        <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Client --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                <select name="client_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Sélectionner un client...</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}"
                            {{ old('client_id', $order?->client_id ?? $selectedClient) == $client->id ? 'selected' : '' }}>
                            {{ $client->name }}{{ $client->trade_name ? ' — '.$client->trade_name : '' }}
                        </option>
                    @endforeach
                </select>
                @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Date commande --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date commande <span class="text-red-500">*</span></label>
                <input type="date" name="issued_at"
                       value="{{ old('issued_at', isset($order) ? $order->issued_at?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                       required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('issued_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Livraison prévue --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Livraison prévue</label>
                <input type="date" name="delivery_date"
                       value="{{ old('delivery_date', isset($order) ? $order->delivery_date?->format('Y-m-d') : '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('delivery_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Référence --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Référence interne</label>
                <input type="text" name="reference"
                       value="{{ old('reference', $order->reference ?? '') }}"
                       placeholder="Ex: REF-2024-001"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Adresse livraison --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Adresse de livraison</label>
                <input type="text" name="delivery_address"
                       value="{{ old('delivery_address', $order->delivery_address ?? '') }}"
                       placeholder="Adresse de livraison..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                @error('delivery_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Notes --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('notes', $order->notes ?? '') }}</textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-4">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de commande</h2>
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
                                <input type="text"
                                       :name="'items[' + index + '][description]'"
                                       x-model="item.description"
                                       placeholder="Description..."
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 min-w-[180px]">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="'items[' + index + '][product_id]'"
                                        x-model="item.product_id"
                                        @change="onProductChange(index)"
                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
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
                                       x-model.number="item.quantity" min="1" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][unit_price]'"
                                       x-model.number="item.unit_price" min="0" step="1"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][discount_percent]'"
                                       x-model.number="item.discount_percent" min="0" max="100" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][tax_rate_value]'"
                                       x-model.number="item.tax_rate_value" min="0" max="100" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
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
                    class="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter une ligne
            </button>
        </div>
    </div>

    {{-- Summary --}}
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
                <label class="whitespace-nowrap">Remise globale (FCFA)</label>
                <input type="number" name="global_discount_amount"
                       x-model.number="global_discount_amount" min="0" step="1"
                       :max="subtotalHt + totalTax"
                       class="w-32 border border-gray-300 rounded px-2 py-1 text-sm text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <template x-if="discountExceedsTotal">
                <p class="text-xs text-amber-600 text-right">⚠ La remise dépasse le total</p>
            </template>
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-blue-700 tabular-nums" x-text="formatFcfa(totalTtc)"></span>
            </div>
        </div>
    </div>

    {{-- Submit --}}
    <div class="mt-4 flex items-center justify-end gap-3">
        <a href="{{ route('ventes.commandes.index') }}"
           class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Annuler
        </a>
        <button type="submit"
                @click="submitting = true"
                :disabled="submitting"
                x-text="submitting ? 'Enregistrement...' : '{{ $order ? 'Mettre à jour' : 'Enregistrer la commande' }}'"
                class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Enregistrer la commande
        </button>
    </div>

</div>

@push('scripts')
<script>
function orderFormVentes() {
    const { order, products, oldItems, oldGlobalDiscount, selectedClient } = window._orderFormData;

    let _nextKey = 1;
    function mapItem(i) {
        return {
            _key:             _nextKey++,
            product_id:       i.product_id       ?? '',
            description:      i.description      ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            tax_rate_value:   parseFloat(i.tax_rate_value)   || 18,
        };
    }

    // Priority: existing order items > old() items from failed submission > default blank
    let initialItems;
    if (order && order.items && order.items.length) {
        initialItems = order.items.map(mapItem);
    } else if (oldItems && oldItems.length) {
        initialItems = oldItems.map(mapItem);
    } else {
        initialItems = [mapItem({ description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18 })];
    }

    return {
        items:                 initialItems,
        global_discount_amount: parseFloat(order ? order.global_discount_amount : oldGlobalDiscount) || 0,
        products:              products,
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
                // Only auto-fill description if the field is empty
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
