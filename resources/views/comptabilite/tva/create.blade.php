@extends('layouts.erp')
@section('title', 'Nouvelle déclaration TVA')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.tva.index') }}" class="hover:text-gray-700">Déclarations TVA</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<div x-data="tvaForm()" class="space-y-6 max-w-4xl">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Nouvelle déclaration TVA</h1>
        <a href="{{ route('comptabilite.tva.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Retour</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Period calculator --}}
    <div class="bg-violet-50 border border-violet-200 rounded-xl p-5 space-y-4">
        <h2 class="text-sm font-semibold text-violet-800 uppercase tracking-wide">Calculer automatiquement depuis les écritures</h2>
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-violet-700 mb-1">Du</label>
                <input type="date" x-model="calcFrom"
                       class="border border-violet-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-violet-700 mb-1">Au</label>
                <input type="date" x-model="calcTo"
                       class="border border-violet-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 bg-white">
            </div>
            <button type="button" @click="calculate()"
                    :disabled="loading || !calcFrom || !calcTo"
                    class="bg-violet-600 hover:bg-violet-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <span x-show="!loading">Calculer</span>
                <span x-show="loading">Calcul...</span>
            </button>
        </div>
        <template x-if="result">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
            <div class="bg-white rounded-lg p-3 text-center border border-violet-100">
                <p class="text-xs text-gray-500">TVA Collectée</p>
                <p class="text-base font-bold text-gray-800 tabular-nums" x-text="fmt(result.tvaCollectee)"></p>
            </div>
            <div class="bg-white rounded-lg p-3 text-center border border-violet-100">
                <p class="text-xs text-gray-500">TVA Déductible</p>
                <p class="text-base font-bold text-gray-800 tabular-nums" x-text="fmt(result.tvaDeductible)"></p>
            </div>
            <div class="bg-white rounded-lg p-3 text-center border border-green-100">
                <p class="text-xs text-gray-500">TVA Due</p>
                <p class="text-base font-bold tabular-nums" :class="result.tvaDue > 0 ? 'text-red-600' : 'text-gray-400'" x-text="fmt(result.tvaDue)"></p>
            </div>
            <div class="bg-white rounded-lg p-3 text-center border border-blue-100">
                <p class="text-xs text-gray-500">Crédit TVA</p>
                <p class="text-base font-bold tabular-nums" :class="result.creditTva > 0 ? 'text-blue-700' : 'text-gray-400'" x-text="fmt(result.creditTva)"></p>
            </div>
        </div>
        </template>
    </div>

    <form method="POST" action="{{ route('comptabilite.tva.store') }}" class="space-y-5">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Déclaration</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Libellé période <span class="text-red-500">*</span></label>
                    <input type="text" name="period_label" value="{{ old('period_label') }}" required placeholder="ex : Janvier 2026"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                    <select name="period_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                        <option value="mensuel"     {{ old('period_type') === 'mensuel'     ? 'selected' : '' }}>Mensuel</option>
                        <option value="trimestriel" {{ old('period_type') === 'trimestriel' ? 'selected' : '' }}>Trimestriel</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date de déclaration <span class="text-red-500">*</span></label>
                    <input type="date" name="declaration_date" value="{{ old('declaration_date', date('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période du <span class="text-red-500">*</span></label>
                    <input type="date" name="period_start" :value="calcFrom || '{{ old('period_start') }}'" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période au <span class="text-red-500">*</span></label>
                    <input type="date" name="period_end" :value="calcTo || '{{ old('period_end') }}'" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date limite</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
        </div>

        {{-- Montants --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Montants TVA (FCFA)</h2>
            <p class="text-xs text-gray-500">Laissez vide pour calculer automatiquement depuis les écritures comptables de la période.</p>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TVA Collectée</label>
                    <input type="number" name="tva_collectee" :value="result ? result.tvaCollectee : '{{ old('tva_collectee') }}'" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TVA Déductible</label>
                    <input type="number" name="tva_deductible" :value="result ? result.tvaDeductible : '{{ old('tva_deductible') }}'" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TVA Due</label>
                    <input type="number" name="tva_due" :value="result ? result.tvaDue : '{{ old('tva_due') }}'" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Crédit TVA</label>
                    <input type="number" name="credit_tva" :value="result ? result.creditTva : '{{ old('credit_tva') }}'" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="3" maxlength="2000"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 resize-none">{{ old('notes') }}</textarea>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('comptabilite.tva.index') }}"
               class="border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium px-5 py-2.5 rounded-lg">Annuler</a>
            <button type="submit"
                    class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
                Créer la déclaration
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function tvaForm() {
    return {
        calcFrom: '{{ $dateFrom ?? '' }}',
        calcTo:   '{{ $dateTo   ?? '' }}',
        result: @json($calc),
        loading: false,
        async calculate() {
            if (!this.calcFrom || !this.calcTo) return;
            this.loading = true;
            try {
                const resp = await fetch('{{ route('comptabilite.tva.calculate') }}?' + new URLSearchParams({ date_from: this.calcFrom, date_to: this.calcTo }));
                const data = await resp.json();
                this.result = data.calc;
            } catch(e) { alert('Erreur de calcul.'); }
            this.loading = false;
        },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n ?? 0); },
    };
}
</script>
@endpush
@endsection
