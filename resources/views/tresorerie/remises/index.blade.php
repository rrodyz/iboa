@extends('layouts.erp')
@section('title', 'Remises en banque')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Remises en banque</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Remises en banque</h1>
        @can('treasury.write')
        <a href="{{ route('tresorerie.remises.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle remise
        </a>
        @endcan
    </div>

    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <select name="cash_account_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous les comptes</option>
                @foreach($bankAccounts as $ba)
                <option value="{{ $ba->id }}" {{ ($filters['cash_account_id'] ?? '') == $ba->id ? 'selected' : '' }}>{{ $ba->name }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
                <option value="">Tous statuts</option>
                <option value="brouillon" {{ ($filters['status'] ?? '') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="valide"    {{ ($filters['status'] ?? '') === 'valide'    ? 'selected' : '' }}>Validé</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Filtrer</button>
            @if(array_filter($filters))
            <a href="{{ route('tresorerie.remises.index') }}" class="border border-gray-300 text-gray-600 text-sm px-3 py-2 rounded-lg">✕</a>
            @endif
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N°</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Banque</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Source</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deposits as $d)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-mono font-semibold text-indigo-600">{{ $d->number }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $d->deposit_date?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $d->cashAccount?->name }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $d->sourceCashAccount?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-800">{{ number_format($d->total_amount, 0, ',', ' ') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $d->status === 'valide' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                                {{ $d->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('tresorerie.remises.show', $d) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Voir →</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400">Aucune remise en banque.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($deposits->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $deposits->links() }}</div>
        @endif
    </div>
</div>
@endsection
