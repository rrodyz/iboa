@extends('layouts.erp')
@section('title', 'Modifier la famille')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('product-families.index') }}" class="hover:text-gray-700">Familles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier — {{ $family->name }}</span>
@endsection

@section('content')
<div class="max-w-2xl">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Modifier la famille</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $family->name }}</p>
    </div>

    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('product-families.update', $family) }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf
        @method('PUT')

        {{-- Famille parente --}}
        <div>
            <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">Famille parente</label>
            <select id="parent_id" name="parent_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent bg-white">
                <option value="">— Aucune (famille racine) —</option>
                @foreach($parents as $parent)
                <option value="{{ $parent->id }}" {{ old('parent_id', $family->parent_id) == $parent->id ? 'selected' : '' }}>
                    {{ $parent->name }}{{ $parent->code ? ' ('.$parent->code.')' : '' }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Nom --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                Nom <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name" value="{{ old('name', $family->name) }}" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent @error('name') border-red-300 @enderror">
            @error('name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Code --}}
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code</label>
            <input type="text" id="code" name="code" value="{{ old('code', $family->code) }}"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent @error('code') border-red-300 @enderror"
                   placeholder="Optionnel — doit être unique si renseigné">
            <p class="text-xs text-gray-400 mt-1">Optionnel — doit être unique si renseigné</p>
            @error('code')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Description --}}
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea id="description" name="description" rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent resize-none">{{ old('description', $family->description) }}</textarea>
        </div>

        {{-- Comptes comptables (SYSCOA / OHADA) --}}
        <div class="pt-4 border-t border-gray-100">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                <h3 class="text-sm font-semibold text-gray-900">Comptes comptables</h3>
                <span class="text-[10px] font-medium bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded">Optionnel</span>
            </div>
            <p class="text-xs text-gray-500 mb-3">Affectations automatiques utilisées lors de la comptabilisation des factures et achats.</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {{-- Compte de vente --}}
                <div>
                    <label for="sale_account_id" class="block text-xs font-medium text-gray-700 mb-1">
                        Compte de vente <span class="text-gray-400">(7xx)</span>
                    </label>
                    <select id="sale_account_id" name="sale_account_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent bg-white font-mono">
                        <option value="">—</option>
                        @foreach($accounts['sale'] as $acc)
                            <option value="{{ $acc->id }}" {{ old('sale_account_id', $family->sale_account_id) == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} — {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Compte d'achat --}}
                <div>
                    <label for="purchase_account_id" class="block text-xs font-medium text-gray-700 mb-1">
                        Compte d'achat <span class="text-gray-400">(6xx)</span>
                    </label>
                    <select id="purchase_account_id" name="purchase_account_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent bg-white font-mono">
                        <option value="">—</option>
                        @foreach($accounts['purchase'] as $acc)
                            <option value="{{ $acc->id }}" {{ old('purchase_account_id', $family->purchase_account_id) == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} — {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Compte de stock --}}
                <div>
                    <label for="stock_account_id" class="block text-xs font-medium text-gray-700 mb-1">
                        Compte de stock <span class="text-gray-400">(3xx)</span>
                    </label>
                    <select id="stock_account_id" name="stock_account_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent bg-white font-mono">
                        <option value="">—</option>
                        @foreach($accounts['stock'] as $acc)
                            <option value="{{ $acc->id }}" {{ old('stock_account_id', $family->stock_account_id) == $acc->id ? 'selected' : '' }}>
                                {{ $acc->code }} — {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Statut actif --}}
        <div class="flex items-center gap-3 py-3 border-t border-gray-100">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" id="is_active" name="is_active" value="1"
                   {{ old('is_active', $family->is_active) ? 'checked' : '' }}
                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-400">
            <label for="is_active" class="text-sm font-medium text-gray-700">Famille active</label>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                Enregistrer les modifications
            </button>
            <a href="{{ route('product-families.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                Annuler
            </a>
        </div>
    </form>

</div>
@endsection
