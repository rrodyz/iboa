@extends('layouts.erp')
@section('title', 'Tableau de bord')

@section('breadcrumb')
    <span class="text-gray-900 font-semibold">Tableau de bord</span>
@endsection

@push('styles')
    @include('partials.dashboard._dashboard-styles')
@endpush

@section('content')

{{-- Wrapper space-y-5 : espacement vertical uniforme entre toutes les sections du dashboard.
     Le </div> de fermeture est dans _dashboard-payment-methods.blade.php (dernière section). --}}
<div class="space-y-5">

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
