@extends('layouts.erp')
@section('title', 'Nouvel entrepôt')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.index') }}" class="hover:text-gray-700">Entrepôts</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('stocks.warehouses.index') }}"
           class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nouvel entrepôt</h1>
            <p class="text-sm text-gray-500">Ajouter un nouveau site de stockage</p>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('stocks.warehouses.store') }}" method="POST">
        @csrf
        <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

            <div class="p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Informations générales</h2>
                @include('warehouses._form')
            </div>

            <div class="px-6 py-4 bg-gray-50 rounded-b-xl flex items-center justify-end gap-3">
                <a href="{{ route('stocks.warehouses.index') }}"
                   class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                    Créer l'entrepôt
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
