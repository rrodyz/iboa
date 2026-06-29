@extends('layouts.erp')
@section('title', 'Centres de coûts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Comptabilité analytique</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Centres de coûts / profit</h1>
        <p class="text-sm text-gray-500 mt-0.5">§12 CDC — Ventilation analytique par axe métier</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('analytique.rapport') }}" class="btn-secondary text-sm">
            Rapport de rentabilité
        </a>
        @can('analytic.manage')
        <a href="{{ route('analytique.centres-couts.create') }}" class="btn-primary text-sm">
            + Nouveau centre
        </a>
        @endcan
    </div>
</div>

<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Nom</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Solde (FCFA)</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Lignes</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($centers as $center)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono font-semibold text-gray-700">{{ $center->code }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $center->name }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $center->type === 'profit' ? 'bg-emerald-100 text-emerald-700' :
                           ($center->type === 'investment' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                        {{ $center->typeLabel() }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right font-mono font-semibold
                    {{ ($center->analytic_lines_sum_amount ?? 0) >= 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                    {{ number_format(abs($center->analytic_lines_sum_amount ?? 0), 0, ',', ' ') }}
                </td>
                <td class="px-4 py-3 text-right text-gray-500">{{ $center->analytic_lines_count ?? 0 }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('analytique.centres-couts.show', $center) }}"
                       class="text-indigo-600 hover:text-indigo-900 text-xs font-medium">Détail →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                    Aucun centre de coûts configuré.
                    @can('analytic.manage')
                    <a href="{{ route('analytique.centres-couts.create') }}" class="text-indigo-600 hover:underline ml-1">Créer le premier</a>
                    @endcan
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-gray-100">
        {{ $centers->links() }}
    </div>
</div>
@endsection
