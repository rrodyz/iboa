@extends('layouts.erp')
@section('title', 'Nouveau fournisseur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau fournisseur</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Nouveau fournisseur</h1>
        <p class="text-sm text-gray-500 mt-0.5">Renseignez les informations du fournisseur</p>
    </div>

    <x-validation-errors />

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        @include('suppliers._form', [
            'formAction' => route('suppliers.store'),
            'formMethod' => 'POST',
        ])
    </div>

</div>
@endsection
