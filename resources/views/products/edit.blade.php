@extends('layouts.erp')
@section('title', 'Modifier '.$product->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('products.index') }}" class="hover:text-gray-700">Articles</a>
    <span class="mx-1">/</span>
    <a href="{{ route('products.show', $product) }}" class="hover:text-gray-700">{{ $product->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Réf. <code class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $product->reference }}</code>
                — modifier la fiche article
            </p>
        </div>
        <a href="{{ route('products.show', $product) }}"
           class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg">
            ← Annuler
        </a>
    </div>

    @include('products._form')
</div>
@endsection
