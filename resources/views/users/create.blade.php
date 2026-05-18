@extends('layouts.erp')
@section('title', 'Nouvel utilisateur')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('users.index') }}" class="hover:text-gray-700">Utilisateurs</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouveau</span>
@endsection

@section('content')
<div class="space-y-1 mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Nouvel utilisateur</h1>
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

<form method="POST" action="{{ route('users.store') }}">
    @csrf
    @include('users._form')

    <div class="mt-4 flex items-center justify-end gap-3">
        <a href="{{ route('users.index') }}"
           class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Annuler
        </a>
        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Créer l'utilisateur
        </button>
    </div>
</form>
@endsection
