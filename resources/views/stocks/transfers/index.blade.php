@extends('layouts.erp')
@section('title', 'Transferts inter-dépôts')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Transferts</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Transferts inter-dépôts</h1>
            <p class="text-sm text-gray-500">Workflow 2 étapes : expédition source → réception destination.</p>
        </div>
        @can('stocks.adjust')
        <a href="{{ route('stocks.transfers.create') }}"
           class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouveau transfert
        </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Statut</label>
            <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach(['brouillon'=>'Brouillon','en_transit'=>'En transit','recu'=>'Reçu','annule'=>'Annulé'] as $k=>$v)
                <option value="{{ $k }}" {{ request('status')===$k ? 'selected':'' }}>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Dépôt source</label>
            <select name="from_warehouse_id" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach($warehouses as $w)<option value="{{ $w->id }}" {{ request('from_warehouse_id')==$w->id?'selected':''}}>{{ $w->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Dépôt destination</label>
            <select name="to_warehouse_id" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                @foreach($warehouses as $w)<option value="{{ $w->id }}" {{ request('to_warehouse_id')==$w->id?'selected':''}}>{{ $w->name }}</option>@endforeach
            </select>
        </div>
        @if(request()->hasAny(['status','from_warehouse_id','to_warehouse_id']))
        <a href="{{ route('stocks.transfers.index') }}" class="text-xs text-gray-400 hover:text-gray-600 underline">Réinitialiser</a>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">N°</th>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">De → Vers</th>
                    <th class="px-4 py-2 text-right">Articles</th>
                    <th class="px-4 py-2 text-center">Statut</th>
                    <th class="px-4 py-2 text-left">Créé par</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($transfers as $t)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono font-semibold text-blue-700">
                        <a href="{{ route('stocks.transfers.show', $t) }}">{{ $t->number }}</a>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $t->transfer_date?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">
                        <span class="text-gray-700">{{ $t->fromWarehouse?->name }}</span>
                        <span class="text-gray-400 mx-1">→</span>
                        <span class="text-gray-700">{{ $t->toWarehouse?->name }}</span>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ $t->items_count ?? $t->items->count() }}</td>
                    <td class="px-4 py-3 text-center">
                        @php $c = $t->statusColor(); @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
                            {{ $t->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $t->createdBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('stocks.transfers.show', $t) }}" class="text-xs text-blue-600 hover:underline">Détails →</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun transfert.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transfers->links() }}</div>
</div>
@endsection
