@extends('layouts.erp')
@section('title', 'Tableau de bord MTO — production à la commande')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Tableau de bord MTO</span>
@endsection

@section('content')
<div class="space-y-3">

    {{-- ═══ Bandeau SAGE X3 ═══ --}}
    <div class="bg-white border border-gray-300 rounded-[4px]">
        <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-b from-gray-50 to-white flex-wrap gap-2">
            <div>
                <h2 class="text-[22px] font-bold text-gray-900 leading-tight">Tableau de bord MTO — production à la commande</h2>
                <p class="text-[11.5px] text-gray-400">Toute ligne de commande fabriquée à la commande (tôle bac), avec ou sans OF déjà créé.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('production.orders.eligible') }}"
                   class="text-[14px] font-semibold text-emerald-700 border border-emerald-300 bg-white hover:bg-emerald-50 px-5 py-2 rounded-[4px] transition-colors">Éligibles sans OF</a>
                <a href="{{ route('production.orders.mts') }}"
                   class="text-[14px] font-semibold text-gray-500 hover:text-gray-700 border border-gray-300 bg-white hover:bg-gray-50 px-5 py-2 rounded-[4px] transition-colors">Planification MTS</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[4px] border border-gray-300 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-[#eef5f0] border-b border-gray-300">
                <tr>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Commande</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Client</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Article</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Commandée</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Stock dispo.</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Produite</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Restant à produire</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">OF existant</th>
                    <th class="px-3 py-1.5 text-center text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Statut financier</th>
                    <th class="px-3 py-1.5 text-left text-[11px] font-bold text-emerald-900 uppercase tracking-wide">Date requise</th>
                    <th class="px-3 py-1.5 text-right text-[11px] font-bold text-emerald-900 uppercase tracking-wide w-32"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($rows as $r)
                <tr class="hover:bg-[#eef5f0]/40">
                    <td class="px-3 py-1.5">
                        <a href="{{ route('ventes.commandes.show', $r['order']->id) }}" class="font-semibold text-emerald-800 hover:underline">{{ $r['order']->number }}</a>
                    </td>
                    <td class="px-3 py-1.5 text-gray-900">{{ $r['order']->client?->trade_name ?? $r['order']->client?->name ?? '—' }}</td>
                    <td class="px-3 py-1.5">
                        <span class="text-gray-900">{{ $r['product']->name }}</span>
                        <span class="text-[11px] text-gray-400 font-mono ml-1">{{ $r['product']->reference }}</span>
                    </td>
                    <td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($r['commandee'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums {{ $r['dispo'] <= 0 ? 'text-red-600' : '' }}">{{ number_format($r['dispo'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums text-blue-700">{{ number_format($r['produite'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5 text-right tabular-nums font-bold {{ $r['restante'] > 0 ? 'text-amber-700' : 'text-gray-400' }}">{{ number_format($r['restante'], 0, ',', ' ') }}</td>
                    <td class="px-3 py-1.5">
                        @if($r['of'])
                            <a href="{{ route('production.orders.show', $r['of']->id) }}" class="text-emerald-800 hover:underline font-medium">{{ $r['of']->number }}</a>
                            <span class="text-[10.5px] text-gray-400 ml-1">{{ $r['of']->status }}</span>
                        @else
                            <span class="text-gray-400 text-[12px]">Aucun</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-center">
                        @if($r['eligible'])
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Éligible</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600">Non couverte</span>
                        @endif
                    </td>
                    <td class="px-3 py-1.5 text-gray-600 text-[12px]">{{ optional($r['order']->delivery_date)->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-3 py-1.5 text-right">
                        @can('production.create')
                        @if($r['restante'] > 0 && $r['eligible'])
                        <a href="{{ route('production.orders.create', ['order_id' => $r['order']->id, 'product_id' => $r['product']->id, 'qty' => $r['restante']]) }}"
                           class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white text-[12px] font-semibold rounded-[4px]">Créer OF</a>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="px-3 py-6 text-center text-gray-400 text-[12.5px]">Aucune ligne MTO ouverte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Barre de contexte pied de page [X3] ─────────────────────────────── --}}
    <div class="bg-[#232a30] text-gray-300 rounded-[4px] px-4 py-2 flex flex-wrap items-center gap-x-6 gap-y-1 text-[12px]">
        <span>Société : <span class="text-white font-semibold">{{ currentCompany()?->name }}</span></span>
        <span class="border-l border-white/10 pl-6">Site : <span class="text-white font-semibold">01</span></span>
        <span class="border-l border-white/10 pl-6">Module : <span class="text-white font-semibold">production — tableau de bord MTO</span></span>
        <span class="ml-auto">Utilisateur : <span class="text-white font-semibold">{{ auth()->user()->name }}</span></span>
        <span class="border-l border-white/10 pl-6 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
    </div>
</div>
@endsection
