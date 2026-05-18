@extends('layouts.erp')
@section('title', 'Rapports & Analyse')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapports</span>
@endsection

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Rapports & Analyse BI</h1>
        <p class="text-sm text-gray-500 mt-1">Sélectionnez un rapport pour analyser vos données</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">

        {{-- CA --}}
        <a href="{{ route('reports.ca') }}"
           class="group bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all duration-200">
            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center mb-4 group-hover:bg-indigo-600 transition-colors">
                <svg class="w-6 h-6 text-indigo-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">Chiffre d'affaires</h3>
            <p class="text-sm text-gray-500">Analyse du CA par période, par client — évolution et tendances avec export Excel.</p>
            <div class="mt-4 flex items-center gap-1 text-indigo-600 text-sm font-semibold group-hover:gap-2 transition-all">
                Ouvrir le rapport
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        {{-- Marges --}}
        <a href="{{ route('reports.margins') }}"
           class="group bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-emerald-300 transition-all duration-200">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center mb-4 group-hover:bg-emerald-600 transition-colors">
                <svg class="w-6 h-6 text-emerald-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">Analyse des marges</h3>
            <p class="text-sm text-gray-500">Marge brute par produit, taux de marge, coût d'achat vs prix de vente — export Excel.</p>
            <div class="mt-4 flex items-center gap-1 text-emerald-600 text-sm font-semibold group-hover:gap-2 transition-all">
                Ouvrir le rapport
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        {{-- Performance --}}
        <a href="{{ route('reports.sales-performance') }}"
           class="group bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-violet-300 transition-all duration-200">
            <div class="w-12 h-12 rounded-xl bg-violet-100 flex items-center justify-center mb-4 group-hover:bg-violet-600 transition-colors">
                <svg class="w-6 h-6 text-violet-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">Performance commerciale</h3>
            <p class="text-sm text-gray-500">CA par commercial, nombre de clients, panier moyen, taux d'encaissement — export Excel.</p>
            <div class="mt-4 flex items-center gap-1 text-violet-600 text-sm font-semibold group-hover:gap-2 transition-all">
                Ouvrir le rapport
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        {{-- Achats --}}
        <a href="{{ route('reports.achats') }}"
           class="group bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-orange-300 transition-all duration-200">
            <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center mb-4 group-hover:bg-orange-600 transition-colors">
                <svg class="w-6 h-6 text-orange-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">Achats fournisseurs</h3>
            <p class="text-sm text-gray-500">Analyse des factures fournisseurs par période, par fournisseur — évolution et tendances.</p>
            <div class="mt-4 flex items-center gap-1 text-orange-600 text-sm font-semibold group-hover:gap-2 transition-all">
                Ouvrir le rapport
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

        {{-- Âge des créances --}}
        <a href="{{ route('reports.aging-receivables') }}"
           class="group bg-white rounded-2xl border border-gray-200 p-6 shadow-sm hover:shadow-md hover:border-rose-300 transition-all duration-200">
            <div class="w-12 h-12 rounded-xl bg-rose-100 flex items-center justify-center mb-4 group-hover:bg-rose-600 transition-colors">
                <svg class="w-6 h-6 text-rose-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">Âge des créances</h3>
            <p class="text-sm text-gray-500">Ancienneté des factures impayées par client — buckets à échoir, 1–30j, 31–60j, 61–90j, +90j.</p>
            <div class="mt-4 flex items-center gap-1 text-rose-600 text-sm font-semibold group-hover:gap-2 transition-all">
                Ouvrir le rapport
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>

    </div>
</div>
@endsection
