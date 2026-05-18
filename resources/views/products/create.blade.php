@extends('layouts.erp')
@section('title', 'Nouvel article')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('products.index') }}" class="hover:text-gray-700">Articles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nouvel article</h1>
            <p class="text-sm text-gray-500 mt-0.5">Création d'une fiche article — toutes les sections sont éditables, les champs facultatifs hériteront des valeurs par défaut.</p>
        </div>
    </div>

    @include('products._form')
</div>
@endsection
