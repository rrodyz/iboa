@extends('layouts.erp')
@section('title', 'Nouveau décaissement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.decaissements.index') }}" class="hover:text-gray-700">Décaissements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="max-w-4xl"
     x-data="decPaymentForm({{ $selectedSupplier ? $selectedSupplier : 'null' }})"
     x-init="init()">

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">Nouveau décaissement fournisseur</h1>
        <p class="text-sm text-gray-500 mt-0.5">Enregistrement d'un paiement effectué à un fournisseur</p>
    </div>

    <form method="POST" action="{{ route('tresorerie.decaissements.store') }}"
          x-data="{ submitting: false }"
          @submit="submitting = true">
        @csrf

        <div class="space-y-5">

            {{-- Section 1 : Informations du paiement --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Informations du paiement</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Fournisseur --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fournisseur <span class="text-red-500">*</span>
                        </label>
                        <select name="supplier_id" required
                                x-model="supplierId"
                                @change="loadInvoices()"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">— Sélectionner un fournisseur —</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    {{ (old('supplier_id', $selectedSupplier) == $supplier->id) ? 'selected' : '' }}>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Montant --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Montant (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="amount" min="1" required
                               x-model.number="amount"
                               value="{{ old('amount') }}"
                               placeholder="0"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        @error('amount')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Date du paiement <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="payment_date" required
                               value="{{ old('payment_date', date('Y-m-d')) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        @error('payment_date')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Mode de paiement --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                        <select name="payment_method_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">— Sélectionner —</option>
                            @foreach($paymentMethods as $pm)
                                <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>
                                    {{ $pm->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Compte de trésorerie --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Compte de trésorerie</label>
                        <select name="cash_account_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">— Sélectionner —</option>
                            @foreach($cashAccounts as $ca)
                                <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>
                                    {{ $ca->name }} ({{ number_format($ca->current_balance, 0, ',', ' ') }} FCFA)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Référence --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Référence / N° chèque / Transaction</label>
                        <input type="text" name="reference" maxlength="100"
                               value="{{ old('reference') }}"
                               placeholder="N° chèque, réf virement, ID transaction..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>

                    {{-- Téléphone --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">N° téléphone (Mobile Money)</label>
                        <input type="text" name="phone_number" maxlength="20"
                               value="{{ old('phone_number') }}"
                               placeholder="Ex: 97000000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>

                    {{-- Notes --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                  placeholder="Observations...">{{ old('notes') }}</textarea>
                    </div>

                </div>
            </div>

            {{-- Section 2 : Imputation sur factures fournisseur --}}
            <div x-show="supplierId" x-transition class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800">Imputation sur factures fournisseur</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Répartissez le montant payé sur les factures dues.</p>
                    </div>
                    <button type="button" @click="autoAllocate()"
                            :disabled="invoices.length === 0 || amount <= 0"
                            class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Répartir automatiquement
                    </button>
                </div>

                <div x-show="loading" class="py-8 text-center text-gray-400 text-sm">
                    <svg class="animate-spin w-5 h-5 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Chargement des factures...
                </div>

                <div x-show="!loading && invoices.length === 0 && supplierId" class="py-8 text-center text-gray-400 text-sm">
                    Aucune facture impayée pour ce fournisseur.
                </div>

                <div x-show="!loading && invoices.length > 0" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                <th class="pb-2 text-left">Facture</th>
                                <th class="pb-2 text-left">N° fournisseur</th>
                                <th class="pb-2 text-left">Échéance</th>
                                <th class="pb-2 text-right">Total TTC</th>
                                <th class="pb-2 text-right">Reste à payer</th>
                                <th class="pb-2 text-right">Montant imputé (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(inv, index) in invoices" :key="inv.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 pr-3">
                                        <span class="font-mono font-medium text-red-700" x-text="inv.number"></span>
                                        <input type="hidden" :name="`allocations[${index}][supplier_invoice_id]`" :value="inv.id">
                                        <input type="hidden" :name="`allocations[${index}][allocated_amount]`" :value="inv.allocated || 0">
                                    </td>
                                    <td class="py-2 pr-3 text-gray-600 text-xs font-mono" x-text="inv.supplier_invoice_number || '—'"></td>
                                    <td class="py-2 pr-3 text-gray-600 whitespace-nowrap" x-text="inv.due_at || '—'"></td>
                                    <td class="py-2 pr-3 text-right tabular-nums text-gray-700" x-text="formatFcfa(inv.total_ttc)"></td>
                                    <td class="py-2 pr-3 text-right tabular-nums font-medium text-orange-600" x-text="formatFcfa(inv.remaining_amount)"></td>
                                    <td class="py-2 text-right">
                                        <input type="number" min="0"
                                               :max="inv.remaining_amount"
                                               x-model.number="inv.allocated"
                                               class="w-36 border border-gray-300 rounded-lg px-2 py-1 text-sm text-right focus:ring-2 focus:ring-red-500 focus:border-red-500 tabular-nums"
                                               placeholder="0">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Montant payé :</span>
                            <span class="font-semibold tabular-nums" x-text="formatFcfa(amount)"></span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Total imputé :</span>
                            <span class="font-semibold tabular-nums text-red-700" x-text="formatFcfa(totalAllocated)"></span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold border-t border-gray-200 pt-1 mt-1"
                             :class="remainingToAllocate < 0 ? 'text-red-600' : 'text-gray-700'">
                            <span>Reste non imputé :</span>
                            <span class="tabular-nums" x-text="formatFcfa(remainingToAllocate)"></span>
                        </div>
                        <p x-show="remainingToAllocate < 0" class="text-xs text-red-500 mt-1">
                            Le montant imputé dépasse le montant payé.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3">
                <button type="submit" :disabled="submitting"
                        :class="submitting ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-600 hover:bg-red-700'"
                        class="text-white font-medium px-6 py-2.5 rounded-lg text-sm transition-colors inline-flex items-center gap-2">
                    <svg x-show="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span x-text="submitting ? 'Enregistrement...' : 'Enregistrer le décaissement'"></span>
                </button>
                <a href="{{ route('tresorerie.decaissements.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Annuler
                </a>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function decPaymentForm(preselectedSupplierId) {
    return {
        supplierId: preselectedSupplierId ? String(preselectedSupplierId) : '',
        amount: 0,
        invoices: [],
        loading: false,

        get totalAllocated() {
            return this.invoices.reduce((sum, inv) => sum + (Number(inv.allocated) || 0), 0);
        },

        get remainingToAllocate() {
            return this.amount - this.totalAllocated;
        },

        init() {
            if (this.supplierId) {
                this.loadInvoices();
            }
        },

        loadInvoices() {
            if (!this.supplierId) {
                this.invoices = [];
                return;
            }
            this.loading = true;
            this.invoices = [];

            fetch(`{{ route('tresorerie.decaissements.invoices') }}?supplier_id=${this.supplierId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.invoices = data.map(inv => ({ ...inv, allocated: 0 }));
                this.loading = false;
            })
            .catch(() => { this.loading = false; });
        },

        autoAllocate() {
            let remaining = Number(this.amount);
            for (let inv of this.invoices) {
                if (remaining <= 0) {
                    inv.allocated = 0;
                    continue;
                }
                const canAllocate = Math.min(remaining, inv.remaining_amount);
                inv.allocated = canAllocate;
                remaining -= canAllocate;
            }
        },

        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' FCFA';
        }
    };
}
</script>
@endpush
