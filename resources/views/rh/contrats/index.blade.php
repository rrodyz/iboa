@extends('layouts.erp')
@section('title', 'Contrats — RH')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Contrats</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Contrats de travail</h1>
        <p class="text-sm text-gray-500 mt-1">Liste de tous les contrats de l'entreprise</p>
    </div>
    <a href="{{ route('rh.employes.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Ajouter via Employé
    </a>
</div>

{{-- Filtres --}}
<form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
        <option value="">Tous statuts</option>
        @foreach($statusOptions as $v => $l)
            <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
        @endforeach
    </select>
    <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
        <option value="">Tous types</option>
        @foreach($typeOptions as $v => $l)
            <option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>
        @endforeach
    </select>
    <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">
        Filtrer
    </button>
    <a href="{{ route('rh.contrats.index') }}" class="px-4 py-2 text-gray-500 rounded-lg text-sm hover:bg-gray-100">
        Réinitialiser
    </a>
</form>

{{-- Stats rapides --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    @php
        $all      = $contracts->total();
        $actifs   = \App\Models\EmployeeContract::whereHas('employee', fn($q) => $q->where('company_id', $company->id))->where('status','actif')->count();
        $expires  = \App\Models\EmployeeContract::whereHas('employee', fn($q) => $q->where('company_id', $company->id))->where('status','expire')->count();
        $resilies = \App\Models\EmployeeContract::whereHas('employee', fn($q) => $q->where('company_id', $company->id))->where('status','resilie')->count();
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="text-xs text-gray-500 mb-1">Total</div>
        <div class="text-2xl font-bold text-gray-900">{{ $all }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="text-xs text-gray-500 mb-1">Actifs</div>
        <div class="text-2xl font-bold text-emerald-600">{{ $actifs }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="text-xs text-gray-500 mb-1">Expirés</div>
        <div class="text-2xl font-bold text-amber-600">{{ $expires }}</div>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="text-xs text-gray-500 mb-1">Résiliés</div>
        <div class="text-2xl font-bold text-red-600">{{ $resilies }}</div>
    </div>
</div>

{{-- Tableau --}}
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Matricule</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Employé</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Début</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-700">Fin</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-700">Salaire de base</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Statut</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-700">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($contracts as $contract)
                @php
                    $badgeClass = match($contract->status) {
                        'actif'   => 'bg-emerald-100 text-emerald-800',
                        'expire'  => 'bg-amber-100 text-amber-800',
                        'resilie' => 'bg-red-100 text-red-800',
                        default   => 'bg-gray-100 text-gray-800',
                    };
                    $badgeLabel = match($contract->status) {
                        'actif'   => 'Actif',
                        'expire'  => 'Expiré',
                        'resilie' => 'Résilié',
                        default   => ucfirst($contract->status),
                    };
                @endphp
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">
                        {{ $contract->employee?->matricule ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('rh.employes.show', $contract->employee_id) }}"
                           class="font-medium text-gray-900 hover:text-indigo-600">
                            {{ $contract->employee?->full_name ?? '—' }}
                        </a>
                        @if($contract->employee?->department)
                        <div class="text-xs text-gray-400">{{ $contract->employee->department }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-700">
                        <span class="font-semibold text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-100">
                            {{ $contract->type }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $contract->start_date?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($contract->end_date)
                            {{ $contract->end_date->format('d/m/Y') }}
                            @if($contract->status === 'actif' && $contract->end_date->isPast())
                                <span class="ml-1 text-xs text-amber-600 font-medium">(dépassé)</span>
                            @elseif($contract->status === 'actif' && $contract->end_date->diffInDays(now()) <= 30)
                                <span class="ml-1 text-xs text-orange-600 font-medium">(bientôt)</span>
                            @endif
                        @else
                            <span class="text-gray-400">Indéterminée</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-medium text-gray-900">
                        {{ number_format($contract->base_salary, 0, ',', ' ') }} F
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                            {{ $badgeLabel }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('rh.employes.show', $contract->employee_id) }}"
                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                            Voir employé
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 italic">
                        Aucun contrat trouvé avec ces critères.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($contracts->hasPages())
    <div class="px-4 py-3 border-t border-gray-200">
        {{ $contracts->links() }}
    </div>
    @endif
</div>
@endsection
