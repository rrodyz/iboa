@extends('layouts.erp')
@section('title', $product->name)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('products.index') }}" class="hover:text-gray-700">Articles</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $product->name }}</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="flex-shrink-0">
            @if($product->image)
            <img src="{{ url(Storage::url($product->image)) }}" alt="" class="w-20 h-20 rounded-xl object-cover border border-gray-200">
            @else
            <div class="w-20 h-20 bg-gray-100 rounded-xl flex items-center justify-center">
                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            @endif
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $product->name }}</h1>
                    <p class="text-sm text-gray-400 font-mono mt-0.5">Réf : {{ $product->reference }}</p>
                    @if($product->barcode)
                    <p class="text-xs text-gray-400 font-mono">Code-barres : {{ $product->barcode }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2 mt-2">
                        @if($product->family)
                        <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full">{{ $product->family->name }}</span>
                        @endif
                        @if($product->brand)
                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full">{{ $product->brand->name }}</span>
                        @endif
                        <span class="bg-purple-50 text-purple-700 text-xs px-2 py-0.5 rounded-full capitalize">{{ $product->type }}</span>
                        @if($product->is_active)
                        <span class="bg-green-50 text-green-700 text-xs px-2 py-0.5 rounded-full">Actif</span>
                        @else
                        <span class="bg-red-50 text-red-700 text-xs px-2 py-0.5 rounded-full">Inactif</span>
                        @endif
                    </div>
                </div>
                <a href="{{ route('products.edit', $product) }}" class="flex-shrink-0 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    Modifier
                </a>
            </div>
            @if($product->description)
            <p class="text-sm text-gray-600 mt-3">{{ $product->description }}</p>
            @endif
        </div>
    </div>

    {{-- Prix + Stock KPIs --}}
    @php
        $totalStock  = $product->productStocks->sum('quantity');
        $stockMin    = $product->stock_min ?? 0;
        $stockMax    = $product->stock_max ?? 0;
        $margin      = $product->purchase_price > 0
            ? round((($product->sale_price - $product->purchase_price) / $product->purchase_price) * 100, 1)
            : null;
        $stockAlert  = $stockMin > 0 && $totalStock <= $stockMin;
        $stockOk     = $stockMax > 0 && $totalStock >= $stockMin && $totalStock <= $stockMax;
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Prix d'achat</p>
            <p class="text-xl font-semibold text-gray-700">{{ number_format($product->purchase_price, 0, ',', ' ') }} <span class="text-sm font-normal text-gray-400">FCFA</span></p>
            @if($margin !== null)
            <p class="text-xs mt-1 {{ $margin >= 0 ? 'text-emerald-600' : 'text-red-600' }} font-medium">
                Marge : {{ $margin >= 0 ? '+' : '' }}{{ $margin }}%
            </p>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Prix de vente HT</p>
            <p class="text-xl font-bold text-blue-700">{{ number_format($product->sale_price, 0, ',', ' ') }} <span class="text-sm font-normal text-gray-400">FCFA</span></p>
            @if($product->taxRate)
            <p class="text-xs text-gray-400 mt-1">TVA {{ $product->taxRate->rate }}% incluse</p>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">TVA</p>
            @if($product->taxRate)
            <p class="text-xl font-semibold text-gray-700">{{ $product->taxRate->rate }}<span class="text-sm font-normal text-gray-400">%</span></p>
            <p class="text-xs text-gray-400 mt-1">{{ $product->taxRate->name }}</p>
            @else
            <p class="text-xl font-semibold text-gray-400">—</p>
            @endif
        </div>
        <div class="bg-white rounded-xl border {{ $stockAlert ? 'border-red-200 bg-red-50' : 'border-gray-200' }} p-4">
            <p class="text-xs font-medium {{ $stockAlert ? 'text-red-500' : 'text-gray-500' }} uppercase tracking-wider mb-1">
                Stock disponible
                @if($stockAlert) ⚠️ @endif
            </p>
            <p class="text-xl font-semibold {{ $stockAlert ? 'text-red-700' : 'text-gray-700' }}">
                {{ number_format($totalStock, 2) }}
                <span class="text-sm font-normal text-gray-400">{{ $product->unit?->abbreviation ?? 'u' }}</span>
            </p>
            @if($stockMax > 0)
            @php $pct = min(100, $stockMax > 0 ? ($totalStock / $stockMax) * 100 : 0); @endphp
            <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full {{ $stockAlert ? 'bg-red-500' : ($pct > 75 ? 'bg-emerald-500' : 'bg-amber-400') }}"
                     style="width: {{ $pct }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1">Min {{ $stockMin }} / Max {{ $stockMax }}</p>
            @elseif($stockMin > 0)
            <p class="text-xs {{ $stockAlert ? 'text-red-500 font-medium' : 'text-gray-400' }} mt-1">Seuil min : {{ $stockMin }}</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Left column: details + stock --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Details --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Informations</h3>
                </div>
                <div class="p-5 grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Unité de mesure</p>
                        <p class="font-medium text-gray-800">{{ $product->unit?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Méthode valorisation</p>
                        <p class="font-medium text-gray-800 uppercase">{{ $product->valuation_method ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Prix min vente</p>
                        <p class="font-medium text-gray-800">{{ $product->min_sale_price ? number_format($product->min_sale_price, 0, ',', ' ').' FCFA' : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Seuil réapprovisionnement</p>
                        <p class="font-medium text-gray-800">{{ $product->reorder_point ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Stock min / max</p>
                        <p class="font-medium text-gray-800">{{ $product->stock_min ?? '—' }} / {{ $product->stock_max ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Caractéristiques</p>
                        <div class="flex flex-wrap gap-1 mt-0.5">
                            @if($product->is_stockable) <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">Stockable</span> @endif
                            @if($product->is_purchasable) <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">Achetable</span> @endif
                            @if($product->is_sellable) <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded">Vendable</span> @endif
                            @if($product->has_serial_number) <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded">N° Série</span> @endif
                            @if($product->has_lot_number) <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded">Lot</span> @endif
                            @if($product->has_expiry_date) <span class="bg-orange-50 text-orange-700 text-xs px-2 py-0.5 rounded">Date expiry</span> @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stock par dépôt --}}
            @if($product->productStocks->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Stock par dépôt</h3>
                </div>
                <table class="w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dépôt</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Disponible</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Réservé</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Coût moyen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($product->productStocks as $stock)
                        <tr>
                            <td class="px-5 py-3 text-gray-900">{{ $stock->warehouse?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900">{{ number_format($stock->quantity, 2) }}</td>
                            <td class="px-5 py-3 text-right text-gray-500">{{ number_format($stock->reserved_quantity, 2) }}</td>
                            <td class="px-5 py-3 text-right text-gray-500">{{ number_format($stock->avg_cost, 0, ',', ' ') }} FCFA</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Composants (type composé) --}}
            @if($product->type === 'compose' && $product->components->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Composants</h3>
                </div>
                <table class="w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Composant</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Référence</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($product->components as $comp)
                        <tr>
                            <td class="px-5 py-3 text-gray-900">
                                <a href="{{ route('products.show', $comp->component) }}" class="hover:text-blue-600">
                                    {{ $comp->component->name }}
                                </a>
                            </td>
                            <td class="px-5 py-3 font-mono text-gray-500 text-xs">{{ $comp->component->reference }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900">{{ number_format($comp->quantity, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        </div>

        {{-- Right column: price tiers + promotions --}}
        <div class="space-y-5">

            {{-- Tarifs par client --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ showForm: false }">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Tarifs spéciaux</h3>
                    <button @click="showForm = !showForm" type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-2.5 py-1 rounded-md transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Ajouter
                    </button>
                </div>

                {{-- Add tier form --}}
                <div x-show="showForm" x-cloak class="p-4 bg-gray-50 border-b border-gray-100">
                    <form method="POST" action="{{ route('product-price-tiers.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Libellé</label>
                            <input type="text" name="label" placeholder="Ex: Tarif grossiste"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Prix (FCFA) <span class="text-red-500">*</span></label>
                                <input type="number" name="price" min="0" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 text-right">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Remise (%)</label>
                                <input type="number" name="discount_percent" min="0" max="100" step="0.01"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 text-right">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Catégorie client</label>
                            <select name="client_category"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="">— Aucune —</option>
                                <option value="gros">Gros</option>
                                <option value="semi-gros">Semi-gros</option>
                                <option value="detail">Détail</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Qté minimum</label>
                            <input type="number" name="min_quantity" min="0" step="0.01" value="1"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 text-right">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Début</label>
                                <input type="date" name="starts_at"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Fin</label>
                                <input type="date" name="ends_at"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-4 py-1.5 rounded-lg transition-colors">
                                Enregistrer
                            </button>
                            <button @click="showForm = false" type="button" class="text-xs text-gray-500 hover:text-gray-700 px-4 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>

                @if($product->productPriceTiers->isEmpty())
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Aucun tarif spécial</div>
                @else
                <div class="divide-y divide-gray-100">
                    @foreach($product->productPriceTiers as $tier)
                    <div class="px-5 py-3 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $tier->label ?: 'Tarif #'.$tier->id }}</p>
                            <p class="text-xs text-gray-500">
                                @if($tier->client_category)
                                <span class="capitalize">{{ $tier->client_category }}</span> ·
                                @endif
                                Qté ≥ {{ $tier->min_quantity ?? 1 }}
                                @if($tier->starts_at || $tier->ends_at)
                                · {{ $tier->starts_at?->format('d/m/Y') ?? '—' }} → {{ $tier->ends_at?->format('d/m/Y') ?? '—' }}
                                @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <div class="text-right">
                                <p class="text-sm font-bold text-blue-700">{{ number_format($tier->price, 0, ',', ' ') }} FCFA</p>
                                @if($tier->discount_percent)
                                <p class="text-xs text-green-600">-{{ $tier->discount_percent }}%</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('product-price-tiers.destroy', $tier) }}"
                                  onsubmit="return confirm('Supprimer ce tarif ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 transition-colors p-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Promotions actives --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Promotions</h3>
                    <a href="{{ route('promotions.create') }}?product_id={{ $product->id }}"
                       class="inline-flex items-center gap-1 text-xs font-medium text-purple-600 hover:text-purple-800 bg-purple-50 hover:bg-purple-100 px-2.5 py-1 rounded-md transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Créer
                    </a>
                </div>
                @if(isset($promotions) && $promotions->isNotEmpty())
                <div class="divide-y divide-gray-100">
                    @foreach($promotions as $promo)
                    @php
                        $today = \Carbon\Carbon::today();
                        $promoActive = $promo->is_active && $promo->starts_at <= $today && $promo->ends_at >= $today;
                    @endphp
                    <div class="px-5 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $promo->name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $promo->starts_at?->format('d/m/Y') ?? '—' }} → {{ $promo->ends_at?->format('d/m/Y') ?? '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if($promo->type === 'pourcentage')
                                <span class="text-sm font-bold text-green-700">-{{ number_format($promo->value, 0) }}%</span>
                                @elseif($promo->type === 'montant_fixe')
                                <span class="text-sm font-bold text-blue-700">-{{ number_format($promo->value, 0, ',', ' ') }} FCFA</span>
                                @else
                                <span class="text-sm font-bold text-purple-700">{{ number_format($promo->value, 0, ',', ' ') }} FCFA</span>
                                @endif
                                @if($promoActive)
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-xs px-1.5 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>Active
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-5 py-8 text-center text-gray-400 text-sm">Aucune promotion</div>
                @endif
            </div>

        </div>
    </div>

    {{-- Derniers mouvements de stock --}}
    @if($recentMovements->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Derniers mouvements de stock</h3>
            <a href="{{ route('stocks.movements', ['product_id' => $product->id]) }}"
               class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                Voir tout →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase">Dépôt</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase">Quantité</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Coût unit.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentMovements as $mvt)
                    @php
                        $isIn = in_array($mvt->type, ['entree', 'retour_client']) || ($mvt->type === 'ajustement' && $mvt->quantity > 0);
                        $typeLabels = ['entree' => 'Entrée', 'sortie' => 'Sortie', 'ajustement' => 'Ajustement', 'transfert' => 'Transfert', 'retour_client' => 'Retour client', 'retour_fournisseur' => 'Retour fourn.'];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 text-gray-500 text-xs">
                            {{ \Carbon\Carbon::parse($mvt->occurred_at)->format('d/m/Y') }}
                        </td>
                        <td class="px-5 py-2.5">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $isIn ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                {{ $typeLabels[$mvt->type] ?? $mvt->type }}
                            </span>
                        </td>
                        <td class="px-5 py-2.5 text-gray-600 text-xs">{{ $mvt->warehouse?->name ?? '—' }}</td>
                        <td class="px-5 py-2.5 text-right font-semibold tabular-nums {{ $isIn ? 'text-green-700' : 'text-red-700' }}">
                            {{ $isIn ? '+' : '' }}{{ number_format($mvt->quantity, 2) }}
                        </td>
                        <td class="px-5 py-2.5 text-right text-gray-500 text-xs tabular-nums hidden md:table-cell">
                            {{ $mvt->unit_cost ? number_format($mvt->unit_cost, 0, ',', ' ').' FCFA' : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Pièces jointes --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <x-attachments.manager model="Product" :id="$product->id" />
    </div>

</div>
@endsection
