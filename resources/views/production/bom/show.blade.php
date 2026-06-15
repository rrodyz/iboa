@extends('layouts.erp')
@section('title', 'Nomenclature '.$bom->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.bom.index') }}" class="hover:text-gray-700">Nomenclatures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $bom->name }}</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $bom->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $bom->product?->name ?? 'Sans produit fini' }} · {{ $bom->sheet_type ?? '—' }}</p>
        </div>
        @can('production.update')
        <a href="{{ route('production.bom.edit', $bom) }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg hover:bg-gray-50">Modifier</a>
        @endcan
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Paramètres de fabrication</h2>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-gray-500">Épaisseur</dt><dd class="text-gray-900">{{ $bom->thickness ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Largeur bobine</dt><dd class="text-gray-900">{{ $bom->coil_width ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Largeur utile</dt><dd class="text-gray-900">{{ $bom->usable_width ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Conso / mètre</dt><dd class="text-gray-900 font-mono">{{ number_format($bom->consumption_per_meter,4,',',' ') }} kg</dd></div>
            <div><dt class="text-gray-500">Taux chute std.</dt><dd class="text-gray-900">{{ number_format($bom->standard_waste_rate,2,',',' ') }} %</dd></div>
            <div><dt class="text-gray-500">Temps machine / u</dt><dd class="text-gray-900">{{ $bom->machine_time_per_unit ?? '—' }} min</dd></div>
            <div><dt class="text-gray-500">MO / u</dt><dd class="text-gray-900 font-mono">{{ number_format($bom->labor_per_unit,0,',',' ') }} F</dd></div>
            <div><dt class="text-gray-500">Statut</dt><dd>{{ $bom->is_active ? 'Active' : 'Inactive' }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Composants ({{ $bom->lines->count() }})</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Article</th>
                        <th class="text-left">Libellé</th>
                        <th class="text-right">Qté / mètre</th>
                        <th class="text-left">Unité</th>
                        <th class="text-right">Chute %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bom->lines as $l)
                    <tr>
                        <td class="text-gray-800">{{ $l->product?->name ?? '—' }}</td>
                        <td class="text-gray-600">{{ $l->label ?? '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($l->quantity_per_meter,4,',',' ') }}</td>
                        <td class="text-gray-600">{{ $l->unit?->abbreviation ?? $l->unit?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($l->waste_rate,2,',',' ') }} %</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Aucun composant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Explosion multi-niveaux --}}
    @if(isset($explosion) && count($explosion))
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Nomenclature multi-niveaux (explosion)</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Composant</th><th class="text-right">Quantité / unité</th><th class="text-left">Type</th></tr></thead>
                <tbody>
                    @foreach($explosion as $row)
                    <tr>
                        <td class="text-gray-800">
                            <span style="padding-left: {{ $row['depth'] * 18 }}px">{{ $row['depth'] > 0 ? '└ ' : '' }}{{ $row['label'] }}</span>
                        </td>
                        <td class="text-right font-mono tabular-nums text-gray-700">{{ number_format($row['quantity'], 4, ',', ' ') }}</td>
                        <td>
                            @if($row['is_semi_finished'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Semi-fini{{ $row['has_sub_bom'] ? ' ▼' : '' }}</span>
                            @else
                                <span class="text-gray-400 text-xs">Matière / composant</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-6 py-3 text-xs text-gray-400 border-t border-gray-100">Les composants semi-finis ▼ sont explosés selon leur propre nomenclature (assemblages charpentes/hangars).</p>
    </div>
    @endif
</div>
@endsection
