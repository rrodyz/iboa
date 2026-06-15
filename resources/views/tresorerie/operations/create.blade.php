@extends('layouts.erp')
@section('title', 'Opération de caisse')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.operations.index') }}" class="hover:text-gray-700">Opérations de caisse</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
@php
    $accountsJson = $cashAccounts->mapWithKeys(fn($a) => [$a->id => ['name' => $a->name, 'balance' => (int) $a->current_balance]]);
@endphp
<div class="max-w-2xl mx-auto"
     x-data="{
        direction: '{{ old('direction', $direction) }}',
        account: '{{ old('cash_account_id', '') }}',
        amount: {{ (int) old('amount', 0) }},
        accounts: {{ Js::from($accountsJson) }},
        get balance() { return this.accounts[this.account]?.balance ?? null; },
        get insufficient() { return this.direction === 'sortie' && this.balance !== null && this.amount > this.balance; },
        get after() { if (this.balance === null) return null; return this.direction === 'entree' ? this.balance + this.amount : this.balance - this.amount; },
        fmt(n) { return n === null ? '—' : new Intl.NumberFormat('fr-FR').format(n) + ' F'; },
        get blocked() { return !this.account || this.amount <= 0 || this.insufficient; }
     }">

    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-900">Opération diverse de caisse</h1>
        <p class="text-sm text-gray-500 mt-0.5">Entrée (apport, recette diverse) ou sortie (dépense, petty cash) sans facture</p>
    </div>

    <form method="POST" action="{{ route('tresorerie.operations.store') }}"
          class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
        @csrf

        {{-- Sens --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Sens <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 border rounded-lg px-4 py-3 cursor-pointer transition-colors"
                       :class="direction === 'entree' ? 'border-emerald-400 bg-emerald-50' : 'border-gray-300'">
                    <input type="radio" name="direction" value="entree" x-model="direction" class="text-emerald-600">
                    <span class="text-sm font-medium text-emerald-700">Entrée de caisse</span>
                </label>
                <label class="flex items-center gap-2 border rounded-lg px-4 py-3 cursor-pointer transition-colors"
                       :class="direction === 'sortie' ? 'border-red-400 bg-red-50' : 'border-gray-300'">
                    <input type="radio" name="direction" value="sortie" x-model="direction" class="text-red-600">
                    <span class="text-sm font-medium text-red-700">Sortie de caisse</span>
                </label>
            </div>
        </div>

        {{-- Caisse + Date --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Caisse <span class="text-red-500">*</span></label>
                <select name="cash_account_id" x-model="account" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Sélectionner —</option>
                    @foreach($cashAccounts as $ca)
                        <option value="{{ $ca->id }}">{{ $ca->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1" x-show="balance !== null">
                    Solde : <span class="font-semibold" x-text="fmt(balance)"></span>
                </p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Date <span class="text-red-500">*</span></label>
                <input type="date" name="operation_date" value="{{ old('operation_date', date('Y-m-d')) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        {{-- Montant + Catégorie --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Montant (FCFA) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" x-model.number="amount" min="1" step="1" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300"
                       :class="insufficient ? 'border-red-400' : ''">
                <p class="text-xs text-red-600 mt-1" x-show="insufficient" x-cloak>Solde insuffisant sur cette caisse.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Catégorie / Motif</label>
                <input type="text" name="category" value="{{ old('category') }}" maxlength="100" list="cat-list"
                       placeholder="Apport, recette diverse, fournitures…"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
                <datalist id="cat-list">
                    <option value="Apport en caisse"></option>
                    <option value="Recette diverse"></option>
                    <option value="Remboursement"></option>
                    <option value="Fournitures de bureau"></option>
                    <option value="Frais de transport"></option>
                    <option value="Petite dépense"></option>
                </datalist>
            </div>
        </div>

        {{-- Aperçu solde après --}}
        <div class="rounded-xl p-4 text-sm" x-show="account && amount > 0" x-cloak
             :class="direction === 'entree' ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100'">
            <p class="text-xs" :class="direction === 'entree' ? 'text-emerald-500' : 'text-red-500'">Solde après opération</p>
            <p class="font-bold font-mono" :class="after < 0 ? 'text-red-600' : (direction === 'entree' ? 'text-emerald-800' : 'text-red-800')" x-text="fmt(after)"></p>
        </div>

        {{-- Libellé --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Libellé <span class="text-gray-400 font-normal">(optionnel)</span></label>
            <input type="text" name="label" value="{{ old('label') }}" maxlength="255" placeholder="Description détaillée…"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <a href="{{ route('tresorerie.operations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuler</a>
            <button type="submit" :disabled="blocked"
                    class="inline-flex items-center gap-2 px-6 py-2.5 text-white rounded-lg text-sm font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    :class="direction === 'entree' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'">
                Enregistrer l'opération
            </button>
        </div>
    </form>
</div>
@endsection
