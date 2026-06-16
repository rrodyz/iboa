@php $invoice ??= null; @endphp
<script>
window._supplierInvoiceFormData = {
    invoice:   @json($invoice ? $invoice->load('items') : null),
    suppliers: @json($suppliers ?? []),
    products:  @json($products ?? [])
};
</script>
<div x-data="supplierInvoiceForm()">

    {{-- Informations générales --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
        <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- Fournisseur --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Fournisseur <span class="text-red-500">*</span></label>
                <select name="supplier_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    <option value="">Sélectionner un fournisseur...</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            {{ old('supplier_id', $invoice?->supplier_id) == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                @error('supplier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- N° facture fournisseur --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">N° facture fournisseur</label>
                <input type="text" name="supplier_invoice_number"
                       value="{{ old('supplier_invoice_number', $invoice?->supplier_invoice_number) }}"
                       placeholder="Réf. facture reçue"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                @error('supplier_invoice_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Date réception --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date réception <span class="text-red-500">*</span></label>
                <input type="date" name="received_at" required
                       value="{{ old('received_at', $invoice?->received_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                @error('received_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Date échéance --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date d'échéance</label>
                <input type="date" name="due_at"
                       value="{{ old('due_at', $invoice?->due_at?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                @error('due_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Notes --}}
            <div class="lg:col-span-3">
                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">{{ old('notes', $invoice?->notes) }}</textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Lignes de facture --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-4">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de facture</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
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
                    <template x-for="(item, index) in items" :key="index">
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-400 text-xs" x-text="index + 1"></td>
                            <td class="px-3 py-2">
                                <input type="text" :name="'items[' + index + '][description]'"
                                       x-model="item.description" placeholder="Description..."
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500 min-w-[180px]">
                            </td>
                            <td class="px-3 py-2">
                                <select :name="'items[' + index + '][product_id]'"
                                        x-model="item.product_id" @change="onProductChange(index)"
                                        class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">— Produit —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][quantity]'"
                                       x-model.number="item.quantity" min="1" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][unit_price]'"
                                       x-model.number="item.unit_price" min="0" step="1"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][discount_percent]'"
                                       x-model.number="item.discount_percent" min="0" max="100" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" :name="'items[' + index + '][tax_rate_value]'"
                                       x-model.number="item.tax_rate_value" min="0" max="100" step="1" inputmode="numeric"
                                       class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm text-right focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
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
                    class="flex items-center gap-2 text-sm text-amber-600 hover:text-amber-800 font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter une ligne
            </button>
        </div>
    </div>

    {{-- Récapitulatif --}}
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
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">Total TTC</span>
                <span class="text-base font-bold text-amber-700 tabular-nums" x-text="formatFcfa(totalTtc)"></span>
            </div>
        </div>
    </div>

    {{-- Boutons --}}
    <div class="mt-4 flex items-center justify-end gap-3">
        <a href="{{ route('achats.factures-fournisseurs.index') }}"
           class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Enregistrer
        </button>
    </div>

</div>

@push('scripts')
<script>
function supplierInvoiceForm() {
    const { invoice, products } = window._supplierInvoiceFormData;
    return {
        items: invoice && invoice.items && invoice.items.length ? invoice.items.map(i => ({
            product_id:       i.product_id   ?? '',
            description:      i.description  ?? '',
            quantity:         parseInt(i.quantity, 10) || 1,
            unit_price:       parseFloat(i.unit_price)       || 0,
            discount_percent: parseFloat(i.discount_percent) || 0,
            tax_rate_value:   parseFloat(i.tax_rate_value)   || 18,
        })) : [{product_id: '', description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18}],
        products,

        get subtotalHt() {
            return this.items.reduce((sum, i) => sum + Math.round(i.quantity * i.unit_price * (1 - i.discount_percent / 100)), 0);
        },
        get totalTax() {
            return this.items.reduce((sum, i) => {
                const ht = i.quantity * i.unit_price * (1 - i.discount_percent / 100);
                return sum + Math.round(ht * i.tax_rate_value / 100);
            }, 0);
        },
        get totalTtc() { return this.subtotalHt + this.totalTax; },
        lineHt(item) { return Math.round(item.quantity * item.unit_price * (1 - item.discount_percent / 100)); },
        lineTtc(item) { return Math.round(this.lineHt(item) * (1 + item.tax_rate_value / 100)); },
        addItem() { this.items.push({product_id: '', description: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_rate_value: 18}); },
        removeItem(index) { this.items.splice(index, 1); },
        onProductChange(index) {
            const p = this.products.find(p => p.id == this.items[index].product_id);
            if (p) {
                this.items[index].description = p.name;
                this.items[index].unit_price  = parseFloat(p.purchase_price) || 0;
            }
        },
        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', {minimumFractionDigits: 0}).format(Math.round(n)) + ' FCFA';
        }
    }
}
</script>
@endpush
