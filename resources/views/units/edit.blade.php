@extends('layouts.erp')
@section('title', 'Modifier l\'unité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('units.index') }}" class="hover:text-gray-700">Unités de mesure</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Modifier l'unité</h1>
        <p class="text-sm text-gray-500 mt-1 font-mono">{{ $unit->name }} ({{ $unit->abbreviation }})</p>
    </div>

    @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        <ul class="list-disc list-inside space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('units.update', $unit) }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf @method('PUT')

        @include('units._form', ['unit' => $unit])

        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                Enregistrer
            </button>
            <a href="{{ route('units.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                Annuler
            </a>
        </div>
    </form>
</div>
@endsection
