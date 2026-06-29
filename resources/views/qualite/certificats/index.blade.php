@extends('layouts.erp')
@section('title', 'Certificats Qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Certificats Qualité</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Certificats Qualité</h1>
            <p class="text-sm text-gray-500 mt-0.5">§8 & §10 CDC — traçabilité et conformité matière</p>
        </div>
        @can('quality.manage')
        <a href="{{ route('qualite.certificats.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau certificat
        </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="text-xs font-medium text-gray-600 mb-1 block">Recherche</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="N°, fournisseur, lot..."
                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Type</label>
            <select name="type" class="rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                <option value="">Tous</option>
                @foreach($types as $val => $label)
                <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">Résultat</label>
            <select name="resultat" class="rounded-lg border-gray-300 text-sm focus:ring-green-500 focus:border-green-500">
                <option value="">Tous</option>
                @foreach($resultats as $val => $r)
                <option value="{{ $val }}" @selected(request('resultat') === $val)>{{ $r['label'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-700">Filtrer</button>
        <a href="{{ route('qualite.certificats.index') }}" class="px-4 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50">Réinitialiser</a>
    </form>

    {{-- Tableau --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N° Certificat</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Lot</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fournisseur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Résultat</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Validé</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($certificates as $cert)
                @php
                    $rc = $cert->resultat === 'conforme' ? 'bg-green-100 text-green-700' :
                         ($cert->resultat === 'non_conforme' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs font-medium text-gray-900">{{ $cert->number }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $cert->typeLabel() }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $cert->lot_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-700 max-w-[180px] truncate">{{ $cert->fournisseur ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $cert->date_certificat?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $rc }}">
                            {{ $cert->resultatLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($cert->validated_at)
                            <span class="inline-flex items-center gap-1 text-xs text-green-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                {{ $cert->validated_at->format('d/m/Y') }}
                            </span>
                        @else
                            <span class="text-xs text-gray-400">En attente</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('qualite.certificats.show', $cert) }}" class="text-xs text-indigo-600 hover:underline">Voir</a>
                            <a href="{{ route('qualite.certificats.pdf', $cert) }}" target="_blank" class="text-xs text-gray-500 hover:text-red-600">PDF</a>
                            @can('quality.manage')
                            <a href="{{ route('qualite.certificats.edit', $cert) }}" class="text-xs text-gray-500 hover:text-indigo-600">Modifier</a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">Aucun certificat trouvé.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($certificates->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $certificates->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
