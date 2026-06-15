@extends('layouts.erp')
@section('title', 'Plan de charge')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Plan de charge</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Plan de charge</h1>
            <p class="text-sm text-gray-500 mt-0.5">Capacité vs charge planifiée par centre de travail (OF actifs)</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Horizon (jours)</label>
                <select name="horizon" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
                    @foreach([1,3,7,14,30] as $h)<option value="{{ $h }}" @selected($horizon==$h)>{{ $h }} j</option>@endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Charge planifiée</p>
            <p class="text-lg font-bold text-indigo-800 tabular-nums mt-1">{{ number_format($plan['total_planned_h'], 1, ',', ' ') }} h</p>
        </div>
        <div class="bg-sky-50 border border-sky-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-sky-600 uppercase tracking-wider">Capacité ({{ $horizon }} j)</p>
            <p class="text-lg font-bold text-sky-800 tabular-nums mt-1">{{ number_format($plan['total_capacity_h'], 1, ',', ' ') }} h</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-red-600 uppercase tracking-wider">Centres en surcharge</p>
            <p class="text-lg font-bold text-red-800 mt-1">{{ $plan['overloaded'] }}</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
            <p class="text-xs font-medium text-green-600 uppercase tracking-wider">Taux global</p>
            <p class="text-lg font-bold text-green-800 tabular-nums mt-1">{{ $plan['total_capacity_h'] > 0 ? number_format($plan['total_planned_h'] / $plan['total_capacity_h'] * 100, 0, ',', ' ') : 0 }} %</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Charge par centre de travail</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Centre</th><th class="text-right">Opérations</th><th class="text-right">Charge</th><th class="text-right">Capacité</th><th class="text-left w-1/3">Occupation</th></tr></thead>
                <tbody>
                    @forelse($plan['rows'] as $r)
                    <tr>
                        <td class="text-gray-800 font-medium">{{ $r['name'] }} <span class="text-gray-400 font-mono text-xs">{{ $r['code'] }}</span></td>
                        <td class="text-right tabular-nums text-gray-500">{{ $r['ops'] }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($r['planned_h'], 1, ',', ' ') }} h</td>
                        <td class="text-right tabular-nums text-gray-500">{{ number_format($r['capacity_h'], 1, ',', ' ') }} h</td>
                        <td>
                            @php $bar = match($r['status']){ 'surcharge'=>'bg-red-500','charge'=>'bg-amber-500','libre'=>'bg-gray-300',default=>'bg-green-500' }; @endphp
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full {{ $bar }}" style="width: {{ min(100, $r['occupation']) }}%"></div></div>
                                <span class="text-xs tabular-nums w-12 text-right {{ $r['status']==='surcharge' ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ number_format($r['occupation'], 0, ',', ' ') }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-gray-400">Aucun centre de travail. Créez-en + affectez des gammes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400">Charge = temps prévu des opérations non terminées sur OF lancés/en cours. Capacité = capacité journalière × rendement × horizon.</p>
</div>
@endsection
