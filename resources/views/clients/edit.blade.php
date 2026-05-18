@extends('layouts.erp')
@section('title', 'Modifier — '.$client->displayName())

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.show', $client) }}" class="hover:text-gray-700">{{ $client->displayName() }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <span class="text-indigo-700 font-bold text-sm">
                    {{ strtoupper(substr($client->displayName(), 0, 2)) }}
                </span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $client->displayName() }}</h1>
                <p class="text-sm text-gray-500 font-mono">{{ $client->code }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('clients.show', $client) }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Retour
            </a>
        </div>
    </div>

    {{-- Archive form — OUTSIDE the update form to avoid nested-form _method collision --}}
    <form id="archiveClientForm"
          action="{{ route('clients.destroy', $client) }}"
          method="POST"
          onsubmit="return confirm('Archiver ce client ?')">
        @csrf
        @method('DELETE')
    </form>

    {{-- Form card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form action="{{ route('clients.update', $client) }}" method="POST">
            @csrf
            @method('PUT')
            @include('clients._form')

            <div class="mt-6 pt-5 border-t border-gray-100 flex items-center justify-between">
                <div>
                    {{-- Trigger archive via the separate form above --}}
                    <button type="button"
                            form="archiveClientForm"
                            onclick="document.getElementById('archiveClientForm').requestSubmit()"
                            class="inline-flex items-center gap-2 px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                        Archiver
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('clients.show', $client) }}"
                       class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                        Annuler
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>
@endsection
