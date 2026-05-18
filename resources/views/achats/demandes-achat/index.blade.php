@extends('layouts.erp')
@section('title', 'Demandes d\'achat')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Demandes d'achat</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Demandes d'achat</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $requests->total() }} demande(s)</p>
        </div>
        @can('purchase_requests.create')
        <a href="{{ route('achats.demandes-achat.create') }}"
           class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 self-start transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouvelle demande
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Numéro, demandeur..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">

            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                <option value="">Tous les statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="soumis"    {{ ($filters['status'] ?? '') === 'soumis'    ? 'selected' : '' }}>Soumis</option>
                <option value="approuve"  {{ ($filters['status'] ?? '') === 'approuve'  ? 'selected' : '' }}>Approuvé</option>
                <option value="rejete"    {{ ($filters['status'] ?? '') === 'rejete'    ? 'selected' : '' }}>Rejeté</option>
                <option value="converti"  {{ ($filters['status'] ?? '') === 'converti'  ? 'selected' : '' }}>Converti</option>
            </select>

            <input type="text" name="department" value="{{ $filters['department'] ?? '' }}" placeholder="Département..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500">

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Filtrer
                </button>
                @if(request()->hasAny(['search', 'status', 'department']))
                <a href="{{ route('achats.demandes-achat.index') }}"
                   class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">✕</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Demandeur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Département</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden lg:table-cell">Date souhaitée</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant estimé</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($requests as $req)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('achats.demandes-achat.show', $req) }}"
                               class="font-mono font-semibold text-amber-600 hover:text-amber-800">
                                {{ $req->number }}
                            </a>
                            @if($req->justification)
                            <p class="text-xs text-gray-400">{{ \Illuminate\Support\Str::limit($req->justification, 35) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700 hidden md:table-cell">{{ $req->requestedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ $req->department ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">
                            @if($req->needed_at)
                                @php $urgent = $req->needed_at->isPast() && !in_array($req->status, ['approuve','converti','annule']); @endphp
                                <span class="{{ $urgent ? 'text-red-600 font-medium' : '' }}">
                                    {{ $req->needed_at->format('d/m/Y') }}
                                </span>
                                @if($urgent)
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Urgent</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-900">
                            {{ $req->total_estimated > 0 ? number_format($req->total_estimated, 0, ',', ' ') . ' FCFA' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php $color = $req->statusColor(); @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
                                {{ $req->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('achats.demandes-achat.show', $req) }}"
                                   class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-colors" title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                @if($req->status === 'brouillon')
                                @can('purchase_requests.submit')
                                <form action="{{ route('achats.demandes-achat.submit', $req) }}" method="POST"
                                      onsubmit="return confirm('Soumettre la demande {{ addslashes($req->number) }} pour approbation ?')">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Soumettre">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                                @endif
                                @if($req->status === 'soumis')
                                @can('purchase_requests.approve')
                                <form action="{{ route('achats.demandes-achat.approve', $req) }}" method="POST"
                                      onsubmit="return confirm('Approuver la demande {{ addslashes($req->number) }} ?')">
                                    @csrf
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded transition-colors" title="Approuver">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </button>
                                </form>
                                @endcan
                                @endif
                                @if($req->isEditable())
                                <form action="{{ route('achats.demandes-achat.destroy', $req) }}" method="POST"
                                      onsubmit="return confirm('Supprimer cette demande ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-gray-400 text-sm">Aucune demande d'achat trouvée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $requests->appends($filters)->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
