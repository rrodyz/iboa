@extends('layouts.erp')
@section('title', 'Lots & Traçabilité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('stocks.index') }}" class="hover:text-gray-700">Stocks</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Lots &amp; Traçabilité</span>
@endsection

@section('content')
<div class="space-y-3">

    <x-x3.title-bar title="Lots &amp; Traçabilité" subtitle="{{ $kpi['lots'] }} lot(s) — stock par lot, réservé et disponible réel">
        <x-x3.btn href="{{ route('stocks.lots') }}">✕ Réinitialiser</x-x3.btn>
    </x-x3.title-bar>

    {{-- KPI — porte sur TOUT le résultat filtré, pas seulement la page affichée --}}
    <x-x3.synthesis cols="4">
        <x-x3.stat label="Lots" :value="number_format($kpi['lots'], 0, ',', ' ')" />
        <x-x3.stat label="Physique" :value="number_format($kpi['physical'], 2, ',', ' ')" unit="kg" />
        <x-x3.stat label="Réservé" :value="number_format($kpi['reserved'], 2, ',', ' ')" unit="kg" color="{{ $kpi['reserved'] > 0 ? 'amber' : 'gray' }}" />
        <x-x3.stat label="Disponible" :value="number_format($kpi['available'], 2, ',', ' ')" unit="kg" color="{{ $kpi['available'] < 0 ? 'red' : 'emerald' }}" />
    </x-x3.synthesis>

    {{-- Critères --}}
    <x-x3.section number="1" title="Critères de sélection">
        <form method="GET">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="lg:col-span-2">
                    <label class="block text-[11px] text-gray-500 mb-1">Recherche (lot, série, article)</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="LOT-001, référence, désignation…"
                           class="w-full h-8 border border-gray-300 rounded-[4px] px-2.5 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Article</label>
                    <select name="product_id" class="w-full h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">Tous les articles</option>
                        @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ (string) ($filters['product_id'] ?? '') === (string) $p->id ? 'selected' : '' }}>
                            {{ $p->name }} @if($p->reference) ({{ $p->reference }}) @endif
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Dépôt</label>
                    <select name="warehouse_id" class="w-full h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="">Tous les dépôts</option>
                        @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ (string) ($filters['warehouse_id'] ?? '') === (string) $wh->id ? 'selected' : '' }}>{{ $wh->name }}{{ $wh->code ? ' ('.$wh->code.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Statut</label>
                    <select name="status" class="w-full h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="" {{ ($filters['status'] ?? '') === '' ? 'selected' : '' }}>Tous</option>
                        <option value="disponible" {{ ($filters['status'] ?? '') === 'disponible' ? 'selected' : '' }}>Disponible</option>
                        <option value="reserve"    {{ ($filters['status'] ?? '') === 'reserve'    ? 'selected' : '' }}>Réservé</option>
                        <option value="expire"     {{ ($filters['status'] ?? '') === 'expire'     ? 'selected' : '' }}>Expiré</option>
                        <option value="consomme"   {{ ($filters['status'] ?? '') === 'consomme'   ? 'selected' : '' }}>Consommé</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Qualité</label>
                    <select name="quality_status" class="w-full h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="" {{ ($filters['quality_status'] ?? '') === '' ? 'selected' : '' }}>Tous</option>
                        <option value="recu"           {{ ($filters['quality_status'] ?? '') === 'recu'           ? 'selected' : '' }}>Reçu</option>
                        <option value="en_attente"     {{ ($filters['quality_status'] ?? '') === 'en_attente'     ? 'selected' : '' }}>En attente</option>
                        <option value="quarantaine"    {{ ($filters['quality_status'] ?? '') === 'quarantaine'    ? 'selected' : '' }}>Quarantaine</option>
                        <option value="libere"         {{ ($filters['quality_status'] ?? '') === 'libere'         ? 'selected' : '' }}>Libéré</option>
                        <option value="libere_partiel" {{ ($filters['quality_status'] ?? '') === 'libere_partiel' ? 'selected' : '' }}>Libéré partiel</option>
                        <option value="refuse"         {{ ($filters['quality_status'] ?? '') === 'refuse'         ? 'selected' : '' }}>Refusé</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] text-gray-500 mb-1">Disponibilité</label>
                    <select name="availability" class="w-full h-8 border border-gray-300 rounded-[4px] px-2 text-[12.5px] focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <option value="" {{ ($filters['availability'] ?? '') === '' ? 'selected' : '' }}>Tous</option>
                        <option value="with_stock" {{ ($filters['availability'] ?? '') === 'with_stock' ? 'selected' : '' }}>Avec stock</option>
                        <option value="exhausted"  {{ ($filters['availability'] ?? '') === 'exhausted'  ? 'selected' : '' }}>Épuisés</option>
                    </select>
                </div>

                <label class="flex items-center gap-1.5 text-[12px] text-gray-700 cursor-pointer self-end pb-1.5">
                    <input type="checkbox" name="expiring_soon" value="1" {{ !empty($filters['expiring_soon']) ? 'checked' : '' }}
                           class="w-3.5 h-3.5 text-orange-500 rounded">
                    <span>Expire bientôt (30j)</span>
                </label>

                <div class="flex gap-1.5 items-end">
                    <button type="submit" class="h-8 flex-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12.5px] font-medium rounded-[4px] transition-colors">
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </x-x3.section>

    {{-- Avertissement réservations génériques non affectées à un lot précis --}}
    @if($genericReservations->isNotEmpty())
    <div class="bg-amber-50 border border-amber-200 rounded-[4px] px-3 py-2 text-[12px] text-amber-800">
        ⚠ Réservations non affectées à un lot précis détectées pour {{ $genericReservations->count() }} couple(s) article/dépôt
        (vente/production sur article fini loté — hors périmètre P1-D lot/bobine) : le « disponible » de ces lignes de lot
        n'inclut pas ces réservations globales, consultez la fiche stock article pour la vue consolidée.
    </div>
    @endif

    {{-- Tableau --}}
    <x-x3.section number="2" title="Détail par lot" flush>
        <x-slot:meta>{{ $lots->total() }} lot(s)</x-slot:meta>
        <div class="overflow-x-auto">
            <table class="tbl">
                <thead>
                    <tr>
                        <th class="text-left">Article</th>
                        <th class="text-left">Lot</th>
                        <th class="text-left hidden md:table-cell">Bobine / Série</th>
                        <th class="text-left">Dépôt</th>
                        <th class="text-right">Physique</th>
                        <th class="text-right">Réservé</th>
                        <th class="text-right">Disponible</th>
                        <th class="text-right hidden lg:table-cell">Coût unit.</th>
                        <th class="text-right hidden lg:table-cell">Valeur</th>
                        <th class="text-center hidden md:table-cell">Qualité / Valorisation</th>
                        <th class="text-left hidden xl:table-cell">Péremption</th>
                        <th class="text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lots as $lot)
                    @php
                        $physical = (float) $lot->quantity;
                        $reserved = (float) $lot->reserved_quantity;
                        $available = $physical - $reserved;
                        $daysLeft = $lot->daysUntilExpiry();
                        $unitCost = $lot->unit_cost !== null ? (float) $lot->unit_cost : null;
                        $value = $unitCost !== null ? $physical * $unitCost : null;
                        $statusClasses = [
                            'disponible' => 'bg-green-100 text-green-700',
                            'reserve'    => 'bg-blue-100 text-blue-700',
                            'expire'     => 'bg-red-100 text-red-700',
                            'consomme'   => 'bg-gray-100 text-gray-500',
                        ];
                        $qualityClasses = [
                            'quarantaine' => 'bg-orange-100 text-orange-700',
                            'refuse' => 'bg-red-100 text-red-700',
                            'libere' => 'bg-green-100 text-green-700',
                            'libere_partiel' => 'bg-amber-100 text-amber-700',
                            'en_attente' => 'bg-gray-100 text-gray-600',
                            'recu' => 'bg-gray-100 text-gray-600',
                        ];
                        // [P1-E FINAL MICRO-GATE] valuation_status juge le COÛT du lot pour
                        // la comptabilité (bloque la consommation indépendamment de la
                        // qualité, cf. CoilConsumptionService::consume()) — distinct de
                        // quality_status (aptitude physique). Vert = nominal, sinon anomalie.
                        $valuationClasses = [
                            'valorisation_manquante' => 'bg-red-100 text-red-700',
                            'bloque_comptabilite' => 'bg-red-100 text-red-700',
                        ];
                        $valuationClass = $valuationClasses[$lot->valuation_status] ?? 'bg-green-100 text-green-700';
                    @endphp
                    <tr class="{{ $lot->status === 'expire' || ($daysLeft !== null && $daysLeft < 0) ? '!bg-red-50' : ($daysLeft !== null && $daysLeft <= 30 ? '!bg-orange-50' : '') }}">
                        <td>
                            <span class="font-medium text-gray-900">{{ $lot->product?->name ?? '—' }}</span>
                            @if($lot->product?->reference)
                            <span class="text-[10.5px] text-gray-400 font-mono block">{{ $lot->product->reference }}</span>
                            @endif
                        </td>
                        <td class="font-mono text-emerald-800 font-semibold">{{ $lot->lot_number }}</td>
                        <td class="hidden md:table-cell text-[11px] text-gray-500">
                            @if($lot->coils->count() > 1)
                                <span class="font-medium text-gray-700">{{ $lot->coils->count() }} bobines</span>
                            @elseif($lot->coils->count() === 1)
                                <span class="font-mono">{{ $lot->coils->first()?->reference ?? '1 bobine' }}</span>
                            @elseif($lot->serial_number)
                                <span class="font-mono">{{ $lot->serial_number }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-gray-600">{{ $lot->warehouse?->name ?? '—' }}</td>
                        <td class="text-right font-mono tabular-nums font-semibold text-gray-900">{{ number_format($physical, 2, ',', ' ') }} <span class="text-gray-400 text-[10.5px]">kg</span></td>
                        <td class="text-right font-mono tabular-nums {{ $reserved > 0 ? 'text-amber-700 font-medium' : 'text-gray-400' }}">{{ number_format($reserved, 2, ',', ' ') }}</td>
                        <td class="text-right font-mono tabular-nums font-semibold {{ $available < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            {{ number_format($available, 2, ',', ' ') }}
                            @if($available < 0)<span class="text-[10px] font-bold ml-1">⚠</span>@endif
                        </td>
                        <td class="text-right hidden lg:table-cell font-mono tabular-nums text-gray-500">
                            {{ $unitCost !== null ? number_format($unitCost, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="text-right hidden lg:table-cell font-mono tabular-nums text-gray-700">
                            {{ $value !== null ? number_format($value, 0, ',', ' ') : '—' }}
                        </td>
                        <td class="text-center hidden md:table-cell">
                            <div class="flex flex-col items-center gap-0.5">
                                @if($lot->quality_status)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $qualityClasses[$lot->quality_status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $lot->qualityStatusLabel() }}
                                </span>
                                @else
                                <span class="text-gray-300">—</span>
                                @endif
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $valuationClass }}"
                                      @if($lot->valuation_reason) title="{{ $lot->valuation_reason }}" @endif>
                                    {{ $lot->valuationStatusLabel() }}
                                </span>
                            </div>
                        </td>
                        <td class="hidden xl:table-cell">
                            @if($lot->expiry_date)
                                <span class="{{ $daysLeft !== null && $daysLeft <= 0 ? 'text-red-600 font-semibold' : ($daysLeft !== null && $daysLeft <= 30 ? 'text-orange-600 font-medium' : 'text-gray-700') }}">
                                    {{ $lot->expiry_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-[3px] text-[10.5px] font-medium {{ $statusClasses[$lot->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $lot->statusLabel() }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-4 py-12 text-center text-gray-400 text-[12.5px]">Aucun lot trouvé.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lots->hasPages())
        <div class="px-3 py-2 border-t border-gray-200 bg-band/40">
            {{ $lots->links() }}
        </div>
        @endif
    </x-x3.section>

    <x-x3.footer module="Stocks — Lots &amp; Traçabilité" />

</div>
@endsection
