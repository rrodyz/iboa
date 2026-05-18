@extends('layouts.erp')
@section('title', 'Nouveau devis')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.devis.index') }}" class="hover:text-gray-700">Devis</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Nouveau devis</h1>
        <p class="text-sm text-gray-500 mt-0.5">Créez un devis et imputez-le sur un client</p>
    </div>

    <form method="POST" action="{{ route('ventes.devis.store') }}">
        @csrf
        @include('ventes.devis._form')
    </form>
</div>
@endsection
