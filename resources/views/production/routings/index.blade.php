@extends('layouts.erp')
@section('title', 'Gammes opératoires')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Gammes opératoires</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Gammes opératoires</h1>
            <p class="text-sm text-gray-500 mt-0.5">Séquences d'opérations par nomenclature — temps & centres de travail</p>
        </div>
        @can('production.create')
        <a href="{{ route('production.routings.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle gamme
        </a>
        @endcan
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Code</th><th class="text-left">Nom</th><th class="text-left">Nomenclature</th><th class="text-right">Opérations</th><th class="text-left">Statut</th><th></th></tr></thead>
                <tbody>
                    @forelse($routings as $r)
                    <tr class="{{ $r->is_active ? '' : 'opacity-50' }}">
                        <td class="font-mono text-xs text-indigo-600">{{ $r->code }}</td>
                        <td class="text-gray-800 font-medium">{{ $r->name }}</td>
                        <td class="text-gray-600">{{ $r->billOfMaterial?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-500">{{ $r->operations_count }}</td>
                        <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $r->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')<a href="{{ route('production.routings.edit', $r) }}" class="text-indigo-600 hover:underline text-xs font-medium">Modifier</a>@endcan
                            @can('production.delete')<form method="POST" action="{{ route('production.routings.destroy', $r) }}" class="inline ml-2" data-confirm="Supprimer cette gamme ?">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-xs">Suppr.</button></form>@endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucune gamme opératoire.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($routings->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $routings->links() }}</div>@endif
    </div>
</div>
@endsection
