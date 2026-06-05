@extends('layouts.erp')
@section('title', 'Tableau de bord')

@section('breadcrumb')
    <span class="text-gray-900 font-semibold">Tableau de bord</span>
@endsection

@push('styles')
    @include('partials.dashboard._dashboard-styles')
@endpush

@section('content')

{{-- ── Auto-refresh wrapper Alpine ───────────────────────────────────────────
     Polling JSON /dashboard/kpis toutes les 60s.
     Pas de rechargement page — mise à jour ciblée des éléments [data-kpi].
──────────────────────────────────────────────────────────────────────────── --}}
<div x-data="kpiAutoRefresh()" x-init="init()" class="space-y-5">

    {{-- Badge discret d'actualisation —────────────────────────────── --}}
    <div class="flex items-center justify-end gap-2 text-xs text-gray-400 -mb-3 pr-1" x-cloak>
        {{-- Spinner chargement --}}
        <span x-show="status === 'loading'" class="flex items-center gap-1.5">
            <svg class="w-3 h-3 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span>Actualisation…</span>
        </span>
        {{-- Succès --}}
        <span x-show="status === 'ok' && refreshedAt" class="flex items-center gap-1 text-emerald-500">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
            <span x-text="'Actualisé à ' + refreshedAt"></span>
        </span>
        {{-- Erreur --}}
        <span x-show="status === 'error'" class="text-rose-400 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12A9 9 0 113 12a9 9 0 0118 0z"/>
            </svg>
            <span>Hors ligne</span>
        </span>
        {{-- Bouton refresh manuel --}}
        <button @click="fetchNow()"
                class="ml-2 p-1 rounded-lg hover:bg-gray-100 transition-colors"
                title="Actualiser maintenant">
            <svg class="w-3.5 h-3.5 text-gray-400" :class="status==='loading'?'animate-spin':''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
        </button>
    </div>

    @include('partials.dashboard._dashboard-hero')

    @include('partials.dashboard._dashboard-kpi-cards')

    @include('partials.dashboard._dashboard-charts-row1')

    @include('partials.dashboard._dashboard-charts-row2')

    @include('partials.dashboard._dashboard-tables')

    @include('partials.dashboard._dashboard-top-lists')

    @include('partials.dashboard._dashboard-payment-methods')

@endsection

@push('scripts')
{{-- ApexCharts est bundlé dans app.js (window.ApexCharts) — pas besoin de CDN. --}}
    @include('partials.dashboard._dashboard-scripts')
@endpush
