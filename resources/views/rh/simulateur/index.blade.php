@extends('layouts.erp')
@section('title', 'Simulateur de salaire')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">RH &ndash; Paie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Simulateur</span>
@endsection

@section('content')
<div
    x-data="simulateur()"
    x-init="init()"
    class="max-w-5xl mx-auto space-y-6">

    {{-- En-tete --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <span class="text-2xl">🧮</span>
                Simulateur de salaire inverse
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Saisissez le net souhaite &mdash; le simulateur calcule le brut, les cotisations et le cout employeur.
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-400 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            CNSS sal. {{ $payroll->cnss_employee_rate }}%
            &nbsp;&middot;&nbsp; CNSS pat. {{ $payroll->cnss_employer_rate }}%
            &nbsp;&middot;&nbsp; Abattement IUTS {{ $payroll->iuts_abattement_rate }}%
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        {{-- Formulaire --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Net souhaite --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                    Net a payer souhaite
                </h2>
                <div class="relative">
                    <input
                        type="number"
                        x-model.number="form.net_souhaite"
                        @input.debounce.600ms="simulate()"
                        min="1"
                        step="1000"
                        placeholder="300 000"
                        class="w-full pl-4 pr-16 py-3 text-xl font-bold text-gray-900 border-2 border-emerald-400 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-emerald-500
                               bg-emerald-50 placeholder-gray-300"
                    >
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">FCFA</span>
                </div>
            </div>

            {{-- Situation familiale --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                    Situation familiale
                </h2>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    @foreach(['celibataire' => 'Celibataire', 'marie' => 'Marie(e)', 'veuf' => 'Veuf/Veuve'] as $val => $label)
                    <label class="cursor-pointer">
                        <input type="radio" x-model="form.family_status" value="{{ $val }}"
                               @change="simulate()" class="sr-only peer">
                        <div class="text-center px-2 py-2.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600
                                    peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-700
                                    hover:border-blue-300 transition-colors">
                            {{ $label }}
                        </div>
                    </label>
                    @endforeach
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1.5">Nombre de charges (enfants)</label>
                    <div class="flex items-center gap-3">
                        <button @click="form.nb_children = Math.max(0, form.nb_children-1); simulate()"
                                type="button"
                                class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 font-bold transition-colors">
                            &minus;
                        </button>
                        <span class="text-xl font-bold text-gray-900 w-8 text-center" x-text="form.nb_children"></span>
                        <button @click="form.nb_children = Math.min(15, form.nb_children+1); simulate()"
                                type="button"
                                class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-600 font-bold transition-colors">
                            +
                        </button>
                        <span class="text-xs text-gray-400 ml-1"
                              x-text="'= ' + nbPartsLabel + ' part(s)'"></span>
                    </div>
                </div>
            </div>

            {{-- Primes et retenues --}}
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                    Primes &amp; retenues
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Primes imposables incluses dans le brut</label>
                        <div class="relative">
                            <input type="number" x-model.number="form.prime_imposable"
                                   @input.debounce.500ms="simulate()"
                                   min="0" step="1000" placeholder="0"
                                   class="w-full pl-3 pr-16 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">FCFA</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">Transport, logement taxable, etc.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Primes non imposables (hors brut)</label>
                        <div class="relative">
                            <input type="number" x-model.number="form.prime_non_imposable"
                                   @input.debounce.500ms="simulate()"
                                   min="0" step="1000" placeholder="0"
                                   class="w-full pl-3 pr-16 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-300">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">FCFA</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">Indemnites exonerees (panier, deplacement, etc.)</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Avances / Retenues sur salaire</label>
                        <div class="relative">
                            <input type="number" x-model.number="form.avances"
                                   @input.debounce.500ms="simulate()"
                                   min="0" step="1000" placeholder="0"
                                   class="w-full pl-3 pr-16 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">FCFA</span>
                        </div>
                    </div>
                </div>
            </div>

            <button
                @click="simulate()"
                :disabled="loading || !form.net_souhaite"
                class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 disabled:opacity-50
                       text-white text-sm font-semibold rounded-xl transition-colors flex items-center justify-center gap-2">
                <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span x-text="loading ? 'Calcul en cours...' : 'Calculer'"></span>
            </button>
        </div>

        {{-- Resultats --}}
        <div class="lg:col-span-3 space-y-4">

            {{-- Etat initial / vide --}}
            <div x-show="!result && !loading" x-cloak
                 class="bg-white rounded-xl border border-dashed border-gray-200 p-12 text-center">
                <div class="text-5xl mb-3">🧮</div>
                <p class="text-gray-400 text-sm">Saisissez un net souhaite pour voir le resultat</p>
            </div>

            {{-- Chargement --}}
            <div x-show="loading" x-cloak
                 class="bg-white rounded-xl border border-gray-200 p-12 flex items-center justify-center gap-3 text-gray-400">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm">Calcul iteratif en cours&hellip;</span>
            </div>

            {{-- Resultats --}}
            <template x-if="result">
                <div class="space-y-4">

                    {{-- Alerte ecart --}}
                    <div x-show="!result.exact"
                         class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>Net exact impossible : ecart de <strong x-text="fmt(result.ecart)"></strong> FCFA.
                        Le brut propose donne <strong x-text="fmt(result.net_calcule)"></strong> FCFA.</span>
                    </div>

                    {{-- KPIs principaux --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <p class="text-xs text-blue-500 font-medium uppercase tracking-wide mb-1">Salaire brut</p>
                            <p class="text-2xl font-bold text-blue-700 tabular-nums" x-text="fmt(result.salaire_brut) + ' F'"></p>
                            <p class="text-xs text-blue-400 mt-1">Base : <span x-text="fmt(result.salaire_base) + ' F'"></span></p>
                        </div>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                            <p class="text-xs text-emerald-500 font-medium uppercase tracking-wide mb-1">Net a payer</p>
                            <p class="text-2xl font-bold text-emerald-700 tabular-nums" x-text="fmt(result.net_calcule) + ' F'"></p>
                            <p class="text-xs text-emerald-400 mt-1">Cible : <span x-text="fmt(result.net_souhaite) + ' F'"></span></p>
                        </div>
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                            <p class="text-xs text-red-500 font-medium uppercase tracking-wide mb-1">CNSS salarie</p>
                            <p class="text-xl font-bold text-red-700 tabular-nums" x-text="fmt(result.cnss_employee) + ' F'"></p>
                        </div>
                        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                            <p class="text-xs text-purple-500 font-medium uppercase tracking-wide mb-1">IUTS</p>
                            <p class="text-xl font-bold text-purple-700 tabular-nums" x-text="fmt(result.iuts) + ' F'"></p>
                            <p class="text-xs text-purple-400 mt-1"><span x-text="result.nb_parts"></span> part(s) fiscale(s)</p>
                        </div>
                        <div class="col-span-2 bg-gray-900 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Cout total employeur</p>
                                <p class="text-2xl font-bold text-white tabular-nums" x-text="fmt(result.cout_employeur) + ' F'"></p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Brut + CNSS patronal (<span x-text="fmt(result.cnss_employer) + ' F'"></span>)
                                </p>
                            </div>
                            <div class="text-4xl">💼</div>
                        </div>
                    </div>

                    {{-- Detail ligne par ligne --}}
                    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-sm font-semibold text-gray-700">Detail du calcul</h3>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <template x-for="(row, idx) in result.detail" :key="idx">
                                <div>
                                    {{-- Separateur section employeur (1 seule fois) --}}
                                    <div x-show="row.section === 'employeur' && (idx === 0 || result.detail[idx-1].section !== 'employeur')"
                                         class="px-4 py-1.5 bg-gray-900 text-xs text-gray-400 uppercase tracking-wide font-semibold">
                                        Charge patronale
                                    </div>
                                    <div class="flex items-center justify-between px-4 py-2.5"
                                         :class="row.bold ? 'bg-gray-50' : 'bg-white'">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 text-center font-mono text-sm font-bold"
                                                  :class="{
                                                      'text-emerald-600': row.signe === '+',
                                                      'text-red-500':    row.signe === '-',
                                                      'text-gray-500':   row.signe === '='
                                                  }"
                                                  x-text="row.signe"></span>
                                            <span class="text-sm"
                                                  :class="row.bold ? 'font-semibold text-gray-900' : 'text-gray-600'"
                                                  x-text="row.label"></span>
                                        </div>
                                        <span class="font-mono text-sm tabular-nums"
                                              :class="{
                                                  'font-bold text-emerald-700': row.color === 'green',
                                                  'font-bold text-blue-700':    row.color === 'blue',
                                                  'font-bold text-rose-700':    row.color === 'rose',
                                                  'text-red-600':    row.color === 'red',
                                                  'text-purple-600': row.color === 'purple',
                                                  'text-amber-600':  row.color === 'amber',
                                                  'text-emerald-600': row.color === 'emerald',
                                                  'text-indigo-600': row.color === 'indigo',
                                                  'text-gray-700':   row.color === 'gray',
                                                  'text-orange-600': row.color === 'orange',
                                              }"
                                              x-text="(row.montant > 0 || row.bold) ? fmt(row.montant) + ' F' : '—'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Taux effectifs --}}
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Taux effectifs</h3>
                        <div class="space-y-2">
                            <template x-for="item in tauxEffectifs" :key="item.label">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500 w-40 flex-shrink-0" x-text="item.label"></span>
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-700"
                                             :class="item.color"
                                             :style="'width:' + Math.min(item.pct, 100) + '%'"></div>
                                    </div>
                                    <span class="text-xs font-mono font-semibold text-gray-700 w-14 text-right"
                                          x-text="item.pct.toFixed(1) + ' %'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </template>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function simulateur() {
    return {
        form: {
            net_souhaite:        null,
            family_status:       'celibataire',
            nb_children:         0,
            prime_imposable:     0,
            prime_non_imposable: 0,
            avances:             0,
        },
        result:  null,
        loading: false,
        error:   null,

        init() {
            // Taux depuis les param. Blade (servis une seule fois au chargement)
        },

        get nbPartsLabel() {
            // Calcul indicatif cote client pour affichage immédiat
            const parts = {
                celibataire: {{ $payroll->parts_base_single  ?? 1 }},
                marie:       {{ $payroll->parts_base_married ?? 2 }},
                veuf:        {{ $payroll->parts_base_widowed ?? 2 }},
            };
            const partsPerChild = {{ $payroll->parts_per_child ?? 0.5 }};
            const maxParts      = {{ $payroll->nb_parts_max   ?? 6.5 }};
            const base = parts[this.form.family_status] ?? 1;
            return Math.min(base + this.form.nb_children * partsPerChild, maxParts).toFixed(1);
        },

        get tauxEffectifs() {
            if (!this.result || !this.result.salaire_brut) return [];
            const b = this.result.salaire_brut;
            const t = this.result.cout_employeur;
            return [
                { label: 'CNSS salarié / brut',  pct: this.result.cnss_employee / b * 100, color: 'bg-red-400' },
                { label: 'IUTS / brut',           pct: this.result.iuts          / b * 100, color: 'bg-purple-400' },
                { label: 'Pression fiscale tot.', pct: (this.result.cnss_employee + this.result.iuts) / b * 100, color: 'bg-blue-400' },
                { label: 'Net / cout emp.',       pct: this.result.net_calcule   / t * 100, color: 'bg-emerald-400' },
            ];
        },

        async simulate() {
            if (!this.form.net_souhaite || this.form.net_souhaite <= 0) {
                this.result = null;
                return;
            }
            this.loading = true;
            this.error   = null;
            try {
                const resp = await fetch('{{ route("rh.paie.simulateur.calculate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.form),
                });
                if (!resp.ok) {
                    const e = await resp.json().catch(() => ({}));
                    throw new Error(e.message ?? 'Erreur serveur');
                }
                this.result = await resp.json();
            } catch (e) {
                this.error  = e.message;
                this.result = null;
            } finally {
                this.loading = false;
            }
        },

        fmt(n) {
            return Math.round(Number(n) || 0)
                .toString()
                .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        },
    };
}
</script>
@endpush
