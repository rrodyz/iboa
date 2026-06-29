@extends('layouts.erp')
@section('title', $certificate->exists ? 'Modifier certificat ' . $certificate->number : 'Nouveau certificat qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.certificats.index') }}" class="hover:text-gray-700">Certificats Qualité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $certificate->exists ? $certificate->number : 'Nouveau' }}</span>
@endsection

@section('content')
<div class="max-w-4xl space-y-5">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            {{ $certificate->exists ? 'Modifier le certificat ' . $certificate->number : 'Nouveau certificat qualité' }}
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">§8 & §10 CDC — attestation de conformité matière</p>
    </div>

    <form method="POST" action="{{ $certificate->exists ? route('qualite.certificats.update', $certificate) : route('qualite.certificats.store') }}"
          class="space-y-5">
        @csrf
        @if($certificate->exists) @method('PUT') @endif

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                    <select name="type" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                        @foreach($types as $val => $label)
                        <option value="{{ $val }}" @selected(old('type', $certificate->type) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date du certificat <span class="text-red-500">*</span></label>
                    <input type="date" name="date_certificat" value="{{ old('date_certificat', $certificate->date_certificat?->format('Y-m-d')) }}" required
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                    @error('date_certificat')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">N° Lot</label>
                    <input type="text" name="lot_number" value="{{ old('lot_number', $certificate->lot_number ?? $lotPrefill ?? '') }}"
                           placeholder="LOT-2026-001"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                    <input type="text" name="fournisseur" value="{{ old('fournisseur', $certificate->fournisseur) }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date réception</label>
                    <input type="date" name="date_reception" value="{{ old('date_reception', $certificate->date_reception?->format('Y-m-d')) }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Norme / Référence</label>
                    <input type="text" name="norme" value="{{ old('norme', $certificate->norme) }}"
                           placeholder="NF EN 10147, ISO 9001..."
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </div>

        {{-- Caractéristiques physiques (§13.5 CDC) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Caractéristiques physiques <span class="text-xs font-normal text-gray-400">(§13.5 CDC — contrôle bobines)</span></h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Poids réel (t)</label>
                    <input type="number" step="0.001" name="poids_reel" value="{{ old('poids_reel', $certificate->poids_reel) }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Largeur (mm)</label>
                    <input type="number" step="0.01" name="largeur_mm" value="{{ old('largeur_mm', $certificate->largeur_mm) }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Épaisseur (mm)</label>
                    <input type="number" step="0.001" name="epaisseur_mm" value="{{ old('epaisseur_mm', $certificate->epaisseur_mm) }}"
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                    <input type="text" name="couleur" value="{{ old('couleur', $certificate->couleur) }}"
                           placeholder="Galvanisé, Vert, Rouge..."
                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </div>

        {{-- Résultat --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Résultat du contrôle</h2>
            <div class="flex gap-4">
                @foreach($resultats as $val => $r)
                @php
                    $colors = ['conforme'=>'green', 'non_conforme'=>'red', 'sous_reserve'=>'amber'];
                    $c = $colors[$val] ?? 'gray';
                @endphp
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="resultat" value="{{ $val }}"
                           @checked(old('resultat', $certificate->resultat ?? 'conforme') === $val)
                           class="text-{{ $c }}-600 focus:ring-{{ $c }}-500">
                    <span class="text-sm font-medium text-gray-700">{{ $r['label'] }}</span>
                </label>
                @endforeach
            </div>
            @error('resultat')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observations</label>
                <textarea name="observations" rows="3"
                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">{{ old('observations', $certificate->observations) }}</textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('qualite.certificats.index') }}"
               class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">Annuler</a>
            <button type="submit"
                    class="px-6 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                {{ $certificate->exists ? 'Mettre à jour' : 'Créer le certificat' }}
            </button>
        </div>
    </form>

</div>
@endsection
