@extends('layouts.erp')
@section('title', 'Modifier — '.$supplier->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.index') }}" class="hover:text-gray-700">Fournisseurs</a>
    <span class="mx-1">/</span>
    <a href="{{ route('suppliers.show', $supplier) }}" class="hover:text-gray-700">{{ $supplier->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Modifier le fournisseur</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ $supplier->name }}</p>
    </div>

    {{-- Validation errors summary --}}
    @if($errors->any())
    <div class="mb-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="font-medium">Veuillez corriger les erreurs suivantes :</p>
            <ul class="mt-1 list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 p-6">
        @include('suppliers._form', [
            'formAction' => route('suppliers.update', $supplier),
            'formMethod' => 'PUT',
        ])
    </div>

</div>
@endsection
