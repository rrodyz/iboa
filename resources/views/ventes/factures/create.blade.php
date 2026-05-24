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

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('ventes.factures.store') }}">
    @csrf
    <x-form-guard />
    @include('ventes.factures._form')
</form>
@endsection
