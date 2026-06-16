@extends('layouts.erp')
@section('title', 'Taux de TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Taux de TVA</span>
@endsection

@section('content')
<div x-data="{
    modal: '',
    form: { name: '', short_name: '', rate: '', type: 'tva', collected_account_id: '', deductible_account_id: '', is_default: false, is_active: true },
    editId: null,
    openCreate() {
        this.form = { name: '', short_name: '', rate: '', type: 'tva', collected_account_id: '', deductible_account_id: '', is_default: false, is_active: true };
        this.editId = null; this.modal = 'form';
    },
    openEdit(id, name, short_name, rate, type, collected, deductible, isActive) {
        this.form = { name, short_name, rate, type: type || 'tva', collected_account_id: collected || '', deductible_account_id: deductible || '', is_default: false, is_active: isActive };
        this.editId = id; this.modal = 'form';
    },
}" class="space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Taux de TVA</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gérez les taux de taxe appliqués à vos articles et factures</p>
        </div>
        @can('settings.manage')
        <button @click="openCreate()"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nouveau taux
        </button>
        @endcan
    </div>

    {{-- Info banner --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm text-blue-700">
            Le taux <strong>par défaut</strong> est automatiquement appliqué aux nouveaux articles.
            Un taux à <strong>0%</strong> représente une opération exonérée ou hors taxe.
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Libellé</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Code court</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Taux</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Par défaut</th>
                    <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Simulation (10 000)</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($taxRates as $tax)
                @php
                    $taxAmount = round(10000 * $tax->rate / 100);
                    $isRetenue = $tax->type === 'retenue';
                    $ttc = $isRetenue ? 10000 - $taxAmount : 10000 + $taxAmount;
                @endphp
                <tr class="{{ $tax->is_default ? 'bg-blue-50/40' : '' }} hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-medium text-gray-900">
                        {{ $tax->name }}
                        @if($tax->is_default)
                        <span class="ml-2 text-xs font-medium text-blue-600 bg-blue-100 px-1.5 py-0.5 rounded">Défaut</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs font-semibold bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $tax->short_name }}</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($tax->type === 'retenue')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Retenue source</span>
                        @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">TVA</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-2xl font-bold tabular-nums {{ $tax->rate == 0 ? 'text-gray-400' : 'text-gray-900' }}">{{ number_format($tax->rate, 2, ',', '') }}</span>
                        <span class="text-sm text-gray-500">%</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        @if($tax->is_default)
                        <svg class="w-4 h-4 text-blue-600 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tax->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $tax->is_active ? 'Actif' : 'Inactif' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="text-xs text-gray-500">HT 10 000</span>
                        <span class="mx-1 text-gray-300">→</span>
                        @if($isRetenue)
                        <span class="text-xs font-medium text-amber-600">Ret. -{{ number_format($taxAmount, 0, ',', ' ') }}</span>
                        <span class="mx-1 text-gray-300">→</span>
                        <span class="text-xs font-semibold text-gray-900">Net {{ number_format($ttc, 0, ',', ' ') }}</span>
                        @else
                        <span class="text-xs font-medium text-gray-700">TVA {{ number_format($taxAmount, 0, ',', ' ') }}</span>
                        <span class="mx-1 text-gray-300">→</span>
                        <span class="text-xs font-semibold text-gray-900">TTC {{ number_format($ttc, 0, ',', ' ') }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3">
                        @can('settings.manage')
                        <div class="flex items-center justify-end gap-2">
                            @if(!$tax->is_default)
                            <form method="POST" action="{{ route('settings.tax-rates.set-default', $tax) }}">@csrf
                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">Par défaut</button>
                            </form>
                            @endif
                            <button @click="openEdit({{ $tax->id }}, '{{ addslashes($tax->name) }}', '{{ addslashes($tax->short_name) }}', '{{ $tax->rate }}', '{{ $tax->type }}', {{ $tax->collected_account_id ?? 'null' }}, {{ $tax->deductible_account_id ?? 'null' }}, {{ $tax->is_active ? 'true' : 'false' }})"
                                    class="text-xs text-gray-500 hover:text-gray-700 font-medium">Modifier</button>
                            @if(!$tax->is_default)
                            <form method="POST" action="{{ route('settings.tax-rates.destroy', $tax) }}"
                                  onsubmit="return confirm('Supprimer le taux {{ addslashes($tax->name) }} ?')">@csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-medium">Supprimer</button>
                            </form>
                            @endif
                        </div>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-5 py-16 text-center text-gray-400">Aucun taux de TVA configuré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <div x-show="modal === 'form'" x-transition
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-5"
                x-text="editId ? 'Modifier le taux' : 'Nouveau taux de TVA'"></h3>

            <form :method="'POST'"
                  :action="editId ? `/parametres/taux-tva/${editId}` : '{{ route('settings.tax-rates.store') }}'"
                  class="space-y-4">
                @csrf
                <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="form.name" required maxlength="50"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                               placeholder="TVA 18%">
                    </div>

                    {{-- Type de taxe --}}
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label @click="form.type = 'tva'"
                                   :class="form.type === 'tva' ? 'border-blue-400 bg-blue-50 ring-2 ring-blue-300' : 'border-gray-200 hover:border-blue-200'"
                                   class="cursor-pointer border rounded-lg p-3 transition-all select-none">
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="type" value="tva" x-model="form.type" class="sr-only">
                                    <span :class="form.type === 'tva' ? 'bg-blue-600 border-blue-600' : 'border-gray-300'"
                                          class="w-4 h-4 rounded-full border-2 flex-shrink-0 flex items-center justify-center">
                                        <span x-show="form.type === 'tva'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">TVA</p>
                                        <p class="text-xs text-gray-500">Taxe collectée — ajoutée au TTC</p>
                                    </div>
                                </div>
                            </label>
                            <label @click="form.type = 'retenue'"
                                   :class="form.type === 'retenue' ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-300' : 'border-gray-200 hover:border-amber-200'"
                                   class="cursor-pointer border rounded-lg p-3 transition-all select-none">
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="type" value="retenue" x-model="form.type" class="sr-only">
                                    <span :class="form.type === 'retenue' ? 'bg-amber-600 border-amber-600' : 'border-gray-300'"
                                          class="w-4 h-4 rounded-full border-2 flex-shrink-0 flex items-center justify-center">
                                        <span x-show="form.type === 'retenue'" class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-800">Retenue source</p>
                                        <p class="text-xs text-gray-500">BIC, AIB… déduite du net</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Code court <span class="text-red-500">*</span></label>
                        <input type="text" name="short_name" x-model="form.short_name" required maxlength="10"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                               placeholder="TVA18">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Taux (%) <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="number" name="rate" x-model="form.rate" required min="0" max="100" step="0.01"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm focus:ring-2 focus:ring-blue-500"
                                   placeholder="18.00">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                        </div>
                    </div>
                </div>

                {{-- Comptes GL associés (visible uniquement pour les TVA) --}}
                <div x-show="form.type === 'tva'" class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Compte TVA collectée <span class="text-gray-400">(44xx)</span></label>
                        <select name="collected_account_id" x-model="form.collected_account_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Par défaut (4431) —</option>
                            @foreach($tvaAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Compte TVA déductible <span class="text-gray-400">(44xx)</span></label>
                        <select name="deductible_account_id" x-model="form.deductible_account_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-blue-500 bg-white">
                            <option value="">— Par défaut (4455) —</option>
                            @foreach($tvaAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Live simulation --}}
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm" x-show="form.rate !== ''">
                    <p class="text-xs text-gray-500 mb-2">Simulation sur 10 000 FCFA HT</p>
                    <template x-if="form.type === 'tva'">
                        <div class="flex items-center gap-3 font-mono">
                            <span class="text-gray-600">TVA : <strong class="text-gray-900" x-text="Math.round(10000 * form.rate / 100).toLocaleString('fr-FR')"></strong></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600">TTC : <strong class="text-gray-900" x-text="(10000 + Math.round(10000 * form.rate / 100)).toLocaleString('fr-FR')"></strong></span>
                        </div>
                    </template>
                    <template x-if="form.type === 'retenue'">
                        <div class="flex items-center gap-3 font-mono">
                            <span class="text-amber-700">Retenue : <strong x-text="'-' + Math.round(10000 * form.rate / 100).toLocaleString('fr-FR')"></strong></span>
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-600">Net : <strong class="text-gray-900" x-text="(10000 - Math.round(10000 * form.rate / 100)).toLocaleString('fr-FR')"></strong></span>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active"
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">Actif</span>
                    </label>
                    <template x-if="!editId">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_default" value="0">
                            <input type="checkbox" name="is_default" value="1" x-model="form.is_default"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Taux par défaut</span>
                        </label>
                    </template>
                </div>

                <div class="flex gap-3 justify-end pt-2">
                    <button type="button" @click="modal = ''"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">Annuler</button>
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg"
                            x-text="editId ? 'Enregistrer' : 'Créer'"></button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
