@extends('layouts.erp')
@section('title', 'Nouveau compte')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.caisses.index') }}" class="hover:text-gray-700">Comptes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nouveau compte de trésorerie</h1>
            <p class="text-sm text-gray-500 mt-0.5">Caisse, compte bancaire ou mobile money — coordonnées et solde d'ouverture</p>
        </div>
        <a href="{{ route('tresorerie.caisses.index') }}" class="text-sm text-gray-500 hover:text-gray-700 whitespace-nowrap">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('tresorerie.caisses.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-5"
          x-data="{ type: '{{ old('type', 'banque') }}' }">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code') }}" required maxlength="30"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" x-model="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="banque"       {{ old('type') === 'banque'       ? 'selected' : '' }}>Banque</option>
                    <option value="caisse"       {{ old('type') === 'caisse'       ? 'selected' : '' }}>Caisse</option>
                    <option value="mobile_money" {{ old('type') === 'mobile_money' ? 'selected' : '' }}>Mobile Money</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement</label>
                <select name="payment_method_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                    <option value="">— Aucun —</option>
                    @foreach($paymentMethods as $pm)
                    <option value="{{ $pm->id }}" {{ old('payment_method_id') == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Devise <span class="text-red-500">*</span></label>
                <input type="text" name="currency_code" value="{{ old('currency_code', 'XOF') }}" required maxlength="3"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Solde d'ouverture (FCFA)</label>
                <input type="number" name="opening_balance" value="{{ old('opening_balance', 0) }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte solde faible (FCFA)</label>
                <input type="number" name="min_balance" value="{{ old('min_balance', 0) }}" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-gray-400 mt-1">0 = pas d'alerte</p>
            </div>
        </div>

        {{-- Coordonnées bancaires (comptes de type Banque) --}}
        <div x-show="type === 'banque'" x-cloak class="border-t border-gray-100 pt-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Coordonnées bancaires</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Banque</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agence</label>
                    <input type="text" name="bank_branch" value="{{ old('bank_branch') }}" maxlength="150"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">N° de compte (RIB)</label>
                    <input type="text" name="account_number" value="{{ old('account_number') }}" maxlength="50"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code SWIFT / BIC</label>
                    <input type="text" name="swift_bic" value="{{ old('swift_bic') }}" maxlength="11"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono uppercase focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                    <input type="text" name="iban" value="{{ old('iban') }}" maxlength="34"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono uppercase focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" maxlength="500"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none">{{ old('notes') }}</textarea>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}
                   class="rounded text-indigo-600">
            <span class="text-sm text-gray-700">Compte par défaut</span>
        </label>
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="{{ route('tresorerie.caisses.index') }}" class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg">Créer</button>
        </div>
    </form>
</div>
@endsection
