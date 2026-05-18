@extends('layouts.erp')
@section('title', 'Nouvel encaissement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.encaissements.index') }}" class="hover:text-gray-700">Encaissements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
@php
$formConfig = [
    'clientId'        => $selectedClient,
    'amount'          => old('amount'),   // null on fresh load → champ vide ; old value on validation retry
    'paymentMethodId' => old('payment_method_id', ''),
    'paymentMethods'  => $paymentMethods,
];
@endphp
<div class="max-w-4xl"
     x-data="paymentForm({{ \Illuminate\Support\Js::from($formConfig) }})">

    <div class="mb-5">
        <h1 class="text-2xl font-bold text-gray-900">Nouvel encaissement client</h1>
        <p class="text-sm text-gray-500 mt-0.5">Enregistrement d'un paiement reçu d'un client</p>
    </div>

    {{-- Validation errors --}}
    @if($errors->any())
    <div class="mb-5 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="font-medium mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('tresorerie.encaissements.store') }}"
          x-ref="form" @submit.prevent="submitForm()">
        @csrf

        <div class="space-y-5">

            {{-- ── Section 1 : Informations du paiement ───────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-green-100 text-green-700 text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                    Informations du paiement
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Client --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Client <span class="text-red-500">*</span>
                        </label>
                        <select name="client_id" required
                                x-model="clientId"
                                @change="onClientChange()"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 {{ $errors->has('client_id') ? 'border-red-400 bg-red-50' : '' }}">
                            <option value="">— Sélectionner un client —</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}"
                                    {{ (old('client_id', $selectedClient) == $client->id) ? 'selected' : '' }}>
                                    {{ $client->trade_name ?? $client->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('client_id')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Montant --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Montant (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="amount" min="1" step="1" required
                               x-model.number="amount"
                               placeholder="Ex : 50 000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 {{ $errors->has('amount') ? 'border-red-400 bg-red-50' : '' }}">
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
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 {{ $errors->has('payment_date') ? 'border-red-400 bg-red-50' : '' }}">
                        @error('payment_date')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Mode de paiement --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                        <select name="payment_method_id"
                                x-model="paymentMethodId"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
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
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">— Sélectionner —</option>
                            @foreach($cashAccounts as $ca)
                                <option value="{{ $ca->id }}" {{ old('cash_account_id') == $ca->id ? 'selected' : '' }}>
                                    {{ $ca->name }}
                                    <template x-if="false"><!-- balance shown below --></template>
                                    ({{ number_format($ca->current_balance, 0, ',', ' ') }} FCFA)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Référence --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Référence
                            <span class="text-gray-400 font-normal text-xs">— N° chèque, virement, transaction</span>
                        </label>
                        <input type="text" name="reference" maxlength="100"
                               value="{{ old('reference') }}"
                               placeholder="N° chèque, réf virement, ID transaction..."
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    {{-- Téléphone (Mobile Money) — shown only for mobile money methods --}}
                    <div x-show="isMobileMoney" x-transition x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            N° téléphone Mobile Money
                            <span class="text-red-500" x-show="isMobileMoney">*</span>
                        </label>
                        <input type="tel" name="phone_number" maxlength="20"
                               value="{{ old('phone_number') }}"
                               placeholder="Ex: +229 97 00 00 00"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    {{-- Notes --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                  placeholder="Observations, informations complémentaires...">{{ old('notes') }}</textarea>
                    </div>

                </div>
            </div>

            {{-- ── Section 2 : Imputation sur factures ─────────────────────── --}}
            <div x-show="clientId" x-transition x-cloak
                 class="bg-white rounded-xl border border-gray-200 p-5">

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-green-100 text-green-700 text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                            Imputation sur factures
                        </h2>
                        <p class="text-xs text-gray-500 mt-0.5 ml-8">Répartissez le montant reçu sur les factures impayées.</p>
                    </div>
                    <button type="button" @click="autoAllocate()"
                            :disabled="invoices.length === 0 || amount <= 0"
                            class="inline-flex items-center gap-1.5 bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Répartir auto.
                    </button>
                </div>

                {{-- Loading state --}}
                <div x-show="loading" class="py-10 text-center text-gray-400 text-sm">
                    <svg class="animate-spin w-6 h-6 mx-auto mb-2 text-green-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Chargement des factures...
                </div>

                {{-- No invoices --}}
                <div x-show="!loading && invoices.length === 0 && clientId"
                     class="py-10 text-center">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-gray-400">Aucune facture impayée pour ce client.</p>
                    <p class="text-xs text-gray-400 mt-0.5">Le paiement sera enregistré comme avance.</p>
                </div>

                {{-- Invoices table --}}
                <div x-show="!loading && invoices.length > 0" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                <th class="pb-2 text-left">Facture</th>
                                <th class="pb-2 text-left hidden sm:table-cell">Émise le</th>
                                <th class="pb-2 text-left">Échéance</th>
                                <th class="pb-2 text-right">Total TTC</th>
                                <th class="pb-2 text-right">Reste dû</th>
                                <th class="pb-2 text-right pr-1">Montant imputé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="(inv, index) in invoices" :key="inv.id">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-2.5 pr-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-semibold text-indigo-700 text-xs" x-text="inv.number"></span>
                                            <span x-show="inv.status === 'en_retard'"
                                                  class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                En retard
                                            </span>
                                            <span x-show="inv.status === 'partiellement_payee'"
                                                  class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                                                Part. payée
                                            </span>
                                        </div>
                                        {{-- Hidden inputs for form submission --}}
                                        <input type="hidden" :name="`allocations[${index}][invoice_id]`" :value="inv.id">
                                        <input type="hidden" :name="`allocations[${index}][allocated_amount]`" :value="inv.allocated || 0">
                                    </td>
                                    <td class="py-2.5 pr-3 text-gray-500 text-xs whitespace-nowrap hidden sm:table-cell" x-text="inv.issued_at || '—'"></td>
                                    <td class="py-2.5 pr-3 whitespace-nowrap">
                                        <span :class="inv.status === 'en_retard' ? 'text-red-600 font-medium' : 'text-gray-600'"
                                              x-text="inv.due_at || '—'"></span>
                                    </td>
                                    <td class="py-2.5 pr-3 text-right tabular-nums text-gray-600 whitespace-nowrap" x-text="formatFcfa(inv.total_ttc)"></td>
                                    <td class="py-2.5 pr-3 text-right tabular-nums font-semibold text-orange-600 whitespace-nowrap" x-text="formatFcfa(inv.remaining_amount)"></td>
                                    <td class="py-2.5 text-right">
                                        <input type="number" min="0" step="1"
                                               :max="inv.remaining_amount"
                                               x-model.number="inv.allocated"
                                               :class="inv.allocated > inv.remaining_amount ? 'border-red-400 bg-red-50' : 'border-gray-300'"
                                               class="w-32 border rounded-lg px-2 py-1 text-sm text-right focus:ring-2 focus:ring-green-500 focus:border-green-500 tabular-nums"
                                               placeholder="0">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    {{-- Allocation summary --}}
                    <div class="mt-4 p-4 rounded-lg border"
                         :class="remainingToAllocate < 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'">
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Montant reçu :</span>
                                <span class="font-semibold tabular-nums" x-text="formatFcfa(amount)"></span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Total imputé :</span>
                                <span class="font-semibold tabular-nums text-green-700" x-text="formatFcfa(totalAllocated)"></span>
                            </div>
                            <div class="flex justify-between text-sm font-semibold border-t border-gray-200 pt-1.5"
                                 :class="remainingToAllocate < 0 ? 'text-red-600' : (remainingToAllocate === 0 ? 'text-green-700' : 'text-gray-700')">
                                <span x-text="remainingToAllocate >= 0 ? 'Non imputé (avance) :' : 'Dépassement :'"></span>
                                <span class="tabular-nums" x-text="formatFcfa(Math.abs(remainingToAllocate))"></span>
                            </div>
                        </div>
                        <p x-show="remainingToAllocate < 0"
                           class="text-xs text-red-600 mt-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Le montant imputé dépasse le montant reçu. Corrigez avant d'enregistrer.
                        </p>
                        <p x-show="remainingToAllocate > 0 && totalAllocated > 0"
                           class="text-xs text-amber-600 mt-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Le solde non imputé (<span x-text="formatFcfa(remainingToAllocate)"></span>) sera conservé comme avance client.
                        </p>
                    </div>
                </div>
            </div>

            {{-- ── Actions ─────────────────────────────────────────────────── --}}
            <div class="flex gap-3 items-center">
                <button type="submit"
                        :disabled="submitting || remainingToAllocate < 0 || amount <= 0"
                        class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium px-6 py-2.5 rounded-lg text-sm transition-colors">
                    <svg x-show="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span x-text="submitting ? 'Enregistrement...' : 'Enregistrer l\'encaissement'"></span>
                </button>
                <a href="{{ route('tresorerie.encaissements.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 font-medium px-6 py-2.5 rounded-lg text-sm transition-colors">
                    Annuler
                </a>
                <p x-show="remainingToAllocate < 0"
                   class="text-xs text-red-600 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    Imputation invalide
                </p>
            </div>

        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function paymentForm(config) {
    return {
        clientId:        config.clientId ? String(config.clientId) : '',
        // Start empty so the placeholder is visible; x-model.number will set to 0 when cleared
        amount:          (config.amount !== null && config.amount !== '') ? Number(config.amount) : '',
        paymentMethodId: String(config.paymentMethodId || ''),
        paymentMethods:  config.paymentMethods || [],
        invoices:        [],
        loading:         false,
        submitting:      false,

        // ── Computed ───────────────────────────────────────────────────────

        get totalAllocated() {
            return this.invoices.reduce((sum, inv) => sum + (Number(inv.allocated) || 0), 0);
        },

        get remainingToAllocate() {
            return this.amount - this.totalAllocated;
        },

        get selectedMethod() {
            return this.paymentMethods.find(m => String(m.id) === String(this.paymentMethodId)) || null;
        },

        get isMobileMoney() {
            return this.selectedMethod?.is_mobile_money === true || this.selectedMethod?.is_mobile_money === 1;
        },

        // ── Lifecycle ─────────────────────────────────────────────────────

        init() {
            // Alpine v3 auto-calls this — do NOT add x-init="init()" on the element
            if (this.clientId) {
                this.loadInvoices();
            }
        },

        // ── Methods ───────────────────────────────────────────────────────

        onClientChange() {
            this.loadInvoices();
        },

        loadInvoices() {
            if (!this.clientId) {
                this.invoices = [];
                return;
            }
            this.loading  = true;
            this.invoices = [];

            fetch(`{{ route('tresorerie.encaissements.invoices') }}?client_id=${this.clientId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                this.invoices = data.map(inv => ({ ...inv, allocated: 0 }));
                this.loading  = false;
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
                inv.allocated  = Math.floor(canAllocate); // integer FCFA
                remaining     -= inv.allocated;
            }
        },

        submitForm() {
            if (this.remainingToAllocate < 0 || this.amount <= 0) return;
            this.submitting = true;
            this.$refs.form.submit();
        },

        formatFcfa(n) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n || 0) + ' FCFA';
        },
    };
}
</script>
@endpush
