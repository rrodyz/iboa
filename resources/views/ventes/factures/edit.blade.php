@extends('layouts.erp')
@section('title', 'Modifier facture '.$invoice->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.index') }}" class="hover:text-gray-700">Factures</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.show', $invoice) }}" class="hover:text-gray-700 font-mono">{{ $invoice->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="space-y-1 mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Modifier la facture <span class="font-mono">{{ $invoice->number }}</span></h1>
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

{{-- [CONCURRENCE] Bandeau de verrou d'édition --}}
<x-edit-lock-banner :model="$invoice" model-type="Invoice" :edit-lock="$editLock ?? null" />

<form method="POST" action="{{ route('ventes.factures.update', $invoice) }}">
    @csrf
    @method('PUT')
    {{-- [CONCURRENCE] Protection anti-double-soumission + verrou optimiste --}}
    <x-form-guard :model="$invoice" />
    @include('ventes.factures._form')
</form>
@endsection
