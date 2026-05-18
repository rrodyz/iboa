@extends('layouts.erp')
@section('title', 'Lettrage des comptes')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Lettrage</span>
@endsection

@section('content')
<div x-data="lettrageApp()" class="space-y-5">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Lettrage des comptes tiers</h1>
        <div class="text-xs text-gray-400">Comptes de tiers (classe 4)</div>
    </div>

    {{-- Account picker --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sélectionner un compte tiers</label>
                <select name="account_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500">
                    <option value="">Choisir un compte...</option>
                    @foreach($accounts as $account)
                    <option value="{{ $account->id }}" {{ ($selectedAccount?->id) == $account->id ? 'selected' : '' }}>
                        {{ $account->code }} — {{ $account->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                Charger
            </button>
        </div>
    </form>

    @if($selectedAccount)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Unlettered lines --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h2 class="font-semibold text-gray-800">Lignes non lettrées</h2>
                <div class="flex gap-2 items-center flex-wrap">
                    <span class="text-xs text-gray-500" x-text="`${selected.length} sélectionnée(s)`"></span>
                    {{-- [OPTION-C] Lettrage automatique : matching exact débit/crédit --}}
                    <button type="button" @click="autoLettrage()"
                            class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1"
                            title="Lettre automatiquement les couples débit/crédit de même montant">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Auto-lettrer
                    </button>
                    <button type="button" @click="applyLettrage()"
                            :disabled="selected.length < 2"
                            class="text-xs bg-violet-600 hover:bg-violet-700 disabled:opacity-40 text-white font-medium px-3 py-1.5 rounded-lg transition-colors">
                        Lettrer la sélection
                    </button>
                </div>
            </div>

            {{-- Balance indicator --}}
            <div class="px-5 py-2 bg-gray-50 border-b border-gray-100 flex gap-4 text-xs">
                <span>Débit sélectionné : <strong class="text-blue-700 tabular-nums" x-text="fmt(selectedDebit)"></strong></span>
                <span>Crédit sélectionné : <strong class="text-green-700 tabular-nums" x-text="fmt(selectedCredit)"></strong></span>
                <span x-show="selected.length >= 2" :class="isBalanced ? 'text-green-600 font-bold' : 'text-red-600 font-bold'">
                    <span x-text="isBalanced ? '✓ Équilibré' : '✗ Déséquilibré'"></span>
                </span>
            </div>

            <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                @forelse($lines as $line)
                <label class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors has-[:checked]:bg-violet-50">
                    <input type="checkbox" class="mt-0.5 rounded" value="{{ $line->id }}"
                           x-model="selected"
                           @change="toggleLine({{ $line->id }}, {{ $line->debit ?? 0 }}, {{ $line->credit ?? 0 }})">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $line->label }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $line->journalEntry?->entry_date?->format('d/m/Y') }}
                            · {{ $line->journalEntry?->number }}
                            @if($line->due_date)· Éch. {{ $line->due_date->format('d/m/Y') }}@endif
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @if($line->debit > 0)
                        <p class="text-sm font-semibold tabular-nums text-blue-700">D {{ number_format($line->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm font-semibold tabular-nums text-green-700">C {{ number_format($line->credit, 0, ',', ' ') }}</p>
                        @endif
                    </div>
                </label>
                @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">
                    Aucune ligne non lettrée pour ce compte.
                </p>
                @endforelse
            </div>
        </div>

        {{-- Lettered groups --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Lignes lettrées</h2>
            </div>
            <div class="divide-y divide-gray-100 max-h-[560px] overflow-y-auto">
                @forelse($letteredGroups as $ref => $group)
                <div class="px-4 py-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="inline-block bg-violet-100 text-violet-700 text-xs font-bold px-2 py-0.5 rounded font-mono">{{ $ref }}</span>
                        <button type="button" @click="removeLettrage('{{ $ref }}')"
                                class="text-xs text-red-500 hover:text-red-700 font-medium">
                            Délettrer
                        </button>
                    </div>
                    @foreach($group as $line)
                    <div class="flex items-center justify-between py-1 pl-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-700 truncate">{{ $line->label }}</p>
                            <p class="text-xs text-gray-400">{{ $line->journalEntry?->entry_date?->format('d/m/Y') }}</p>
                        </div>
                        <div class="text-right ml-2">
                            @if($line->debit > 0)
                            <p class="text-xs font-semibold tabular-nums text-blue-600">D {{ number_format($line->debit, 0, ',', ' ') }}</p>
                            @else
                            <p class="text-xs font-semibold tabular-nums text-green-600">C {{ number_format($line->credit, 0, ',', ' ') }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">Aucune ligne lettrée.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    {{-- Toast notification --}}
    <div x-show="toast" x-transition
         :class="toastError ? 'bg-red-600' : 'bg-green-600'"
         class="fixed bottom-6 right-6 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg z-50"
         x-text="toast">
    </div>

</div>

@push('scripts')
<script>
function lettrageApp() {
    return {
        selected: [],
        lineData: {},
        toast: '',
        toastError: false,

        toggleLine(id, debit, credit) {
            this.lineData[id] = { debit, credit };
        },
        get selectedDebit() {
            return this.selected.reduce((s, id) => s + (this.lineData[id]?.debit || 0), 0);
        },
        get selectedCredit() {
            return this.selected.reduce((s, id) => s + (this.lineData[id]?.credit || 0), 0);
        },
        get isBalanced() {
            return this.selected.length >= 2 && this.selectedDebit === this.selectedCredit && this.selectedDebit > 0;
        },
        fmt(n) { return new Intl.NumberFormat('fr-FR').format(n); },
        showToast(msg, error = false) {
            this.toast = msg; this.toastError = error;
            setTimeout(() => this.toast = '', 4000);
        },
        async applyLettrage() {
            if (this.selected.length < 2) return;
            if (!this.isBalanced) {
                this.showToast('Le lettrage doit être équilibré.', true); return;
            }
            try {
                const resp = await fetch('{{ route('comptabilite.lettrage.apply') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ line_ids: this.selected.map(Number) }),
                });
                const data = await resp.json();
                if (data.ok) { window.location.reload(); }
                else { this.showToast(data.message, true); }
            } catch (e) { this.showToast('Erreur réseau. Réessayez.', true); }
        },
        async removeLettrage(ref) {
            if (!confirm(`Supprimer le lettrage ${ref} ?`)) return;
            try {
                const resp = await fetch('{{ route('comptabilite.lettrage.remove') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ ref }),
                });
                const data = await resp.json();
                if (data.ok) { window.location.reload(); }
                else { this.showToast(data.message, true); }
            } catch (e) { this.showToast('Erreur réseau. Réessayez.', true); }
        },
        /**
         * [OPTION-C] Lettrage automatique : appelle l'endpoint qui apparie
         * automatiquement les couples débit/crédit de même montant.
         */
        async autoLettrage() {
            const accountId = {{ $selectedAccount?->id ?? 0 }};
            if (!accountId) {
                this.showToast('Sélectionnez d\'abord un compte.', true); return;
            }
            if (!confirm("Lancer le lettrage automatique ?\n\nL'algorithme apparie les couples débit/crédit non lettrés ayant exactement le même montant.\nLes lettrages obtenus pourront être supprimés individuellement si besoin.")) return;

            try {
                const resp = await fetch('{{ route('comptabilite.lettrage.auto-apply') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ account_id: accountId }),
                });
                const data = await resp.json();
                if (data.ok) {
                    this.showToast(data.message);
                    if (data.matched > 0) setTimeout(() => window.location.reload(), 1500);
                } else {
                    this.showToast(data.message || 'Erreur lors du lettrage automatique.', true);
                }
            } catch (e) { this.showToast('Erreur réseau. Réessayez.', true); }
        },
    };
}
</script>
@endpush
@endsection
