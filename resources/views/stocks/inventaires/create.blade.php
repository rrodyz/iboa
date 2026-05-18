@extends('layouts.erp')
@section('title', 'Nouvel inventaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.inventaires.index') }}" class="hover:text-gray-700">Inventaires</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="max-w-lg mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Nouvel inventaire</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Créer une nouvelle session de comptage. Les articles seront automatiquement pré-remplis avec les niveaux de stock théoriques actuels.
        </p>
    </div>

    {{-- Form --}}
    <form action="{{ route('stocks.inventaires.store') }}" method="POST"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf

        {{-- Type d'inventaire --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type d'inventaire <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-3">
                @foreach([
                    'complet'  => ['label' => 'Complet',  'desc' => 'Tous les articles de l\'entrepôt', 'icon' => '📦'],
                    'annuel'   => ['label' => 'Annuel',   'desc' => 'Bilan annuel réglementaire',        'icon' => '📅'],
                    'tournant' => ['label' => 'Tournant', 'desc' => 'Contrôle rotatif par familles',     'icon' => '🔄'],
                ] as $val => $opt)
                <label class="relative flex flex-col gap-1 border rounded-lg px-4 py-3 cursor-pointer transition-colors
                              {{ old('type', 'complet') === $val ? 'border-teal-500 bg-teal-50' : 'border-gray-200 hover:border-teal-300' }}">
                    <input type="radio" name="type" value="{{ $val }}" class="sr-only"
                           {{ old('type', 'complet') === $val ? 'checked' : '' }}>
                    <span class="text-base">{{ $opt['icon'] }}</span>
                    <span class="text-sm font-semibold text-gray-900">{{ $opt['label'] }}</span>
                    <span class="text-xs text-gray-500">{{ $opt['desc'] }}</span>
                </label>
                @endforeach
            </div>
            @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        {{-- Warehouse --}}
        <div>
            <label for="warehouse_id" class="block text-sm font-medium text-gray-700 mb-1">
                Entrepôt <span class="text-red-500">*</span>
            </label>
            <select id="warehouse_id" name="warehouse_id" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 @error('warehouse_id') border-red-500 @enderror">
                <option value="">— Sélectionner un entrepôt —</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}{{ $wh->code ? ' (' . $wh->code . ')' : '' }}
                    </option>
                @endforeach
            </select>
            @error('warehouse_id')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-gray-400">
                Tous les articles ayant un stock dans cet entrepôt seront inclus automatiquement.
            </p>
        </div>

        {{-- Notes --}}
        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                Notes / Observations
            </label>
            <textarea id="notes" name="notes" rows="2"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 @error('notes') border-red-500 @enderror"
                      placeholder="Motif, observations...">{{ old('notes') }}</textarea>
            @error('notes')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Info box --}}
        <div class="flex items-start gap-3 p-3 bg-teal-50 border border-teal-200 rounded-lg text-sm text-teal-800">
            <svg class="w-5 h-5 text-teal-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium">Comment ça fonctionne ?</p>
                <ol class="mt-1 space-y-0.5 text-teal-700 text-xs list-decimal list-inside">
                    <li>Créer la session — les stocks théoriques sont chargés automatiquement</li>
                    <li>Saisir les quantités réellement comptées dans l'entrepôt</li>
                    <li>Valider — le stock est mis à jour avec les quantités comptées</li>
                </ol>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <a href="{{ route('stocks.inventaires.index') }}"
               class="text-sm text-gray-600 hover:text-gray-900 hover:underline">
                Annuler
            </a>
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
                Créer l'inventaire
            </button>
        </div>
    </form>

</div>
@endsection
