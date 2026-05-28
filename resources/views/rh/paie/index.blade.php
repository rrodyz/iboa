@extends('layouts.erp')
@section('title', 'Bulletins de paie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">RH – Paie</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int)$n, 0, ',', ' '); @endphp
{{-- KPI summary bar --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">Masse salariale brute</p>
        <p class="text-lg font-bold text-gray-900 tabular-nums">{{ $fmt($summary['total_brut']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">Total net à payer</p>
        <p class="text-lg font-bold text-emerald-600 tabular-nums">{{ $fmt($summary['total_net']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">CNSS salarié</p>
        <p class="text-lg font-bold text-red-600 tabular-nums">{{ $fmt($summary['total_cnss_employee']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">IUTS total</p>
        <p class="text-lg font-bold text-orange-600 tabular-nums">{{ $fmt($summary['total_iuts']) }} <span class="text-xs font-normal text-gray-400">FCFA</span></p>
    </div>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Bulletins de Paie</h1>
        <p class="text-sm text-gray-500 mt-1">Gestion mensuelle de la paie CNSS + IUTS</p>
    </div>
    <div class="flex gap-2">
        {{-- Livre de paie annuel PDF --}}
        <div x-data="{open:false}" class="relative">
            <button @click="open=!open"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Livre de paie
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.away="open=false"
                 class="absolute right-0 mt-1 w-52 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1">
                @foreach($years->isEmpty() ? [now()->year] : $years as $y)
                <a href="{{ route('rh.paie.livre-paie') }}?year={{ $y }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    📄 Livre de paie {{ $y }} PDF
                </a>
                @endforeach
            </div>
        </div>

        <a href="{{ route('rh.paie.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau bulletin
        </a>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex gap-3">
    <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Toutes les années</option>
        @foreach($years as $y)
            <option value="{{ $y }}" @selected(($filters['year'] ?? '') == $y)>{{ $y }}</option>
        @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Tous les statuts</option>
        @foreach(['brouillon'=>'Brouillon','calcule'=>'Calculé','valide'=>'Validé','paye'=>'Payé'] as $v=>$l)
            <option value="{{ $v }}" @selected(($filters['status'] ?? '')===$v)>{{ $l }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-sm">Filtrer</button>
    <a href="{{ route('rh.paie.index') }}" class="px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">✕</a>
</form>

<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Période</th>
                <th class="px-4 py-3 text-center">Effectif</th>
                <th class="px-4 py-3 text-right">Total brut</th>
                <th class="px-4 py-3 text-right">CNSS (emp.)</th>
                <th class="px-4 py-3 text-right">CNSS (pat.)</th>
                <th class="px-4 py-3 text-right">IUTS</th>
                <th class="px-4 py-3 text-right">Total net</th>
                <th class="px-4 py-3 text-center">Statut</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($runs as $run)
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ $run->period_label }}</td>
            <td class="px-4 py-3 text-center text-gray-600">{{ $run->employee_count ?: '—' }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ $run->total_brut ? number_format($run->total_brut, 0, ',', ' ') : '—' }}</td>
            <td class="px-4 py-3 text-right font-mono text-red-600">{{ $run->total_cnss_employee ? number_format($run->total_cnss_employee, 0, ',', ' ') : '—' }}</td>
            <td class="px-4 py-3 text-right font-mono text-orange-600">{{ $run->total_cnss_employer ? number_format($run->total_cnss_employer, 0, ',', ' ') : '—' }}</td>
            <td class="px-4 py-3 text-right font-mono text-red-600">{{ $run->total_iuts ? number_format($run->total_iuts, 0, ',', ' ') : '—' }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">{{ $run->total_net ? number_format($run->total_net, 0, ',', ' ') : '—' }}</td>
            <td class="px-4 py-3 text-center">
                @php $c = $run->status_color @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                    {{ $run->status_label }}
                </span>
            </td>
            <td class="px-4 py-3 text-right">
                <a href="{{ route('rh.paie.show', $run) }}" class="text-blue-600 hover:underline text-xs">Voir</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="9" class="px-4 py-12 text-center text-gray-400">Aucun bulletin de paie. Créez-en un pour commencer.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($runs->hasPages())
    <div class="px-4 py-3 border-t">{{ $runs->links() }}</div>
    @endif
</div>
@endsection
