{{--
    Formulaire article — niveau Sage GesCom.

    Structure :
      - Section 1 : Identification (référence, désignation, code-barres, description, photo)
      - Section 2 : Classification (type, famille, marque, unité)
      - Section 3 : Tarification (achat, vente, marge, TVA, valorisation)
      - Section 4 : Stock & seuils
      - Section 5 : Comptabilité (3 comptes : vente/achat/stock)
      - Section 6 : Fournisseur principal
      - Section 7 : Traçabilité (lot/série/expiration)
      - Section 8 : Composants (kit)
      - Section 9 : Comportement (flags)

    Rendu 100 % serveur — Alpine.js uniquement pour les composants vraiment dynamiques
    (composants kit, modale création rapide).
--}}

@php
    $p = $product ?? null;
    $isEdit = isset($product);
    // Cast helpers pour les checkboxes
    $boolVal = fn($field, $default = false) => old($field, $p?->{$field} ?? $default) ? '1' : '0';
@endphp

<form action="{{ $isEdit ? route('products.update', $p) : route('products.store') }}"
      method="POST" enctype="multipart/form-data" data-turbo="false"
      x-data="productForm({
          type: '{{ old('type', $p->type ?? 'simple') }}',
          purchasePrice: {{ (int) old('purchase_price', $p->purchase_price ?? 0) }},
          marginRate: {{ (float) old('margin_rate_target', $p->margin_rate_target ?? 0) }},
          salePrice: {{ (int) old('sale_price', $p->sale_price ?? 0) }},
          components: {{ Js::from(old('components', $isEdit && $p->components->isNotEmpty()
              ? $p->components->map(fn($c) => ['component_product_id' => $c->component_product_id, 'quantity' => $c->quantity])->toArray()
              : [])) }}
      })"
      class="space-y-5">
    @csrf
    @if($isEdit) @method('PUT') @endif

    {{-- ──────────────────────────────────────────────────────────
         Erreurs de validation
    ────────────────────────────────────────────────────────── --}}
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
        <strong>Veuillez corriger les erreurs suivantes :</strong>
        <ul class="mt-1 list-disc list-inside">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- ═══════════ COLONNE PRINCIPALE (2/3) ═══════════ --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 1. IDENTIFICATION
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-blue-500 rounded-full"></span>
                    Identification
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Référence</label>
                        <input type="text" name="reference" value="{{ old('reference', $p->reference ?? '') }}"
                               maxlength="50" placeholder="Auto si vide (ART-00001)"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            Désignation <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name', $p->name ?? '') }}"
                               required maxlength="200"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 font-medium">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Code-barres (EAN/UPC)</label>
                        <input type="text" name="barcode" value="{{ old('barcode', $p->barcode ?? '') }}"
                               maxlength="50"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Marque</label>
                        <select name="brand_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="">— Aucune —</option>
                            @foreach($brands as $b)
                                <option value="{{ $b->id }}" @selected(old('brand_id', $p->brand_id ?? '') == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description / notes</label>
                    <textarea name="description" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('description', $p->description ?? '') }}</textarea>
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 2. TARIFICATION + MARGE
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-emerald-500 rounded-full"></span>
                    Tarification & marges
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Prix d'achat HT</label>
                        <div class="relative">
                            <input type="number" name="purchase_price" x-model.number="purchasePrice"
                                   value="{{ old('purchase_price', $p->purchase_price ?? 0) }}"
                                   min="0" step="1"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 text-right tabular-nums pr-12">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">FCFA</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-orange-700 mb-1">Marge cible</label>
                        <div class="relative">
                            <input type="number" name="margin_rate_target" x-model.number="marginRate"
                                   @input="recomputeFromMargin()"
                                   value="{{ old('margin_rate_target', $p->margin_rate_target ?? '') }}"
                                   min="0" max="999" step="0.01"
                                   class="w-full border border-orange-300 bg-orange-50/40 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 text-right tabular-nums pr-8">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-500">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-emerald-700 mb-1">Prix vente HT <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" name="sale_price" x-model.number="salePrice"
                                   @input="recomputeMargin()"
                                   value="{{ old('sale_price', $p->sale_price ?? 0) }}"
                                   min="0" step="1"
                                   class="w-full border border-emerald-300 bg-emerald-50/40 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 text-right tabular-nums font-semibold pr-12">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-500">FCFA</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Prix plancher</label>
                        <div class="relative">
                            <input type="number" name="min_sale_price"
                                   value="{{ old('min_sale_price', $p->min_sale_price ?? 0) }}"
                                   min="0" step="1"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 text-right tabular-nums pr-12">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">FCFA</span>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-gray-500 bg-gray-50 px-3 py-2 rounded-lg flex items-center gap-2">
                    <span>💡 Marge calculée :</span>
                    <strong x-text="formatPercent(actualMargin())" class="text-emerald-700"></strong>
                    <span class="mx-1 text-gray-300">|</span>
                    <span>Marge en valeur :</span>
                    <strong x-text="formatMoney(salePrice - purchasePrice)" class="text-emerald-700"></strong>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Taux TVA</label>
                        <select name="tax_rate_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="">— Exonéré —</option>
                            @foreach($taxRates as $tr)
                                <option value="{{ $tr->id }}" @selected(old('tax_rate_id', $p->tax_rate_id ?? '') == $tr->id)>
                                    {{ $tr->name }} ({{ $tr->rate }} %)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Méthode de valorisation</label>
                        <select name="valuation_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            @php $vm = old('valuation_method', $p->valuation_method ?? 'cmp'); @endphp
                            <option value="cmp"  @selected($vm === 'cmp')>CMP — Coût Moyen Pondéré</option>
                            <option value="fifo" @selected($vm === 'fifo')>FIFO — Premier Entré / Premier Sorti</option>
                            <option value="lifo" @selected($vm === 'lifo')>LIFO — Dernier Entré / Premier Sorti</option>
                        </select>
                    </div>
                </div>

                @if($isEdit && ($p->last_purchase_price > 0 || $p->weighted_avg_cost > 0))
                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100 text-sm">
                    <div class="bg-gray-50 rounded-lg p-2.5">
                        <div class="text-xs text-gray-500">Dernier prix d'achat</div>
                        <div class="font-mono font-semibold text-gray-900 tabular-nums">{{ number_format($p->last_purchase_price, 0, ',', ' ') }} FCFA</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2.5">
                        <div class="text-xs text-gray-500">PMP (Prix moyen pondéré)</div>
                        <div class="font-mono font-semibold text-gray-900 tabular-nums">{{ number_format($p->weighted_avg_cost, 2, ',', ' ') }} FCFA</div>
                    </div>
                </div>
                @endif
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 3. STOCK & SEUILS (visible si stockable)
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-amber-500 rounded-full"></span>
                    Stock & seuils
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stock minimum</label>
                        <input type="number" name="stock_min"
                               value="{{ old('stock_min', $p->stock_min ?? 0) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 text-right tabular-nums">
                        <p class="text-[10px] text-gray-400 mt-1">Alerte rupture</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Stock maximum</label>
                        <input type="number" name="stock_max"
                               value="{{ old('stock_max', $p->stock_max ?? '') }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 text-right tabular-nums">
                        <p class="text-[10px] text-gray-400 mt-1">Plafond</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Point de commande</label>
                        <input type="number" name="reorder_point"
                               value="{{ old('reorder_point', $p->reorder_point ?? 0) }}"
                               min="0" step="0.01"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 text-right tabular-nums">
                        <p class="text-[10px] text-gray-400 mt-1">Déclenche réappro.</p>
                    </div>
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 4. COMPTABILITÉ (Sage GesCom)
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-indigo-500 rounded-full"></span>
                    Comptabilité
                    <span class="text-[10px] font-normal text-gray-400 normal-case">(facultatif — utilise la famille si vide)</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-emerald-700 mb-1">Compte de vente (701x)</label>
                        <select name="sale_account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="">— Hériter famille —</option>
                            @foreach($accounts->filter(fn($a) => str_starts_with($a->code, '7')) as $a)
                                <option value="{{ $a->id }}" @selected(old('sale_account_id', $p->sale_account_id ?? '') == $a->id)>
                                    {{ $a->code }} — {{ $a->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-orange-700 mb-1">Compte d'achat (601x)</label>
                        <select name="purchase_account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500">
                            <option value="">— Hériter famille —</option>
                            @foreach($accounts->filter(fn($a) => str_starts_with($a->code, '6')) as $a)
                                <option value="{{ $a->id }}" @selected(old('purchase_account_id', $p->purchase_account_id ?? '') == $a->id)>
                                    {{ $a->code }} — {{ $a->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-purple-700 mb-1">Compte de stock (311x)</label>
                        <select name="stock_account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500">
                            <option value="">— Hériter famille —</option>
                            @foreach($accounts->filter(fn($a) => str_starts_with($a->code, '3')) as $a)
                                <option value="{{ $a->id }}" @selected(old('stock_account_id', $p->stock_account_id ?? '') == $a->id)>
                                    {{ $a->code }} — {{ $a->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 5. FOURNISSEUR PRINCIPAL
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-cyan-500 rounded-full"></span>
                    Fournisseur principal
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Fournisseur</label>
                        <select name="default_supplier_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500">
                            <option value="">— Aucun fournisseur principal —</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}" @selected(old('default_supplier_id', $p->default_supplier_id ?? '') == $s->id)>
                                    {{ $s->code }} — {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Délai de livraison</label>
                        <div class="relative">
                            <input type="number" name="delivery_delay_days"
                                   value="{{ old('delivery_delay_days', $p->delivery_delay_days ?? '') }}"
                                   min="0" max="365"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-cyan-500 text-right tabular-nums pr-14">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">jours</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Référence chez le fournisseur
                        <span class="text-gray-400 font-normal">(SKU fournisseur)</span>
                    </label>
                    <input type="text" name="supplier_reference"
                           value="{{ old('supplier_reference', $p->supplier_reference ?? '') }}"
                           maxlength="80"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-cyan-500">
                </div>
            </div>

            {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
                 6. COMPOSANTS (Kit) — si type = compose
            ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
            <div x-show="type === 'compose'" x-cloak
                 class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <span class="w-1.5 h-5 bg-purple-500 rounded-full"></span>
                        Composants du kit
                    </h3>
                    <button type="button" @click="addComponent()"
                            class="inline-flex items-center gap-1 text-xs font-medium text-purple-600 hover:text-purple-800 bg-purple-50 px-2 py-1 rounded">
                        + Ajouter
                    </button>
                </div>

                <template x-if="components.length === 0">
                    <p class="text-sm text-gray-400 text-center py-4">Cliquez sur « + Ajouter » pour définir les articles qui composent ce kit.</p>
                </template>

                <template x-for="(c, i) in components" :key="i">
                    <div class="flex items-end gap-3 bg-gray-50 rounded-lg p-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Article composant</label>
                            <select :name="`components[${i}][component_product_id]`" x-model="c.component_product_id" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">— Choisir —</option>
                                @foreach($componentProducts as $cp)
                                    <option value="{{ $cp->id }}">{{ $cp->name }} ({{ $cp->reference }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="width:120px">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Quantité</label>
                            <input type="number" :name="`components[${i}][quantity]`" x-model="c.quantity"
                                   min="0.001" step="0.001" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right">
                        </div>
                        <button type="button" @click="removeComponent(i)"
                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded mb-0.5">✕</button>
                    </div>
                </template>
            </div>

        </div>

        {{-- ═══════════ COLONNE LATÉRALE (1/3) ═══════════ --}}
        <div class="space-y-5">

            {{-- ━━━ TYPE + FAMILLE + UNITÉ ━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-blue-500 rounded-full"></span>
                    Classification
                </h3>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select name="type" x-model="type" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="simple">Article simple</option>
                        <option value="service">Service / Prestation</option>
                        <option value="compose">Composé (Kit / Nomenclature)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Famille</label>
                    <select name="family_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">— Aucune —</option>
                        @foreach($families as $f)
                            <option value="{{ $f->id }}" @selected(old('family_id', $p->family_id ?? '') == $f->id)>{{ $f->name }}</option>
                            @foreach($f->children as $child)
                                <option value="{{ $child->id }}" @selected(old('family_id', $p->family_id ?? '') == $child->id)>
                                    &nbsp;&nbsp;└ {{ $child->name }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Unité de mesure</label>
                    <select name="unit_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">— Aucune —</option>
                        @foreach($units as $u)
                            <option value="{{ $u->id }}" @selected(old('unit_id', $p->unit_id ?? '') == $u->id)>
                                {{ $u->name }} ({{ $u->abbreviation }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- ━━━ COMPORTEMENT (flags) ━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-teal-500 rounded-full"></span>
                    Comportement
                </h3>

                @foreach([
                    ['name' => 'is_stockable',   'label' => 'Géré en stock',    'desc' => 'Suivi des entrées/sorties', 'default' => true],
                    ['name' => 'is_purchasable', 'label' => 'Achetable',        'desc' => 'Peut figurer sur un BC',     'default' => true],
                    ['name' => 'is_sellable',    'label' => 'Vendable',         'desc' => 'Peut être facturé',          'default' => true],
                    ['name' => 'is_active',      'label' => 'Actif',            'desc' => 'Visible dans les listes',    'default' => true],
                ] as $opt)
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="hidden" name="{{ $opt['name'] }}" value="0">
                    <input type="checkbox" name="{{ $opt['name'] }}" value="1"
                           {{ old($opt['name'], $p->{$opt['name']} ?? $opt['default']) ? 'checked' : '' }}
                           class="mt-0.5 w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                    <div>
                        <span class="text-sm font-medium text-gray-700">{{ $opt['label'] }}</span>
                        <p class="text-xs text-gray-400">{{ $opt['desc'] }}</p>
                    </div>
                </label>
                @endforeach
            </div>

            {{-- ━━━ TRAÇABILITÉ ━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-orange-500 rounded-full"></span>
                    Traçabilité
                </h3>

                @foreach([
                    ['name' => 'has_lot_number',    'label' => 'Numéro de lot',     'desc' => 'Produits chimiques, alimentaires'],
                    ['name' => 'has_serial_number', 'label' => 'Numéro de série',   'desc' => 'Matériel, équipements'],
                    ['name' => 'has_expiry_date',   'label' => 'Date d\'expiration', 'desc' => 'Médicaments, périssables'],
                ] as $opt)
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="hidden" name="{{ $opt['name'] }}" value="0">
                    <input type="checkbox" name="{{ $opt['name'] }}" value="1"
                           {{ old($opt['name'], $p->{$opt['name']} ?? false) ? 'checked' : '' }}
                           class="mt-0.5 w-4 h-4 text-orange-500 rounded focus:ring-orange-400">
                    <div>
                        <span class="text-sm font-medium text-gray-700">{{ $opt['label'] }}</span>
                        <p class="text-xs text-gray-400">{{ $opt['desc'] }}</p>
                    </div>
                </label>
                @endforeach
            </div>

            {{-- ━━━ POIDS ━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-gray-400 rounded-full"></span>
                    Poids
                </h3>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <input type="number" name="weight" value="{{ old('weight', $p->weight ?? '') }}"
                               min="0" step="0.001" placeholder="0.000"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 text-right tabular-nums">
                    </div>
                    <div style="width:80px">
                        <select name="weight_unit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @php $wu = old('weight_unit', $p->weight_unit ?? 'kg'); @endphp
                            <option value="kg" @selected($wu === 'kg')>kg</option>
                            <option value="g"  @selected($wu === 'g')>g</option>
                            <option value="t"  @selected($wu === 't')>t</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- ━━━ PHOTO ━━━ --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-1.5 h-5 bg-pink-400 rounded-full"></span>
                    Photo
                </h3>
                @if($isEdit && $p->image)
                <div class="mb-2 w-full h-32 bg-gray-50 rounded-lg border overflow-hidden">
                    <img src="{{ url(Storage::url($p->image)) }}" alt="" class="w-full h-full object-contain">
                </div>
                @endif
                <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 cursor-pointer
                              file:mr-3 file:py-1 file:px-3 file:border-0 file:bg-blue-50 file:text-blue-700
                              file:rounded file:text-xs file:font-medium hover:file:bg-blue-100">
                <p class="text-[10px] text-gray-400">JPEG, PNG, GIF, WebP — max 2 Mo</p>
            </div>
        </div>
    </div>

    {{-- ── Boutons d'action ── --}}
    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('products.index') }}"
           class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg">
            Annuler
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg">
            ✓ {{ $isEdit ? 'Enregistrer' : 'Créer l\'article' }}
        </button>
    </div>
</form>

@push('scripts')
<script>
function productForm(init) {
    return {
        type:          init.type || 'simple',
        purchasePrice: Number(init.purchasePrice) || 0,
        marginRate:    Number(init.marginRate)    || 0,
        salePrice:     Number(init.salePrice)     || 0,
        components:    init.components || [],

        addComponent()    { this.components.push({ component_product_id: '', quantity: 1 }); },
        removeComponent(i){ this.components.splice(i, 1); },

        actualMargin() {
            if (!this.salePrice) return 0;
            return ((this.salePrice - this.purchasePrice) / this.salePrice) * 100;
        },
        recomputeMargin() {
            this.marginRate = Math.round(this.actualMargin() * 100) / 100;
        },
        recomputeFromMargin() {
            if (this.marginRate >= 100 || !this.purchasePrice) return;
            this.salePrice = Math.round(this.purchasePrice / (1 - this.marginRate / 100));
        },
        formatPercent(v) {
            return (Math.round(v * 100) / 100).toFixed(2) + ' %';
        },
        formatMoney(v) {
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(v) + ' FCFA';
        },
    };
}
</script>
@endpush
