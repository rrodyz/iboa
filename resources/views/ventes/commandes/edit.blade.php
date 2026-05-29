@extends('layouts.erp')
@section('title', 'Modifier commande '.$order->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.index') }}" class="hover:text-gray-700">Commandes</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.commandes.show', $order) }}" class="hover:text-gray-700 font-mono">{{ $order->number }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<div class="space-y-1 mb-5">
    <h1 class="text-2xl font-bold text-gray-900">Modifier la commande <span class="font-mono">{{ $order->number }}</span></h1>
</div>

<x-validation-errors />

<form method="POST" action="{{ route('ventes.commandes.update', $order) }}">
    @csrf
    @method('PUT')
    <x-form-guard :model="$order" />
    @include('ventes.commandes._form')
</form>
@endsection
