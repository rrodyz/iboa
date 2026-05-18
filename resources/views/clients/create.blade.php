@extends('layouts.erp')
@section('title', 'Nouveau client')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau client</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nouveau client</h1>
            <p class="text-sm text-gray-500 mt-0.5">Remplissez les informations pour créer un nouveau client</p>
        </div>
        <a href="{{ route('clients.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour
        </a>
    </div>

    {{-- Form card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form action="{{ route('clients.store') }}" method="POST">
            @csrf
            @include('clients._form')

            <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-end gap-3">
                <a href="{{ route('clients.index') }}"
                   class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Créer le client
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
