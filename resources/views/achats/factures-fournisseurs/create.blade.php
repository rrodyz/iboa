@extends('layouts.erp')
@section('title', 'Nouvelle facture fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('achats.factures-fournisseurs.index') }}" class="hover:text-gray-700">Factures fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle facture</span>
@endsection

@section('content')
<div class="space-y-1 mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Nouvelle facture fournisseur</h1>
</div>

<x-validation-errors />

<form method="POST" action="{{ route('achats.factures-fournisseurs.store') }}">
    @csrf
    @include('achats.factures-fournisseurs._form')
</form>
@endsection
