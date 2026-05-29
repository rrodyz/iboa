@extends('layouts.erp')
@section('title', 'Nouvelle commande')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Commandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle commande</span>
@endsection

@section('content')
<div class="mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Nouvelle commande</h1>
    <p class="text-sm text-gray-500 mt-0.5">Créez une commande client et gérez les lignes d'articles</p>
</div>

<x-validation-errors />

<form method="POST" action="{{ route('ventes.commandes.store') }}">
    @csrf
    <x-form-guard />
    @include('ventes.commandes._form')
</form>
@endsection
