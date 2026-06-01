{{--
    Combobox produit avec recherche instantanée
    ─────────────────────────────────────────────────────────────────────────────
    Usage dans une <td> x-for items :

        @include('ventes.partials._product_combobox', [
            'accentColor' => 'indigo',   // indigo | blue
            'formName'    => 'invoice',  // préfixe unique pour les IDs
        ])

    Prérequis dans le composant Alpine parent :
        - items[].product_id
        - items[]._ps_open   = false
        - items[]._ps_search = ''
        - items[]._ps_rect   = null
        - products (array)
        - onProductChange(index)
        - formatFcfa(n)
--}}

@php
    $accent   = $accentColor ?? 'indigo';
    $ringCls  = "focus:ring-{$accent}-500 focus:border-{$accent}-500";
    $chkCls   = "text-{$accent}-500";
    $hovCls   = "hover:bg-{$accent}-50";
    $selCls   = "bg-{$accent}-50";
    $cntCls   = "text-{$accent}-500";
@endphp

<td class="px-3 py-2">

    {{-- Champ hidden pour la soumission --}}
    <input type="hidden"
           :name="'items[' + index + '][product_id]'"
           :value="item.product_id">

    {{-- Bouton déclencheur --}}
    <button type="button"
            x-ref="psbtn"
            @click="
                if (!item._ps_open) {
                    items.forEach((it, i) => { if (i !== index) it._ps_open = false; });
                    const r = $refs.psbtn.getBoundingClientRect();
                    item._ps_rect = { top: r.bottom + 4, left: r.left, width: Math.max(r.width, 280) };
                    item._ps_open = true;
                    $nextTick(() => { const el = document.getElementById('ps_{{ $formName ?? 'f' }}_' + item._key); if (el) el.focus(); });
                } else {
                    item._ps_open = false;
                }
            "
            class="w-full flex items-center justify-between gap-1 border border-gray-300 rounded px-2 py-1.5 text-sm bg-white hover:border-{{ $accent }}-400 focus:outline-none focus:ring-1 {{ $ringCls }} min-w-[160px] transition-colors"
            :class="item._ps_open ? 'border-{{ $accent }}-500 ring-1 ring-{{ $accent }}-500' : ''">
        <span class="truncate text-left"
              :class="item.product_id ? 'text-gray-900' : 'text-gray-400'"
              x-text="item.product_id
                  ? (products.find(p => String(p.id) === String(item.product_id))?.name || '— Produit —')
                  : '— Produit —'">
        </span>
        <svg class="w-3 h-3 text-gray-400 flex-shrink-0 transition-transform duration-150"
             :class="item._ps_open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Overlay transparent pour fermer en cliquant ailleurs --}}
    <div x-show="item._ps_open"
         x-cloak
         @click="item._ps_open = false; item._ps_search = ''"
         class="fixed inset-0 z-40"
         style="background: transparent;">
    </div>

    {{-- Dropdown fixe (échappe à overflow:auto de la table) --}}
    <div x-show="item._ps_open"
         x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed z-50 bg-white border border-gray-200 rounded-xl shadow-2xl overflow-hidden"
         :style="item._ps_rect ? 'top:' + item._ps_rect.top + 'px; left:' + item._ps_rect.left + 'px; width:' + item._ps_rect.width + 'px; min-width:280px;' : ''">

        {{-- Champ de recherche --}}
        <div class="p-2.5 border-b border-gray-100 bg-gray-50">
            <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5 text-{{ $accent }}-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input :id="'ps_{{ $formName ?? 'f' }}_' + item._key"
                       x-model="item._ps_search"
                       type="text"
                       placeholder="Rechercher par nom ou référence..."
                       class="w-full bg-transparent text-sm focus:outline-none text-gray-700 placeholder-gray-400"
                       @keydown.escape="item._ps_open = false; item._ps_search = ''"
                       @keydown.enter.prevent="">
                <button x-show="item._ps_search"
                        type="button"
                        @click.stop="item._ps_search = ''"
                        class="text-gray-400 hover:text-gray-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Liste --}}
        <ul class="max-h-64 overflow-y-auto divide-y divide-gray-50">

            <li @click="item.product_id = ''; item._ps_open = false; item._ps_search = ''; onProductChange(index)"
                class="flex items-center gap-2 px-3 py-2.5 text-sm cursor-pointer hover:bg-gray-50 transition-colors"
                :class="!item.product_id ? '{{ $selCls }} text-{{ $accent }}-600' : 'text-gray-500'">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="italic">— Aucun produit —</span>
            </li>

            <template x-for="p in products.filter(p =>
                !item._ps_search ||
                p.name.toLowerCase().includes(item._ps_search.toLowerCase()) ||
                (p.reference && p.reference.toLowerCase().includes(item._ps_search.toLowerCase()))
            ).slice(0, 60)" :key="p.id">
                <li @click="item.product_id = String(p.id); item._ps_open = false; item._ps_search = ''; onProductChange(index)"
                    class="px-3 py-2.5 cursor-pointer {{ $hovCls }} transition-colors"
                    :class="String(item.product_id) === String(p.id) ? '{{ $selCls }}' : ''">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate leading-tight" x-text="p.name"></p>
                            {{-- Méta : référence • Stock N • prix --}}
                            <div class="flex items-center flex-wrap gap-x-1.5 gap-y-0.5 text-xs mt-0.5 leading-tight">
                                <span class="text-gray-400" x-show="p.reference" x-text="p.reference"></span>
                                <span class="text-gray-300" x-show="p.reference && p.is_stockable">•</span>
                                {{-- Badge stock (produits stockables uniquement) --}}
                                <span x-show="p.is_stockable"
                                      class="font-medium"
                                      :class="(parseFloat(p.stock_qty) || 0) > 0 ? 'text-emerald-600' : 'text-rose-500'"
                                      x-text="'Stock ' + (parseFloat(p.stock_qty) || 0)"></span>
                                <span class="text-gray-300" x-show="p.reference || p.is_stockable">•</span>
                                <span class="text-gray-500" x-text="formatFcfa(p.sale_price)"></span>
                            </div>
                        </div>
                        <svg x-show="String(item.product_id) === String(p.id)"
                             class="w-4 h-4 {{ $chkCls }} flex-shrink-0"
                             fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </li>
            </template>

            <li x-show="item._ps_search && products.filter(p =>
                    p.name.toLowerCase().includes(item._ps_search.toLowerCase()) ||
                    (p.reference && p.reference.toLowerCase().includes(item._ps_search.toLowerCase()))
                ).length === 0"
                class="px-4 py-6 text-sm text-gray-400 text-center">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Aucun produit pour "<span x-text="item._ps_search"></span>"
            </li>
        </ul>

        {{-- Pied --}}
        <div class="px-3 py-1.5 border-t border-gray-100 bg-gray-50 flex items-center justify-between"
             x-show="item._ps_search">
            <p class="text-xs text-gray-400">
                <span x-text="products.filter(p =>
                    p.name.toLowerCase().includes(item._ps_search.toLowerCase()) ||
                    (p.reference && p.reference.toLowerCase().includes(item._ps_search.toLowerCase()))
                ).length"></span> résultat(s)
            </p>
            <button type="button" @click.stop="item._ps_search = ''"
                    class="text-xs {{ $cntCls }} hover:opacity-70">Effacer</button>
        </div>
    </div>
</td>
