@extends('layouts.erp')
@section('title', 'Alertes réapprovisionnement')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.dashboard') }}" class="hover:text-gray-700">Stock</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Alertes réappro</span>
@endsection

@section('content')
@php $fmt = fn($n) => number_format((int) $n, 0, ',', ' '); @endphp

<div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">⚠ Alertes de réapprovisionnement</h1>
            <p class="text-sm text-gray-500">{{ $alerts->total() }} article(s) sous le point de réappro.</p>
        </div>
        @if($alerts->total() > 0)
        @can('purchase_orders.create')
        <a href="{{ route('achats.dashboard.restock-po') }}"
           class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Générer PO d'achat
        </a>
        @endcan
        @endif
    </div>

    @if($alerts->isEmpty())
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-6 text-center text-emerald-700 text-sm">
            ✓ Aucune alerte de réappro — tous les articles sont au-dessus de leur point de réapprovisionnement.
        </div>
    @else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2 text-left">Article</th>
                    <th class="px-4 py-2 text-left">Fournisseur</th>
                    <th class="px-4 py-2 text-right">Disponible</th>
                    <th class="px-4 py-2 text-right">Réappro</th>
                    <th class="px-4 py-2 text-right">Stock max</th>
                    <th class="px-4 py-2 text-right">À commander</th>
                    <th class="px-4 py-2 text-right">Coût estimé</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($alerts as $row)
                @php
                    $unitCost = $row->last_purchase_price ?: $row->purchase_price ?: 0;
                    $estimated = (int) ($unitCost * $row->suggested_qty);
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2">
                        <a href="{{ route('stocks.show', $row->id) }}" class="font-mono text-xs text-blue-700 hover:underline">{{ $row->reference }}</a>
                        <p class="text-sm text-gray-900">{{ $row->name }}</p>
                        @if($row->warehouse_name)
                        <p class="text-xs text-gray-400">📦 {{ $row->warehouse_name }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-xs">
                        @if($row->supplier_name)
                            <a href="#" class="text-gray-700">{{ $row->supplier_name }}</a>
                        @else
                            <span class="text-gray-400 italic">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums {{ $row->available_qty <= 0 ? 'text-red-600 font-semibold' : 'text-orange-700' }}">
                        {{ number_format($row->available_qty, 0, ',', ' ') }}
                        @if($row->reserved_quantity > 0)
                            <span class="text-xs text-gray-400">({{ number_format($row->quantity, 0, ',', ' ') }} − {{ number_format($row->reserved_quantity, 0, ',', ' ') }} rés.)</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $row->reorder_point }}</td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-500">{{ $row->stock_max ?: '—' }}</td>
                    <td class="px-4 py-2 text-right tabular-nums font-semibold text-emerald-700">
                        {{ number_format($row->suggested_qty, 0, ',', ' ') }}
                    </td>
                    <td class="px-4 py-2 text-right tabular-nums text-gray-700">
                        {{ $fmt($estimated) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $alerts->links() }}</div>
    @endif

    {{-- Légende --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
        <p class="font-medium mb-1">💡 Comment ça marche</p>
        <ul class="list-disc list-inside space-y-0.5 text-blue-700 text-xs">
            <li><strong>Disponible</strong> = stock physique − réservé (devis / commandes).</li>
            <li><strong>Point de réappro</strong> = seuil défini par article. Quand on descend dessous, on déclenche l'achat.</li>
            <li><strong>À commander</strong> = qté suggérée pour remonter au stock max (ou réappro + 1 si max non défini).</li>
            <li><strong>Coût estimé</strong> = qté suggérée × dernier prix d'achat (ou prix d'achat catalogue).</li>
        </ul>
    </div>
</div>
@endsection
