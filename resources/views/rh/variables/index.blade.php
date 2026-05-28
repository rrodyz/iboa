@extends('layouts.erp')
@section('title', 'Variables mensuelles — RH')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Variables mensuelles</span>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Variables mensuelles</h1>
        <p class="text-sm text-gray-500 mt-1">Primes, heures sup., absences et retenues saisies par bulletin</p>
    </div>
</div>

@if($runs->isEmpty())
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-16 text-center">
    <div class="text-gray-400 text-lg">Aucun bulletin de paie disponible.</div>
    <a href="{{ route('rh.paie.create') }}" class="mt-4 inline-block text-indigo-600 hover:text-indigo-800 text-sm font-medium">
        Créer le premier bulletin
    </a>
</div>
@else
<div class="space-y-3">
    @foreach($runs as $run)
    @php
        $periodDate  = \Carbon\Carbon::createFromDate($run->period_year, $run->period_month, 1);
        $periodLabel = ucfirst($periodDate->translatedFormat('F Y'));
        $statusClass = match($run->status) {
            'brouillon' => 'bg-gray-100 text-gray-600',
            'calcule'   => 'bg-blue-100 text-blue-700',
            'valide'    => 'bg-green-100 text-green-700',
            'paye'      => 'bg-emerald-100 text-emerald-700',
            default     => 'bg-gray-100 text-gray-600',
        };
        $statusLabel = match($run->status) {
            'brouillon' => 'Brouillon',
            'calcule'   => 'Calculé',
            'valide'    => 'Validé',
            'paye'      => 'Payé',
            default     => ucfirst($run->status),
        };
        $varUrl = route('rh.paie.variables', $run);
    @endphp

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
         x-data="{
             open: false,
             loading: false,
             loaded: false,
             vars: [],
             async toggle() {
                 if (this.open) { this.open = false; return; }
                 if (!this.loaded) {
                     this.loading = true;
                     try {
                         const r = await fetch('{{ $varUrl }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                         this.vars = await r.json();
                         this.loaded = true;
                     } catch(e) {
                         this.vars = [];
                         this.loaded = true;
                     }
                     this.loading = false;
                 }
                 this.open = true;
             }
         }">

        {{-- En-tête --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
            <div class="flex items-center gap-3">
                <div>
                    <span class="font-semibold text-gray-900">{{ $periodLabel }}</span>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                    <span class="ml-2 text-sm text-gray-400">{{ $run->items_count ?? 0 }} employé(s)</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('rh.paie.show', $run) }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Ouvrir le bulletin
                </a>

                <button @click="toggle()"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg border transition-colors"
                        :class="open ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-600 hover:bg-gray-100'">
                    {{-- Icône chargement --}}
                    <svg x-show="loading" class="w-4 h-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{-- Icône chevron --}}
                    <svg x-show="!loading" class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span x-text="open ? 'Masquer' : 'Variables saisies'">Variables saisies</span>
                </button>
            </div>
        </div>

        {{-- Panneau variables --}}
        <div x-show="open" x-transition:enter="transition-all ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition-all ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
             class="border-t border-gray-100 px-5 py-4" style="display:none;">

            {{-- Aucune variable --}}
            <template x-if="vars.length === 0">
                <div class="flex flex-col items-center py-6 text-center">
                    <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm text-gray-400 italic">Aucune variable saisie pour ce bulletin.</p>
                    @if($run->isEditable())
                    <a href="{{ route('rh.paie.show', $run) }}"
                       class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                        + Ajouter des variables sur le bulletin
                    </a>
                    @endif
                </div>
            </template>

            {{-- Tableau des variables --}}
            <template x-if="vars.length > 0">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 border border-gray-200">Employé</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 border border-gray-200">Type</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 border border-gray-200">Libellé</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 border border-gray-200">Qté</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 border border-gray-200">Montant (F)</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 border border-gray-200">Imposable</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 border border-gray-200">CNSS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="v in vars" :key="v.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 border border-gray-100 font-medium text-gray-800" x-text="v.employee?.full_name ?? '—'"></td>
                                <td class="px-3 py-2 border border-gray-100">
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700" x-text="v.type ?? '—'"></span>
                                </td>
                                <td class="px-3 py-2 border border-gray-100 text-gray-700" x-text="v.label || v.type || '—'"></td>
                                <td class="px-3 py-2 border border-gray-100 text-center text-gray-600">
                                    <span x-text="v.qty ? v.qty + ' ' + (v.unit ?? '') : '—'"></span>
                                </td>
                                <td class="px-3 py-2 border border-gray-100 text-right font-mono font-semibold text-gray-900"
                                    x-text="Number(v.amount).toLocaleString('fr-FR')"></td>
                                <td class="px-3 py-2 border border-gray-100 text-center">
                                    <span x-show="v.is_taxable" class="text-xs text-green-600 font-bold">✓</span>
                                    <span x-show="!v.is_taxable" class="text-xs text-gray-300">—</span>
                                </td>
                                <td class="px-3 py-2 border border-gray-100 text-center">
                                    <span x-show="v.is_social_charged" class="text-xs text-green-600 font-bold">✓</span>
                                    <span x-show="!v.is_social_charged" class="text-xs text-gray-300">—</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold">
                            <td colspan="4" class="px-3 py-2 border border-gray-200 text-sm text-gray-700">Total variables</td>
                            <td class="px-3 py-2 border border-gray-200 text-right font-mono text-gray-900"
                                x-text="vars.reduce((s,v) => s + Number(v.amount), 0).toLocaleString('fr-FR')"></td>
                            <td colspan="2" class="px-3 py-2 border border-gray-200"></td>
                        </tr>
                    </tfoot>
                </table>
            </template>
        </div>
    </div>
    @endforeach
</div>

{{-- Pagination --}}
@if($runs->hasPages())
<div class="mt-4">{{ $runs->links() }}</div>
@endif
@endif
@endsection
