@extends('layouts.erp')
@section('title', 'Bulletins de paie')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">RH – Paie</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Bulletins de Paie</h1>
        <p class="text-sm text-gray-500 mt-1">Gestion mensuelle de la paie CNSS + IUTS</p>
    </div>
    <a href="{{ route('rh.paie.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nouveau bulletin
    </a>
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
