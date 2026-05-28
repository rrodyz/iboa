@extends('layouts.erp')
@section('title', 'Présences & Absences — RH')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Présences & Absences</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Présences & Absences</h1>
        <p class="text-sm text-gray-500 mt-1">Suivi des présences et des absences du personnel</p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
        {{-- Icône --}}
        <div class="w-20 h-20 rounded-full bg-indigo-50 flex items-center justify-center mb-5">
            <svg class="w-10 h-10 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>

        <h2 class="text-xl font-semibold text-gray-800 mb-2">Module en cours de développement</h2>
        <p class="text-gray-500 max-w-md mb-2">
            Le module de gestion des présences et absences sera disponible dans une prochaine mise à jour.
        </p>
        <p class="text-sm text-gray-400 max-w-md mb-8">
            En attendant, vous pouvez gérer les congés et absences depuis le module <strong>Congés</strong>.
        </p>

        <div class="flex flex-wrap gap-3 justify-center">
            <a href="{{ route('rh.conges.index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Gérer les congés
            </a>
            <a href="{{ route('rh.dashboard') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                Tableau de bord RH
            </a>
        </div>

        {{-- Roadmap features --}}
        <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl text-left">
            @foreach([
                ['Pointage quotidien', 'Enregistrement des entrées/sorties par employé'],
                ['Récapitulatif mensuel', 'Vue calendrier des présences et absences'],
                ['Export & rapports', 'Export Excel des présences pour la paie'],
            ] as [$feat, $desc])
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 opacity-60">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2 h-2 rounded-full bg-indigo-300"></span>
                    <span class="text-sm font-semibold text-gray-700">{{ $feat }}</span>
                </div>
                <p class="text-xs text-gray-500">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
