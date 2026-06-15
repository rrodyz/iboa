@extends('layouts.erp')
@section('title', 'Nomenclatures (BOM)')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nomenclatures</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Nomenclatures de fabrication</h1>
            <p class="text-sm text-gray-500 mt-0.5">Recettes tôle bac : consommation/m, taux de chute, temps machine</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.bom.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle nomenclature
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Nom</th>
                        <th class="text-left">Produit fini</th>
                        <th class="text-left">Type tôle</th>
                        <th class="text-right">Conso/m</th>
                        <th class="text-right">Chute std.</th>
                        <th class="text-right">Composants</th>
                        <th class="text-left">Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $b)
                    <tr class="{{ $b->is_active ? '' : 'opacity-50' }}">
                        <td class="text-gray-800 font-medium">
                            <a href="{{ route('production.bom.show', $b) }}" class="hover:underline">{{ $b->name }}</a>
                        </td>
                        <td class="text-gray-600">{{ $b->product?->name ?? '—' }}</td>
                        <td class="text-gray-600">{{ $b->sheet_type ?? '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-700">{{ number_format($b->consumption_per_meter,4,',',' ') }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($b->standard_waste_rate,2,',',' ') }} %</td>
                        <td class="text-right tabular-nums text-gray-500">{{ $b->lines_count }}</td>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $b->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $b->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            <a href="{{ route('production.bom.edit', $b) }}" class="text-indigo-600 hover:underline text-xs font-medium">Modifier</a>
                            @endcan
                            @can('production.delete')
                            <form method="POST" action="{{ route('production.bom.destroy', $b) }}" class="inline ml-2" data-confirm="Supprimer cette nomenclature ?">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">Aucune nomenclature.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($boms->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $boms->links() }}</div>@endif
    </div>
</div>
@endsection
