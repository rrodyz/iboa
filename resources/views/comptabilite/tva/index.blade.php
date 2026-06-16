@extends('layouts.erp')
@section('title', 'Déclarations TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Déclarations TVA</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Déclarations TVA</h1>
        @can('accounting.write')
        <a href="{{ route('comptabilite.tva.create') }}"
           class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle déclaration
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="soumis"    {{ ($filters['status'] ?? '') === 'soumis'    ? 'selected' : '' }}>Soumis</option>
                <option value="paye"      {{ ($filters['status'] ?? '') === 'paye'      ? 'selected' : '' }}>Payé</option>
            </select>
            <select name="period_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                <option value="">Toutes périodes</option>
                <option value="mensuel"      {{ ($filters['period_type'] ?? '') === 'mensuel'      ? 'selected' : '' }}>Mensuel</option>
                <option value="trimestriel"  {{ ($filters['period_type'] ?? '') === 'trimestriel'  ? 'selected' : '' }}>Trimestriel</option>
            </select>
            <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                <option value="">Toutes années</option>
                @foreach(range(date('Y'), date('Y') - 3) as $y)
                <option value="{{ $y }}" {{ ($filters['year'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Filtrer</button>
                @if(array_filter($filters))
                <a href="{{ route('comptabilite.tva.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">N°</th>
                        <th class="text-left">Période</th>
                        <th class="text-left">Type</th>
                        <th class="text-right">TVA Collectée</th>
                        <th class="text-right">TVA Déductible</th>
                        <th class="text-right">TVA Due</th>
                        <th class="text-right">Reste à payer</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($declarations as $d)
                    <tr>
                        <td class="font-mono font-semibold text-violet-600">{{ $d->number }}</td>
                        <td>
                            <p class="font-medium text-gray-800">{{ $d->period_label }}</p>
                            <p class="text-xs text-gray-400">{{ $d->period_start?->format('d/m/Y') }} → {{ $d->period_end?->format('d/m/Y') }}</p>
                        </td>
                        <td class="text-gray-500 capitalize">{{ $d->period_type }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($d->tva_collectee, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($d->tva_deductible, 0, ',', ' ') }}</td>
                        <td class="text-right tabular-nums font-semibold {{ $d->tva_due > 0 ? 'text-red-600' : 'text-gray-400' }}">
                            {{ $d->tva_due > 0 ? number_format($d->tva_due, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="text-right tabular-nums font-semibold {{ $d->remaining > 0 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $d->remaining > 0 ? number_format($d->remaining, 0, ',', ' ') : '✓ 0' }}
                        </td>
                        <td>
                            @php $colors = ['brouillon' => 'bg-gray-100 text-gray-700', 'soumis' => 'bg-blue-100 text-blue-700', 'paye' => 'bg-green-100 text-green-700']; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$d->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $d->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('comptabilite.tva.show', $d) }}" class="text-violet-600 hover:text-violet-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center text-gray-400">Aucune déclaration TVA.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($declarations->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $declarations->links() }}</div>
        @endif
    </div>

</div>
@endsection
