@extends('layouts.erp')
@section('title', 'Paramétrage de la paie')
@section('breadcrumb')
    <a href="{{ route('rh.dashboard') }}" class="hover:text-gray-700">RH</a>
    <span class="mx-1">/</span><span>Paramétrage de la paie</span>
@endsection

@section('content')
<div x-data="parametragePayroll()" class="max-w-5xl mx-auto">

    {{-- En-tête --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Paramétrage de la paie</h1>
            <p class="text-sm text-gray-500 mt-1">Taux CNSS, barème IUTS, jours ouvrés, heures supplémentaires.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
            {{ $company->name }}
        </span>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('rh.parametrage.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ── CNSS ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-blue-50 border-b border-blue-100 px-5 py-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span class="font-semibold text-blue-800">Caisse Nationale de Sécurité Sociale (CNSS)</span>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Taux employé (%)</label>
                    <input type="number" name="cnss_employee_rate" step="0.01" min="0" max="100"
                           value="{{ old('cnss_employee_rate', $setting->cnss_employee_rate) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('cnss_employee_rate') border-red-400 @enderror">
                    @error('cnss_employee_rate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Taux patronal (%)</label>
                    <input type="number" name="cnss_employer_rate" step="0.01" min="0" max="100"
                           value="{{ old('cnss_employer_rate', $setting->cnss_employer_rate) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('cnss_employer_rate') border-red-400 @enderror">
                    @error('cnss_employer_rate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Plafond mensuel (FCFA)</label>
                    <input type="number" name="cnss_ceiling" step="1000" min="0"
                           value="{{ old('cnss_ceiling', $setting->cnss_ceiling) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('cnss_ceiling') border-red-400 @enderror">
                    @error('cnss_ceiling')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Taux AT/MP (%)</label>
                    <input type="number" name="cnss_at_rate" step="0.01" min="0" max="100"
                           value="{{ old('cnss_at_rate', $setting->cnss_at_rate) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm @error('cnss_at_rate') border-red-400 @enderror">
                    @error('cnss_at_rate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- ── Temps de travail ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-amber-50 border-b border-amber-100 px-5 py-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="font-semibold text-amber-800">Temps de travail &amp; Heures supplémentaires</span>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Jours ouvrés / mois</label>
                    <input type="number" name="work_days_month" min="1" max="31"
                           value="{{ old('work_days_month', $setting->work_days_month) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Heures / jour</label>
                    <input type="number" name="work_hours_day" min="1" max="24"
                           value="{{ old('work_hours_day', $setting->work_hours_day) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Majoration HS 25 (%)</label>
                    <input type="number" name="hs_rate_25" step="0.5" min="0"
                           value="{{ old('hs_rate_25', $setting->hs_rate_25) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Majoration HS 50 (%)</label>
                    <input type="number" name="hs_rate_50" step="0.5" min="0"
                           value="{{ old('hs_rate_50', $setting->hs_rate_50) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Majoration HS nuit (%)</label>
                    <input type="number" name="hs_rate_nuit" step="0.5" min="0"
                           value="{{ old('hs_rate_nuit', $setting->hs_rate_nuit) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        {{-- ── Quotient familial IUTS ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-purple-50 border-b border-purple-100 px-5 py-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="font-semibold text-purple-800">Quotient familial (IUTS)</span>
            </div>
            <div class="p-5 grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nb parts max</label>
                    <input type="number" name="nb_parts_max" min="1" max="20"
                           value="{{ old('nb_parts_max', $setting->nb_parts_max) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Parts par enfant à charge</label>
                    <input type="number" name="parts_per_child" step="0.25" min="0" max="5"
                           value="{{ old('parts_per_child', $setting->parts_per_child) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        {{-- ── Barème IUTS ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-indigo-50 border-b border-indigo-100 px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="font-semibold text-indigo-800">Barème IUTS (par part — mensuel)</span>
                </div>
                <button type="button" @click="addBracket()"
                        class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                    + Ajouter une tranche
                </button>
            </div>
            <div class="p-5">
                <p class="text-xs text-gray-500 mb-4">
                    Le barème s'applique au revenu mensuel divisé par le nombre de parts.
                    La dernière tranche couvre tout revenu supérieur (plafond automatique).
                    Les tranches sont triées par plafond croissant à la sauvegarde.
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-12">#</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Plafond (FCFA/part)</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Taux (%)</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-16">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(bracket, index) in brackets" :key="index">
                                <tr class="border-t border-gray-100">
                                    <td class="px-4 py-2 text-gray-400 text-xs" x-text="index + 1"></td>
                                    <td class="px-4 py-2">
                                        <input type="number"
                                               :name="`brackets[${index}][limit]`"
                                               x-model="bracket.limit"
                                               :disabled="index === brackets.length - 1"
                                               min="1"
                                               class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm disabled:bg-gray-100 disabled:text-gray-400">
                                        <span x-show="index === brackets.length - 1"
                                              class="text-xs text-indigo-500 mt-0.5 block">∞ (dernière tranche)</span>
                                        <input type="hidden"
                                               :name="`brackets[${index}][limit]`"
                                               x-show="index === brackets.length - 1"
                                               value="9999999999">
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="relative">
                                            <input type="number"
                                                   :name="`brackets[${index}][rate]`"
                                                   x-model="bracket.rate"
                                                   step="0.5" min="0" max="100"
                                                   class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm pr-8">
                                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <button type="button" @click="removeBracket(index)"
                                                x-show="brackets.length > 1"
                                                class="text-red-500 hover:text-red-700 p-1 rounded">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                @error('brackets')
                    <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ── Divers ── --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-5 py-3">
                <span class="font-semibold text-gray-700">Paramètres généraux</span>
            </div>
            <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Préfixe bulletin</label>
                    <input type="text" name="bulletin_prefix" maxlength="10"
                           value="{{ old('bulletin_prefix', $setting->bulletin_prefix) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Devise</label>
                    <input type="text" name="currency_code" maxlength="10"
                           value="{{ old('currency_code', $setting->currency_code) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Code pays</label>
                    <input type="text" name="country_code" maxlength="5"
                           value="{{ old('country_code', $setting->country_code) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes internes</label>
                    <input type="text" name="notes" maxlength="500"
                           value="{{ old('notes', $setting->notes) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        {{-- Boutons --}}
        <div class="flex items-center justify-end gap-3 pb-4">
            <a href="{{ route('rh.dashboard') }}"
               class="px-5 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                Annuler
            </a>
            <button type="submit"
                    class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 shadow-sm">
                Enregistrer les paramètres
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function parametragePayroll() {
    return {
        brackets: @json(old('brackets') ? collect(old('brackets'))->map(fn($b) => ['limit' => $b['limit'], 'rate' => $b['rate']]) : collect($brackets)->map(fn($b) => ['limit' => $b[0], 'rate' => $b[1]])),

        addBracket() {
            // Insère une nouvelle tranche avant la dernière (∞)
            const lastIdx = this.brackets.length - 1;
            const prevLimit = lastIdx > 0 ? this.brackets[lastIdx - 1].limit : 0;
            this.brackets.splice(lastIdx, 0, {
                limit: parseInt(prevLimit) + 10000,
                rate: 0
            });
        },

        removeBracket(index) {
            if (this.brackets.length <= 1) return;
            this.brackets.splice(index, 1);
        }
    }
}
</script>
@endpush
@endsection
