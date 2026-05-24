@extends('layouts.erp')
@section('title', 'Nouvel employé')

@section('breadcrumb')
    <a href="{{ route('rh.employes.index') }}" class="hover:text-gray-700">Employés</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="max-w-5xl mx-auto">
<h1 class="text-2xl font-bold text-gray-900 mb-6">Créer un nouvel employé</h1>

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
    <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('rh.employes.store') }}">
@csrf
<x-form-guard />

<div class="space-y-6">

{{-- ─── Identité ──────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
        Identité
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Matricule (auto)</label>
            <input type="text" value="{{ $nextMatricule }}" readonly
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="last_name" value="{{ old('last_name') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Prénom <span class="text-red-500">*</span></label>
            <input type="text" name="first_name" value="{{ old('first_name') }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sexe</label>
            <select name="gender" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="M" @selected(old('gender','M')==='M')>Masculin</option>
                <option value="F" @selected(old('gender')==='F')>Féminin</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
            <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lieu de naissance</label>
            <input type="text" name="birth_place" value="{{ old('birth_place') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                   placeholder="Ex: Ouagadougou">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nationalité</label>
            <input type="text" name="nationality" value="{{ old('nationality', 'Burkinabè') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">N° CIN / CNIB</label>
            <input type="text" name="cin_number" value="{{ old('cin_number') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">N° CNSS</label>
            <input type="text" name="cnss_number" value="{{ old('cnss_number') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
            <input type="text" name="city" value="{{ old('city', 'Ouagadougou') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
            <input type="text" name="address" value="{{ old('address') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>

{{-- ─── Poste & Emploi ─────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        Poste & Emploi
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Département</label>
            <select name="department_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">— Aucun —</option>
                @foreach($departments as $dep)
                    <option value="{{ $dep->id }}" @selected(old('department_id')==$dep->id)>{{ $dep->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Intitulé du poste</label>
            <input type="text" name="job_title" value="{{ old('job_title') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <select name="category" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                @foreach(['cadre' => 'Cadre', 'agent_maitrise' => 'Agent de maîtrise', 'employe' => 'Employé', 'ouvrier' => 'Ouvrier'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('category','employe')===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date d'embauche</label>
            <input type="date" name="hiring_date" value="{{ old('hiring_date', now()->toDateString()) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>

{{-- ─── Situation familiale (IUTS) ────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Situation familiale
        <span class="text-xs font-normal text-gray-400">(quotient familial IUTS)</span>
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut familial</label>
            <select name="family_status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                @foreach(['celibataire' => 'Célibataire', 'marie' => 'Marié(e)', 'veuf' => 'Veuf / Veuve', 'divorce' => 'Divorcé(e)'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('family_status','celibataire')===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre d'enfants à charge</label>
            <input type="number" name="nb_children" value="{{ old('nb_children', 0) }}" min="0" max="20" step="1"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>

{{-- ─── Contrat initial ────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Contrat initial <span class="text-red-500">*</span>
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type de contrat</label>
            <select name="contract_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                @foreach(['CDI' => 'CDI – Durée indéterminée', 'CDD' => 'CDD – Durée déterminée', 'stage' => 'Stage', 'consultant' => 'Consultant'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('contract_type','CDI')===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de début <span class="text-red-500">*</span></label>
            <input type="date" name="contract_start" value="{{ old('contract_start', now()->toDateString()) }}" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin <span class="text-gray-400 font-normal">(CDD uniquement)</span></label>
            <input type="date" name="contract_end" value="{{ old('contract_end') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salaire de base (FCFA) <span class="text-red-500">*</span></label>
            <input type="number" name="base_salary" value="{{ old('base_salary') }}" min="0" step="1000" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono text-right focus:ring-2 focus:ring-blue-500"
                   placeholder="Ex : 250 000">
        </div>
    </div>
</div>

{{-- ─── Coordonnées bancaires ──────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 p-6">
    <h2 class="text-base font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
        </svg>
        Coordonnées bancaires
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement du salaire</label>
            <select name="payment_mode" id="payment_mode"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                    onchange="document.getElementById('bank-fields').style.display = this.value === 'virement' ? '' : 'none'">
                <option value="virement" @selected(old('payment_mode','virement')==='virement')>Virement bancaire</option>
                <option value="especes"  @selected(old('payment_mode')==='especes')>Espèces</option>
                <option value="cheque"   @selected(old('payment_mode')==='cheque')>Chèque</option>
                <option value="mobile"   @selected(old('payment_mode')==='mobile')>Mobile Money</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Banque / Établissement</label>
            <input type="text" name="bank_name" value="{{ old('bank_name') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                   placeholder="Ex : ECOBANK BF">
        </div>
    </div>

    {{-- RIB (visible seulement si virement) --}}
    <div id="bank-fields" style="{{ old('payment_mode','virement') !== 'virement' ? 'display:none' : '' }}">
        <p class="text-xs text-gray-500 mb-3 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
            <strong>RIB :</strong> Code banque (2 car.) · Code guichet (5 car.) · N° compte (11 car.) · Clé RIB (2 car.)
        </p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Code banque</label>
                <input type="text" name="bank_code" value="{{ old('bank_code') }}"
                       maxlength="5"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                       placeholder="BF">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Code guichet</label>
                <input type="text" name="bank_branch" value="{{ old('bank_branch') }}"
                       maxlength="5"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                       placeholder="01234">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Numéro de compte</label>
                <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}"
                       maxlength="11"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                       placeholder="00123456789">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Clé RIB</label>
                <input type="text" name="bank_rib_key" value="{{ old('bank_rib_key') }}"
                       maxlength="2"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                       placeholder="97">
            </div>
        </div>
        {{-- Ancien champ générique (rétrocompat) --}}
        <div class="mt-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">N° de compte complet (optionnel)</label>
            <input type="text" name="bank_account" value="{{ old('bank_account') }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                   placeholder="Format libre : ex. BF99 xxxx xxxx xxxx">
        </div>
    </div>
</div>

</div>{{-- end space-y --}}

<div class="flex justify-end gap-3 mt-6">
    <a href="{{ route('rh.employes.index') }}"
       class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">
        Annuler
    </a>
    <button type="submit"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
        Créer l'employé
    </button>
</div>
</form>
</div>
@endsection
