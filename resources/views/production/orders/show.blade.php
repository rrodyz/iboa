@extends('layouts.erp')
@section('title', 'OF '.$order->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.orders.index') }}" class="hover:text-gray-700">Ordres de fabrication</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $order->number }}</span>
@endsection

@section('content')
<div class="max-w-4xl mx-auto space-y-5" x-data="{ cancelOpen: false }">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $order->number }}</h1>
                @php $sc = match($order->status){ 'brouillon'=>'bg-gray-100 text-gray-600','lance'=>'bg-amber-100 text-amber-700','en_cours'=>'bg-sky-100 text-sky-700','termine'=>'bg-green-100 text-green-700',default=>'bg-red-100 text-red-700' }; @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $order->statusLabel() }}</span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">{{ $order->client?->trade_name ?? $order->client?->name ?? 'Sans client' }} · {{ $order->product?->name ?? '—' }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($order->isEditable())
                @can('production.create')
                <a href="{{ route('production.orders.edit', $order) }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg hover:bg-gray-50">Modifier</a>
                @endcan
            @endif

            @if($order->status === 'brouillon')
                @can('production.launch')
                <form method="POST" action="{{ route('production.orders.allocate', $order) }}">@csrf
                    <button class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-4 py-2 rounded-lg">Allouer la matière</button>
                </form>
                <form method="POST" action="{{ route('production.orders.launch', $order) }}">@csrf
                    <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Lancer l'OF</button>
                </form>
                @endcan
            @elseif($order->status === 'matiere_allouee')
                @can('production.launch')
                <form method="POST" action="{{ route('production.orders.launch', $order) }}">@csrf
                    <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Lancer l'OF</button>
                </form>
                @endcan
            @elseif($order->status === 'lance')
                @can('production.launch')
                <form method="POST" action="{{ route('production.orders.start', $order) }}">@csrf
                    <button class="bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Démarrer production</button>
                </form>
                @endcan
            @elseif(in_array($order->status, ['en_cours','termine_partiellement']))
                @can('production.validate')
                @if($order->status === 'en_cours')
                <form method="POST" action="{{ route('production.orders.partial', $order) }}">@csrf
                    <button class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Terminer partiellement</button>
                </form>
                @endif
                <form method="POST" action="{{ route('production.orders.finish', $order) }}">@csrf
                    <button class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Terminer l'OF</button>
                </form>
                @endcan
            @endif

            @if(!in_array($order->status, ['termine','annule']))
                @can('production.cancel')
                <button type="button" @click="cancelOpen = true" class="border border-red-200 text-red-600 text-sm px-4 py-2 rounded-lg hover:bg-red-50">Annuler</button>
                @endcan
            @endif
        </div>
    </div>

    {{-- ══ Chaîne de production (Commande → Comptabilisation) ══ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Chaîne de production</h2>
        <div class="flex flex-wrap gap-y-3">
            @foreach($workflow as $i => $s)
            @php
                [$dot, $txt, $ico] = match($s['state']) {
                    'done'    => ['bg-green-500 text-white', 'text-gray-900 font-medium', '✓'],
                    'current' => ['bg-sky-500 text-white animate-pulse', 'text-sky-700 font-semibold', '●'],
                    'blocked' => ['bg-red-500 text-white', 'text-red-700 font-semibold', '✕'],
                    'pending' => ['bg-gray-200 text-gray-500', 'text-gray-400', $i+1],
                    default   => ['bg-gray-100 text-gray-300', 'text-gray-300', '–'],
                };
            @endphp
            <div class="flex items-center {{ !$loop->last ? 'flex-1 min-w-[140px]' : '' }}">
                <div class="flex flex-col items-center text-center px-1">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold {{ $dot }}">{{ $ico }}</span>
                    @if($s['link'])
                        <a href="{{ $s['link'] }}" class="text-[11px] mt-1 leading-tight max-w-[90px] {{ $txt }} hover:underline">{{ $s['label'] }}</a>
                    @else
                        <span class="text-[11px] mt-1 leading-tight max-w-[90px] {{ $txt }}">{{ $s['label'] }}</span>
                    @endif
                </div>
                @if(!$loop->last)
                <div class="flex-1 h-0.5 mx-1 {{ $s['state'] === 'done' ? 'bg-green-300' : 'bg-gray-200' }}"></div>
                @endif
            </div>
            @endforeach
        </div>
        @php $blocked = collect($workflow)->firstWhere('state', 'blocked'); @endphp
        @if($blocked)
        <p class="mt-3 text-xs text-red-600">⛔ Bloqué à l'étape « {{ $blocked['label'] }} ».</p>
        @endif
    </div>

    {{-- Frise workflow --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between text-xs">
            @php $steps = ['brouillon'=>'Brouillon','lance'=>'Lancé','en_cours'=>'En cours','termine'=>'Terminé']; $order_idx = array_search($order->status, array_keys($steps)); @endphp
            @foreach($steps as $k => $label)
                @php $i = array_search($k, array_keys($steps)); $done = $order->status!=='annule' && $order_idx!==false && $i <= $order_idx; @endphp
                <div class="flex items-center gap-2 flex-1 {{ !$loop->last ? 'after:content-[\'\'] after:flex-1 after:h-px after:mx-2 after:'.($done && !$loop->last ? 'bg-indigo-300':'bg-gray-200') : '' }}">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold {{ $done ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-400' }}">{{ $i+1 }}</span>
                    <span class="{{ $done ? 'text-gray-900 font-medium' : 'text-gray-400' }}">{{ $label }}</span>
                </div>
            @endforeach
        </div>
        @if($order->status === 'annule')
            <p class="mt-3 text-sm text-red-600 font-medium">Cet ordre a été annulé.</p>
        @endif
    </div>

    {{-- Indicateurs production --}}
    @if($order->consumptions->isNotEmpty() || $order->outputs->isNotEmpty() || $order->wastes->isNotEmpty())
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Matière consommée</p>
            <p class="text-base font-bold text-gray-900 tabular-nums mt-1">{{ number_format($metrics['consumed_weight'],2,',',' ') }} kg</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Coût matière</p>
            <p class="text-base font-bold text-gray-900 tabular-nums mt-1">{{ number_format($metrics['consumed_cost'],0,',',' ') }} F</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Produit (mètres)</p>
            <p class="text-base font-bold text-gray-900 tabular-nums mt-1">{{ number_format($metrics['output_meters'],2,',',' ') }} m</p>
        </div>
        <div class="bg-white border border-gray-100 shadow-sm rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">Chutes</p>
            <p class="text-base font-bold text-gray-900 tabular-nums mt-1">{{ number_format($metrics['waste_weight'],2,',',' ') }} kg</p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <p class="text-xs text-emerald-600 uppercase tracking-wider">Rendement matière</p>
            <p class="text-base font-bold text-emerald-800 tabular-nums mt-1">{{ $metrics['yield'] !== null ? number_format($metrics['yield'],1,',',' ').' %' : '—' }}</p>
        </div>
    </div>
    @endif

    {{-- Caractéristiques --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Caractéristiques</h2>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-gray-500">Type de tôle</dt><dd class="text-gray-900">{{ $order->sheet_type ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Épaisseur</dt><dd class="text-gray-900">{{ $order->thickness ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Couleur</dt><dd class="text-gray-900">{{ $order->color ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Largeur utile</dt><dd class="text-gray-900">{{ $order->usable_width ?? '—' }} mm</dd></div>
            <div><dt class="text-gray-500">Qté demandée</dt><dd class="text-gray-900 font-mono">{{ number_format($order->quantity_requested,0,',',' ') }}</dd></div>
            <div><dt class="text-gray-500">Qté produite</dt><dd class="text-gray-900 font-mono">{{ number_format($order->quantity_produced,0,',',' ') }}</dd></div>
            <div><dt class="text-gray-500">Nomenclature</dt><dd class="text-gray-900">{{ $order->billOfMaterial?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Ligne</dt><dd class="text-gray-900">{{ $order->productionLine?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Commande</dt><dd class="text-gray-900 font-mono">{{ $order->order?->number ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Responsable</dt><dd class="text-gray-900">{{ $order->responsible?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Lancé le</dt><dd class="text-gray-900">{{ optional($order->launched_at)->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Terminé le</dt><dd class="text-gray-900">{{ optional($order->finished_at)->format('d/m/Y') ?? '—' }}</dd></div>
        </dl>
        @if($order->notes)
        <div class="mt-4 pt-4 border-t border-gray-100">
            <dt class="text-gray-500 text-sm">Notes</dt>
            <dd class="text-gray-700 text-sm whitespace-pre-line mt-1">{{ $order->notes }}</dd>
        </div>
        @endif
    </div>

    {{-- Détail coupes --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Détail des coupes ({{ $order->lines->count() }})</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        <th class="text-left">Libellé</th>
                        <th class="text-right">Longueur (m)</th>
                        <th class="text-right">Quantité</th>
                        <th class="text-right">Total mètres</th>
                        <th class="text-left">Unité</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->lines as $l)
                    <tr>
                        <td class="text-gray-800">{{ $l->label ?? '—' }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-700">{{ number_format($l->length,2,',',' ') }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-700">{{ number_format($l->quantity,0,',',' ') }}</td>
                        <td class="text-right font-mono tabular-nums text-gray-900 font-semibold">{{ number_format($l->total_meters,2,',',' ') }}</td>
                        <td class="text-gray-600">{{ $l->unit?->abbreviation ?? $l->unit?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Aucune coupe détaillée.</td></tr>
                    @endforelse
                </tbody>
                @if($order->lines->isNotEmpty())
                <tfoot>
                    <tr class="font-semibold bg-gray-50">
                        <td colspan="3" class="text-right text-gray-500">Total mètres linéaires</td>
                        <td class="text-right font-mono tabular-nums text-gray-900">{{ number_format($order->totalMeters(),2,',',' ') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    @php $live = $order->isInProgress() && auth()->user()->can('production.update'); @endphp

    {{-- ══ Consommation matière ══ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Consommation matière</h2>
            <span class="text-sm text-gray-500 tabular-nums">{{ number_format($metrics['consumed_weight'],2,',',' ') }} kg · {{ number_format($metrics['consumed_cost'],0,',',' ') }} F</span>
        </div>
        @if($live)
        <form method="POST" action="{{ route('production.orders.consume', $order) }}" class="px-6 py-4 bg-gray-50/60 border-b border-gray-100 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Bobine</label>
                <select name="coil_id" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">— Choisir —</option>
                    @foreach($coils as $c)
                        <option value="{{ $c->id }}">{{ $c->reference }} ({{ number_format($c->remaining_weight,0,',',' ') }} kg · {{ number_format($c->cost_per_kg,0,',',' ') }} F/kg)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Poids (kg)</label>
                <input type="number" name="weight_consumed" step="0.01" min="0.01" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Longueur (m)</label>
                <input type="number" name="length_consumed" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Consommer</button>
        </form>
        @endif
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Date</th><th class="text-left">Bobine</th><th class="text-right">Poids</th><th class="text-right">Longueur</th><th class="text-right">Coût</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->consumptions as $c)
                    <tr>
                        <td class="text-gray-600">{{ optional($c->consumed_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="font-mono text-xs text-indigo-600">{{ $c->coil?->reference ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($c->weight_consumed,2,',',' ') }} kg</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($c->length_consumed,2,',',' ') }} m</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($c->cost,0,',',' ') }} F</td>
                        <td class="text-right">@if($live)<form method="POST" action="{{ route('production.consumptions.destroy', $c) }}" data-confirm="Annuler cette consommation ? Le poids sera restitué.">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-xs">✕</button></form>@endif</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune consommation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ Sorties produits finis ══ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Sorties produits finis</h2>
            <span class="text-sm text-gray-500 tabular-nums">{{ number_format($metrics['output_qty'],0,',',' ') }} u · {{ number_format($metrics['output_meters'],2,',',' ') }} m</span>
        </div>
        @if($live)
        <form method="POST" action="{{ route('production.orders.output', $order) }}" class="px-6 py-4 bg-gray-50/60 border-b border-gray-100 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Entrepôt</label>
                <select name="warehouse_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">— Défaut —</option>
                    @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Longueur (m)</label>
                <input type="number" name="length" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Quantité</label>
                <input type="number" name="quantity" step="0.01" min="0.01" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Coût unit. (F)</label>
                <input type="number" name="unit_cost" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <button class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Produire</button>
        </form>
        @endif
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Date</th><th class="text-left">Produit</th><th class="text-right">Longueur</th><th class="text-right">Qté</th><th class="text-right">Total m</th><th class="text-left">Entrepôt</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->outputs as $o)
                    <tr>
                        <td class="text-gray-600">{{ optional($o->produced_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-gray-800">{{ $o->product?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($o->length,2,',',' ') }} m</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($o->quantity,0,',',' ') }}</td>
                        <td class="text-right tabular-nums text-gray-900 font-semibold">{{ number_format($o->total_meters,2,',',' ') }}</td>
                        <td class="text-gray-500 text-xs">{{ $o->warehouse?->name ?? '—' }}</td>
                        <td class="text-right">@if($live)<form method="POST" action="{{ route('production.outputs.destroy', $o) }}" data-confirm="Annuler cette sortie ? Le stock sera corrigé.">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-xs">✕</button></form>@endif</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune sortie produite.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ Chutes / pertes ══ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Chutes & pertes</h2>
            <span class="text-sm text-gray-500 tabular-nums">{{ number_format($metrics['waste_weight'],2,',',' ') }} kg · {{ number_format($metrics['waste_value'],0,',',' ') }} F</span>
        </div>
        @if($live)
        <form method="POST" action="{{ route('production.orders.waste', $order) }}" class="px-6 py-4 bg-gray-50/60 border-b border-gray-100 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="reutilisable">Réutilisable</option>
                    <option value="non_reutilisable" selected>Non réutilisable</option>
                    <option value="rebut">Rebut</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Poids (kg)</label>
                <input type="number" name="weight" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Machine</label>
                <select name="machine_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">—</option>
                    @foreach($machines as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Motif</label>
                <input type="text" name="reason" maxlength="255" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </div>
            <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Enregistrer</button>
        </form>
        @endif

        {{-- [§4] Entrée des sous-produits (chute au poids / avarié à la quantité) en stock --}}
        @php $bomBp = $order->billOfMaterial; @endphp
        @if($live && $bomBp && ($bomBp->scrap_product_id || $bomBp->defect_product_id))
        <form method="POST" action="{{ route('production.orders.byproduct', $order) }}" class="px-6 py-4 bg-indigo-50/40 border-b border-gray-100 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            @csrf
            <div class="col-span-2 md:col-span-5 text-xs font-semibold text-indigo-700 uppercase tracking-wider">Entrée sous-produits en stock</div>
            @if($bomBp->scrap_product_id)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Chute — poids (kg)</label>
                <input type="number" name="scrap_weight" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
                <p class="text-[10px] text-gray-500 mt-0.5 truncate">{{ $bomBp->scrapProduct?->name }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Dépôt chute</label>
                <select name="scrap_warehouse_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">défaut</option>
                    @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                </select>
            </div>
            @endif
            @if($bomBp->defect_product_id)
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Avarié — quantité</label>
                <input type="number" name="defect_quantity" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
                <p class="text-[10px] text-gray-500 mt-0.5 truncate">{{ $bomBp->defectProduct?->name }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Dépôt avarié</label>
                <select name="defect_warehouse_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">défaut</option>
                    @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                </select>
            </div>
            @endif
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Entrer en stock</button>
        </form>
        @endif
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Type</th><th class="text-right">Poids</th><th class="text-right">Valeur</th><th class="text-left">Machine</th><th class="text-left">Motif</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->wastes as $w)
                    <tr>
                        <td>
                            @php $wc = match($w->type){ 'reutilisable'=>'bg-green-100 text-green-700','rebut'=>'bg-red-100 text-red-700',default=>'bg-amber-100 text-amber-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $wc }}">{{ $w->typeLabel() }}</span>
                        </td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($w->weight,2,',',' ') }} kg</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($w->value,0,',',' ') }} F</td>
                        <td class="text-gray-500 text-xs">{{ $w->machine?->name ?? '—' }}</td>
                        <td class="text-gray-500 text-xs max-w-xs truncate">{{ $w->reason ?? '—' }}</td>
                        <td class="text-right">@if($live)<form method="POST" action="{{ route('production.wastes.destroy', $w) }}" data-confirm="Supprimer cette chute ?">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-xs">✕</button></form>@endif</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucune chute enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ Coût de revient ══ --}}
    @can('production.cost.view')
    @php $cost = $order->cost; @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Coût de revient</h2>
            @can('production.update')
            <form method="POST" action="{{ route('production.orders.cost', $order) }}" class="flex items-end gap-2">
                @csrf
                <div>
                    <label class="block text-[11px] font-medium text-gray-500 mb-0.5">Frais indirects %</label>
                    <input type="number" name="overhead_rate" step="0.1" min="0" max="100" value="0" class="w-20 border border-gray-300 rounded-lg px-2 py-1 text-sm text-right font-mono">
                </div>
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">{{ $cost ? 'Recalculer' : 'Calculer' }}</button>
            </form>
            @endcan
        </div>
        @if($cost)
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Matière</p><p class="font-bold text-gray-900 tabular-nums">{{ number_format($cost->material_cost,0,',',' ') }} F</p></div>
                <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Main-d'œuvre</p><p class="font-bold text-gray-900 tabular-nums">{{ number_format($cost->labor_cost,0,',',' ') }} F</p></div>
                <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Machine</p><p class="font-bold text-gray-900 tabular-nums">{{ number_format($cost->machine_cost,0,',',' ') }} F</p></div>
                <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Frais indirects</p><p class="font-bold text-gray-900 tabular-nums">{{ number_format($cost->overhead_cost,0,',',' ') }} F</p></div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3"><p class="text-xs text-indigo-600">Coût total</p><p class="font-bold text-indigo-800 tabular-nums">{{ number_format($cost->total_cost,0,',',' ') }} F</p></div>
                <div class="bg-sky-50 border border-sky-200 rounded-xl p-3"><p class="text-xs text-sky-600">Coût / mètre</p><p class="font-bold text-sky-800 tabular-nums">{{ number_format($cost->cost_per_meter,2,',',' ') }} F</p></div>
                <div class="bg-sky-50 border border-sky-200 rounded-xl p-3"><p class="text-xs text-sky-600">Coût / unité</p><p class="font-bold text-sky-800 tabular-nums">{{ number_format($cost->cost_per_unit,2,',',' ') }} F</p></div>
                <div class="{{ $cost->margin >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border rounded-xl p-3">
                    <p class="text-xs {{ $cost->margin >= 0 ? 'text-green-600' : 'text-red-600' }}">Marge estimée</p>
                    <p class="font-bold {{ $cost->margin >= 0 ? 'text-green-800' : 'text-red-800' }} tabular-nums">{{ number_format($cost->margin,0,',',' ') }} F</p>
                </div>
            </div>

            @if($cost->standard_total > 0)
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-100">
                <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Coût standard</p><p class="font-bold text-gray-900 tabular-nums">{{ number_format($cost->standard_total,0,',',' ') }} F</p></div>
                <div class="bg-gray-50 rounded-xl p-3"><p class="text-xs text-gray-500">Coût réel</p><p class="font-bold text-gray-900 tabular-nums">{{ number_format($cost->total_cost,0,',',' ') }} F</p></div>
                @php $fav = ($cost->variance ?? 0) <= 0; @endphp
                <div class="{{ $fav ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border rounded-xl p-3">
                    <p class="text-xs {{ $fav ? 'text-green-600' : 'text-red-600' }}">Écart {{ $fav ? '(favorable)' : '(défavorable)' }}</p>
                    <p class="font-bold {{ $fav ? 'text-green-800' : 'text-red-800' }} tabular-nums">{{ $fav ? '' : '+' }}{{ number_format($cost->variance ?? 0,0,',',' ') }} F</p>
                </div>
            </div>
            @endif
        </div>
        @else
        <p class="px-6 py-8 text-center text-gray-400">Coût non calculé. {{ auth()->user()->can('production.update') ? 'Cliquez « Calculer ».' : '' }}</p>
        @endif
    </div>
    @endcan

    {{-- ══ Contrôle qualité ══ --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Contrôle qualité</h2></div>
        @can('production.update')
        @if(!in_array($order->status, ['brouillon','annule']))
        <form method="POST" action="{{ route('production.orders.quality', $order) }}" class="px-6 py-4 bg-gray-50/60 border-b border-gray-100 space-y-3">
            @csrf
            <div class="flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700"><input type="checkbox" name="thickness_ok" value="1" checked class="rounded border-gray-300 text-indigo-600"> Épaisseur</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700"><input type="checkbox" name="length_ok" value="1" checked class="rounded border-gray-300 text-indigo-600"> Longueur</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700"><input type="checkbox" name="color_ok" value="1" checked class="rounded border-gray-300 text-indigo-600"> Couleur</label>
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700"><input type="checkbox" name="visual_ok" value="1" checked class="rounded border-gray-300 text-indigo-600"> Visuel</label>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Verdict</label>
                    <select name="status" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                        <option value="conforme">Conforme</option>
                        <option value="a_reprendre">À reprendre</option>
                        <option value="non_conforme">Non conforme</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Qté rejetée</label>
                    <input type="number" name="rejected_quantity" step="0.01" min="0" value="0" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Motif (si non conforme)</label>
                    <input type="text" name="reason" maxlength="255" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                </div>
            </div>
            <div class="flex justify-end"><button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Enregistrer le contrôle</button></div>
        </form>
        @endif
        @endcan
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Date</th><th class="text-left">Critères</th><th class="text-left">Verdict</th><th class="text-right">Rejeté</th><th class="text-left">Motif</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->qualityControls as $qc)
                    <tr>
                        <td class="text-gray-600">{{ optional($qc->controlled_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-xs">
                            @foreach(['thickness_ok'=>'Ép.','length_ok'=>'Long.','color_ok'=>'Coul.','visual_ok'=>'Vis.'] as $f=>$lbl)
                                <span class="inline-flex items-center gap-0.5 mr-1 {{ $qc->$f ? 'text-green-600' : 'text-red-500' }}">{{ $qc->$f ? '✓' : '✕' }} {{ $lbl }}</span>
                            @endforeach
                        </td>
                        <td>
                            @php $qcc = match($qc->status){ 'conforme'=>'bg-green-100 text-green-700','a_reprendre'=>'bg-amber-100 text-amber-700',default=>'bg-red-100 text-red-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $qcc }}">{{ $qc->statusLabel() }}</span>
                        </td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($qc->rejected_quantity,0,',',' ') }}</td>
                        <td class="text-gray-500 text-xs max-w-xs truncate">{{ $qc->reason ?? '—' }}</td>
                        <td class="text-right">@can('production.update')<form method="POST" action="{{ route('production.quality.destroy', $qc) }}" data-confirm="Supprimer ce contrôle ?">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-xs">✕</button></form>@endcan</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Aucun contrôle qualité.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ Work Orders (gamme opératoire) ══ --}}
    @php $woLive = in_array($order->status, ['lance','en_cours'], true) && auth()->user()->can('production.update'); @endphp
    @if($order->operations->isNotEmpty() || in_array($order->status, ['lance','en_cours'], true))
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-900">Opérations (gamme)</h2>
                @if($opProgress['total'] > 0)
                <span class="text-sm text-gray-500 tabular-nums">{{ $opProgress['done'] }}/{{ $opProgress['total'] }} · {{ $opProgress['percent'] }}%</span>
                @endif
            </div>
            @can('production.update')
            @if($order->operations->isEmpty() && in_array($order->status, ['lance','en_cours'], true))
            <form method="POST" action="{{ route('production.orders.operations', $order) }}">@csrf
                <button class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Générer depuis la gamme
                </button>
            </form>
            @endif
            @endcan
        </div>

        @if($opProgress['total'] > 0)
        <div class="px-6 pt-4">
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-indigo-500" style="width: {{ $opProgress['percent'] }}%"></div></div>
        </div>
        @endif

        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Séq.</th><th class="text-left">Opération</th><th class="text-left">Centre</th><th class="text-right">Prévu (min)</th><th class="text-right">Réel (min)</th><th class="text-left">Statut</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->operations as $op)
                    <tr>
                        <td class="text-gray-500 font-mono text-xs">{{ $op->sequence }}</td>
                        <td class="text-gray-800">{{ $op->name }}</td>
                        <td class="text-gray-600 text-xs">{{ $op->workCenter?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($op->planned_minutes,0,',',' ') }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($op->real_minutes,0,',',' ') }}</td>
                        <td>
                            @php $oc = match($op->status){ 'pending'=>'bg-gray-100 text-gray-600','in_progress'=>'bg-sky-100 text-sky-700',default=>'bg-green-100 text-green-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $oc }}">{{ $op->statusLabel() }}</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if($woLive)
                                @if($op->status === 'pending')
                                <form method="POST" action="{{ route('production.operations.start', $op) }}" class="inline">@csrf<button class="text-sky-600 hover:underline text-xs font-medium">Démarrer</button></form>
                                @elseif($op->status === 'in_progress')
                                <form method="POST" action="{{ route('production.operations.finish', $op) }}" class="inline">@csrf<button class="text-green-600 hover:underline text-xs font-medium">Terminer</button></form>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune opération. Générez-les depuis la gamme de la nomenclature.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ══ Main-d'œuvre (pointage temps → RH) ══ --}}
    @php $moAllowed = in_array($order->status, ['lance','en_cours','termine'], true); $moHours = $order->timeLogs->sum('hours'); $moCost = $order->timeLogs->sum('labor_cost'); @endphp
    @if($moAllowed || $order->timeLogs->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Main-d'œuvre (pointage)</h2>
            <span class="text-sm text-gray-500 tabular-nums">{{ number_format($moHours,2,',',' ') }} h · {{ number_format($moCost,0,',',' ') }} F</span>
        </div>
        @can('production.update')
        @if($moAllowed)
        <form method="POST" action="{{ route('production.orders.time', $order) }}" class="px-6 py-4 bg-gray-50/60 border-b border-gray-100 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Opérateur</label>
                <select name="employee_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Heures</label>
                <input type="number" name="hours" step="0.25" min="0.25" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Coût horaire (F)</label>
                <input type="number" name="hourly_cost" step="1" min="0" required class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div class="flex items-center gap-2">
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-700"><input type="checkbox" name="is_overtime" value="1" class="rounded border-gray-300 text-indigo-600"> H. sup.</label>
                <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">Pointer</button>
            </div>
        </form>
        @endif
        @endcan
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Date</th><th class="text-left">Opérateur</th><th class="text-right">Heures</th><th class="text-right">Coût/h</th><th class="text-right">Coût MO</th><th class="text-left">Type</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->timeLogs as $t)
                    <tr>
                        <td class="text-gray-600">{{ optional($t->entry_date)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-gray-800">{{ $t->employee?->full_name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($t->hours,2,',',' ') }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($t->hourly_cost,0,',',' ') }}</td>
                        <td class="text-right tabular-nums font-semibold text-gray-900">{{ number_format($t->labor_cost,0,',',' ') }} F</td>
                        <td>@if($t->is_overtime)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">H. sup.</span>@else<span class="text-gray-400 text-xs">Normal</span>@endif</td>
                        <td class="text-right">@can('production.update')<form method="POST" action="{{ route('production.time-logs.destroy', $t) }}" data-confirm="Supprimer ce pointage ?">@csrf @method('DELETE')<button class="text-gray-400 hover:text-red-600 text-xs">✕</button></form>@endcan</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucun pointage. Le coût MO utilisera l'estimation nomenclature.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ══ Lots de fabrication (traçabilité) ══ --}}
    @if($order->batches->isNotEmpty() || in_array($order->status, ['lance','en_cours','termine'], true))
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Lots de fabrication</h2>
            @can('production.update')
            @if(!in_array($order->status, ['brouillon','annule']))
            <form method="POST" action="{{ route('production.orders.batches', $order) }}">@csrf
                <button class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nouveau lot
                </button>
            </form>
            @endif
            @endcan
        </div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">N° Lot</th><th class="text-right">Quantité</th><th class="text-left">Statut</th><th class="text-left">Produit le</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->batches as $b)
                    <tr>
                        <td class="font-mono text-xs text-indigo-600">{{ $b->batch_number }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($b->quantity,0,',',' ') }}</td>
                        <td><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $b->status==='cloture' ? 'bg-gray-100 text-gray-600' : 'bg-sky-100 text-sky-700' }}">{{ $b->statusLabel() }}</span></td>
                        <td class="text-gray-600">{{ optional($b->produced_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-right">
                            @if($b->status==='en_cours' && auth()->user()->can('production.update'))
                            <form method="POST" action="{{ route('production.batches.close', $b) }}">@csrf<button class="text-gray-500 hover:underline text-xs">Clôturer</button></form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucun lot. Créez un lot pour tracer la production.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ══ Réservation produit fini (client) ══ --}}
    @php $activeRes = $order->reservations->where('status', 'reserved'); @endphp
    @if($order->status === 'termine' || $order->reservations->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Réservation produit fini</h2>
            @can('production.update')
            @if($order->status === 'termine' && $order->product_id && $activeRes->isEmpty())
            <form method="POST" action="{{ route('production.orders.reserve', $order) }}">@csrf
                <button class="inline-flex items-center gap-1.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Réserver pour le client
                </button>
            </form>
            @endif
            @endcan
        </div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Produit</th><th class="text-right">Quantité</th><th class="text-left">Statut</th><th class="text-left">Réservé le</th><th></th></tr></thead>
                <tbody>
                    @forelse($order->reservations as $r)
                    <tr>
                        <td class="text-gray-800">{{ $r->product?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-900">{{ number_format($r->quantity, 0, ',', ' ') }}</td>
                        <td>
                            @php $rc = match($r->status){ 'reserved'=>'bg-teal-100 text-teal-700','released'=>'bg-gray-100 text-gray-500',default=>'bg-amber-100 text-amber-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $rc }}">{{ $r->statusLabel() }}</span>
                        </td>
                        <td class="text-gray-600">{{ optional($r->reserved_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-right">
                            @can('production.update')
                            @if($r->status === 'reserved')
                            <form method="POST" action="{{ route('production.reservations.release', $r) }}" data-confirm="Libérer cette réservation ?">@csrf
                                <button class="text-gray-400 hover:text-red-600 text-xs">Libérer</button>
                            </form>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Aucune réservation. {{ $order->status === 'termine' ? 'Réservez le PF pour le client.' : '' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($order->status === 'brouillon')
        @can('production.delete')
        <div class="flex justify-end">
            <form method="POST" action="{{ route('production.orders.destroy', $order) }}" data-confirm="Supprimer définitivement cet OF en brouillon ?">
                @csrf @method('DELETE')
                <button class="text-red-600 hover:underline text-sm">Supprimer cet OF</button>
            </form>
        </div>
        @endcan
    @endif

    {{-- Modal annulation --}}
    @can('production.cancel')
    <div x-show="cancelOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="cancelOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4">
            <h3 class="font-semibold text-gray-900">Annuler l'ordre de fabrication</h3>
            <form method="POST" action="{{ route('production.orders.cancel', $order) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Motif (facultatif)</label>
                    <textarea name="reason" rows="3" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-red-300" placeholder="Raison de l'annulation…"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="cancelOpen = false" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Retour</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Confirmer l'annulation</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
