@extends('layouts.erp')
@section('title', 'Modifier — ' . $location->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.index') }}" class="hover:text-gray-700">Entrepôts</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.show', $warehouse) }}" class="hover:text-gray-700">{{ $warehouse->name }}</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.warehouses.locations.index', $warehouse) }}" class="hover:text-gray-700">Emplacements</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $location->code }}</span>
@endsection

@section('content')
<div class="max-w-3xl space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Modifier l'emplacement</h1>
        <a href="{{ route('stocks.warehouses.locations.index', $warehouse) }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('stocks.warehouses.locations.update', [$warehouse, $location]) }}" class="space-y-5">
        @csrf @method('PUT')
        @include('warehouses.locations._form')
        <div class="flex justify-end gap-3">
            <a href="{{ route('stocks.warehouses.locations.index', $warehouse) }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg">Annuler</a>
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
