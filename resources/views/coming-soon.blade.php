@extends('layouts.erp')
@section('title', $module)
@section('content')
<div class="flex flex-col items-center justify-center py-24 text-center">
    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 4a2 2 0 100-4 2 2 0 000 4zm12 0a2 2 0 100-4 2 2 0 000 4zm-6 4a2 2 0 100-4 2 2 0 000 4"/>
        </svg>
    </div>
    <h2 class="text-xl font-semibold text-gray-900 mb-2">Module {{ $module }}</h2>
    <p class="text-gray-500 max-w-sm">Ce module est en cours de développement et sera disponible prochainement.</p>
    <a href="{{ route('dashboard') }}" class="mt-6 text-blue-600 hover:text-blue-700 text-sm font-medium">← Retour au tableau de bord</a>
</div>
@endsection
