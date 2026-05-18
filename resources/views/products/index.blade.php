@extends('layouts.erp')
@section('title', 'Articles')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Articles</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Articles</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $products->total() }} article(s) trouvé(s)</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('exports.products', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
                Exporter Excel
            </a>
            <a href="{{ route('exports.products-pdf', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 border border-red-600 text-red-700 hover:bg-red-50 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Exporter PDF
            </a>
            <a href="{{ route('import.index', ['type' => 'products']) }}"
               class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4 0l4-4m0 0l4 4m-4-4V4"/>
                </svg>
                Importer
            </a>
            <a href="{{ route('products.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvel article
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
            {{-- Recherche texte (prend 2 cols sur xl) --}}
            <div class="sm:col-span-2 xl:col-span-2">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Désignation, référence, code-barres…"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <select name="family_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Toutes les familles</option>
                @foreach($families as $family)
                    <option value="{{ $family->id }}" {{ request('family_id') == $family->id ? 'selected' : '' }}>{{ $family->name }}</option>
                    @foreach($family->children as $child)
                    <option value="{{ $child->id }}" {{ request('family_id') == $child->id ? 'selected' : '' }}>&nbsp;&nbsp;└ {{ $child->name }}</option>
                    @endforeach
                @endforeach
            </select>

            <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Tous les types</option>
                <option value="simple"  {{ request('type') === 'simple'  ? 'selected' : '' }}>Simple</option>
                <option value="service" {{ request('type') === 'service' ? 'selected' : '' }}>Service</option>
                <option value="compose" {{ request('type') === 'compose' ? 'selected' : '' }}>Composé</option>
            </select>

            <select name="is_active" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">Tous les statuts</option>
                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Actifs</option>
                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactifs</option>
            </select>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                Filtrer
            </button>
            @if(request()->hasAny(['search', 'family_id', 'brand_id', 'type', 'is_active']))
            <a href="{{ route('products.index') }}"
               class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Réinitialiser
            </a>
            @endif
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Article</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Famille</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Prix achat</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Prix vente</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider hidden xl:table-cell">TVA</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if($product->image)
                                <img src="{{ url(Storage::url($product->image)) }}" class="w-9 h-9 rounded-lg object-cover flex-shrink-0 border border-gray-100">
                                @else
                                <div class="w-9 h-9 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $product) }}"
                                       class="font-medium text-gray-900 hover:text-blue-600 transition-colors block truncate max-w-xs">
                                        {{ $product->name }}
                                    </a>
                                    <p class="text-xs text-gray-400 font-mono">{{ $product->reference ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell">
                            @if($product->family)
                                <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">{{ $product->family->name }}</span>
                            @else
                                <span class="text-gray-300 text-sm">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            @php
                                $typeLabels = ['simple' => ['label' => 'Simple', 'class' => 'bg-gray-100 text-gray-600'], 'service' => ['label' => 'Service', 'class' => 'bg-purple-50 text-purple-700'], 'compose' => ['label' => 'Composé', 'class' => 'bg-orange-50 text-orange-700']];
                                $t = $typeLabels[$product->type] ?? ['label' => $product->type, 'class' => 'bg-gray-100 text-gray-600'];
                            @endphp
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $t['class'] }}">{{ $t['label'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500 tabular-nums text-sm whitespace-nowrap hidden sm:table-cell">
                            {{ $product->purchase_price > 0 ? number_format($product->purchase_price, 0, ',', ' ').' FCFA' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900 tabular-nums whitespace-nowrap">
                            {{ $product->sale_price > 0 ? number_format($product->sale_price, 0, ',', ' ').' FCFA' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500 text-xs hidden xl:table-cell">
                            {{ $product->taxRate?->short_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($product->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Actif
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactif
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('products.show', $product) }}"
                                   class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors"
                                   title="Voir">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('products.edit', $product) }}"
                                   class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                                   title="Modifier">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('products.destroy', $product) }}" method="POST"
                                      onsubmit="return confirm('Supprimer « {{ addslashes($product->name) }} » ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                                            title="Supprimer">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-3 text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-gray-600">Aucun article trouvé</p>
                                    @if(request()->hasAny(['search', 'family_id', 'brand_id', 'type', 'is_active']))
                                        <a href="{{ route('products.index') }}" class="text-sm text-blue-600 hover:text-blue-700 mt-1 inline-block">Effacer les filtres</a>
                                    @else
                                        <a href="{{ route('products.create') }}" class="text-sm text-blue-600 hover:text-blue-700 mt-1 inline-block">Créer le premier article</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $products->withQueryString()->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
