@extends('layouts.erp')
@section('title', 'Nouvelle facture')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.index') }}" class="hover:text-gray-700">Factures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle facture</span>
@endsection

@section('content')
<div class="mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Nouvelle facture</h1>
    <p class="text-sm text-gray-500 mt-0.5">Créez une facture client avec lignes d'articles, taxes et remises</p>
</div>

<x-validation-errors />

<form method="POST" action="{{ route('ventes.factures.store') }}">
    @csrf
    <x-form-guard />
    @include('ventes.factures._form')
</form>
@endsection
