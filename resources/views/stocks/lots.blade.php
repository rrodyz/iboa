@extends('layouts.erp')
@section('title', 'Lots & Traçabilité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Lots & Traçabilité</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Lots & Traçabilité</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $lots->total() }} lot(s) trouvé(s)</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="Lot, série, article..."
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 lg:col-span-2">

            <select name="warehouse_id"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                <option value="">Tous les entrepôts</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ ($filters['warehouse_id'] ?? '') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>

            <select name="status"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
                <option value="disponible" {{ ($filters['status'] ?? 'disponible') === 'disponible' ? 'selected' : '' }}>Disponible</option>
                <option value="reserve"    {{ ($filters['status'] ?? '') === 'reserve'    ? 'selected' : '' }}>Réservé</option>
                <option value="expire"     {{ ($filters['status'] ?? '') === 'expire'     ? 'selected' : '' }}>Expiré</option>
                <option value="consomme"   {{ ($filters['status'] ?? '') === 'consomme'   ? 'selected' : '' }}>Consommé</option>
                <option value=""           {{ ($filters['status'] ?? '') === ''           ? 'selected' : '' }}>Tous</option>
            </select>

            <div class="flex gap-2 items-center">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="expiring_soon" value="1"
                           {{ !empty($filters['expiring_soon']) ? 'checked' : '' }}
                           class="w-4 h-4 text-orange-500 rounded">
                    <span>Expire bientôt (30j)</span>
                </label>
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="submit"
                    class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['search', 'warehouse_id', 'status', 'expiring_soon']))
            <a href="{{ route('stocks.lots') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg">
                Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Article</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N° Lot</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">N° Série</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Entrepôt</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Péremption</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lots as $lot)
                    @php
                        $daysLeft = $lot->daysUntilExpiry();
                        $rowClass = '';
                        if ($lot->status === 'expire' || ($daysLeft !== null && $daysLeft < 0)) {
                            $rowClass = 'bg-red-50';
                        } elseif ($daysLeft !== null && $daysLeft <= 30) {
                            $rowClass = 'bg-orange-50';
                        }
                        $statusClasses = [
                            'disponible' => 'bg-green-100 text-green-700',
                            'reserve'    => 'bg-blue-100 text-blue-700',
                            'expire'     => 'bg-red-100 text-red-700',
                            'consomme'   => 'bg-gray-100 text-gray-500',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors {{ $rowClass }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900">{{ $lot->product?->name ?? '—' }}</div>
                            @if($lot->product?->reference)
                            <div class="text-xs text-gray-400 font-mono">{{ $lot->product->reference }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-teal-700 font-semibold">{{ $lot->lot_number }}</td>
                        <td class="px-4 py-3 font-mono text-gray-500 text-xs hidden md:table-cell">{{ $lot->serial_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ $lot->warehouse?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900">
                            {{ number_format((float)$lot->quantity, 2, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3">
                            @if($lot->expiry_date)
                                <span class="{{ $daysLeft !== null && $daysLeft <= 0 ? 'text-red-600 font-semibold' : ($daysLeft !== null && $daysLeft <= 30 ? 'text-orange-600 font-medium' : 'text-gray-700') }}">
                                    {{ $lot->expiry_date->format('d/m/Y') }}
                                </span>
                                @if($daysLeft !== null && $daysLeft > 0 && $daysLeft <= 30)
                                <div class="text-xs text-orange-500">{{ $daysLeft }}j restant(s)</div>
                                @elseif($daysLeft !== null && $daysLeft <= 0)
                                <div class="text-xs text-red-500">Expiré</div>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$lot->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $lot->statusLabel() }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucun lot trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lots->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $lots->withQueryString()->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
