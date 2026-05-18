@extends('layouts.erp')
@section('title', 'Rapprochement ' . $rapprochement->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('comptabilite.rapprochement.index') }}" class="hover:text-gray-700">Rapprochement</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $rapprochement->number }}</span>
@endsection

@section('content')
<div x-data="matchingPanel()" class="space-y-5">

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $rapprochement->number }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $rapprochement->cashAccount?->name }} —
                {{ $rapprochement->period_start?->format('d/m/Y') }} au {{ $rapprochement->period_end?->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @php $colors = ['brouillon' => 'bg-gray-100 text-gray-700', 'valide' => 'bg-green-100 text-green-700']; @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $colors[$rapprochement->status] ?? 'bg-gray-100 text-gray-700' }}">
                {{ $rapprochement->statusLabel() }}
            </span>
            @if($rapprochement->isEditable())
            @can('accounting.validate')
            <form method="POST" action="{{ route('comptabilite.rapprochement.validate', $rapprochement) }}"
                  onsubmit="return confirm('Valider ce rapprochement ?')">
                @csrf
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                    ✓ Valider
                </button>
            </form>
            @endcan
            @endif
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Solde ouverture</p>
            <p class="text-lg font-bold tabular-nums text-gray-800">{{ number_format($rapprochement->opening_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Solde clôture (relevé)</p>
            <p class="text-lg font-bold tabular-nums text-gray-800">{{ number_format($rapprochement->closing_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Solde comptable</p>
            <p class="text-lg font-bold tabular-nums text-gray-800">{{ number_format($rapprochement->book_balance, 0, ',', ' ') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">Écart</p>
            <p class="text-lg font-bold tabular-nums {{ $rapprochement->difference == 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $rapprochement->difference == 0 ? '✓ Équilibré' : number_format($rapprochement->difference, 0, ',', ' ') }}
            </p>
        </div>
    </div>

    {{-- Bank lines + matching --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Left: bank statement lines --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Lignes du relevé bancaire</h2>
            </div>
            <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                @forelse($rapprochement->lines as $line)
                <div class="px-4 py-3 flex items-start justify-between gap-2 hover:bg-gray-50 transition-colors"
                     :class="selectedBankLine == {{ $line->id }} ? 'bg-violet-50 ring-1 ring-violet-300 ring-inset' : ''">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            @if($line->is_matched)
                            <span class="inline-block w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span>
                            @else
                            <span class="inline-block w-2 h-2 rounded-full bg-gray-300 flex-shrink-0"></span>
                            @endif
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $line->label }}</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 pl-4">
                            {{ $line->value_date?->format('d/m/Y') }}
                            @if($line->reference) · {{ $line->reference }}@endif
                            @if($line->is_matched && $line->journalEntryLine)
                            <span class="text-green-600 font-medium">→ {{ $line->journalEntryLine->journalEntry?->number ?? '' }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @if($line->debit > 0)
                        <p class="text-sm font-semibold tabular-nums text-red-600">-{{ number_format($line->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm font-semibold tabular-nums text-green-600">+{{ number_format($line->credit, 0, ',', ' ') }}</p>
                        @endif
                        @if($rapprochement->isEditable())
                        <div class="flex gap-1 mt-1 justify-end">
                            @if(!$line->is_matched)
                            <button type="button" @click="selectBankLine({{ $line->id }})"
                                    class="text-xs text-violet-600 hover:text-violet-800 font-medium">Associer</button>
                            @else
                            <button type="button" @click="unmatch({{ $line->id }})"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium">Dissocier</button>
                            @endif
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">Aucune ligne.</p>
                @endforelse
            </div>
        </div>

        {{-- Right: unmatched journal lines --}}
        @if($rapprochement->isEditable())
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-800">Écritures comptables non rapprochées</h2>
                <p class="text-xs text-gray-500 mt-0.5">Sélectionnez une ligne relevé puis cliquez sur une écriture pour l'associer.</p>
            </div>
            <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                @forelse($unmatchedJournalLines as $jl)
                <div class="px-4 py-3 flex items-start justify-between gap-2 cursor-pointer hover:bg-violet-50 transition-colors"
                     @click="matchLine({{ $jl->id }})">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $jl->label }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $jl->journalEntry?->entry_date?->format('d/m/Y') }}
                            · {{ $jl->journalEntry?->number }}
                            · {{ $jl->account?->code }}
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        @if($jl->debit > 0)
                        <p class="text-sm font-semibold tabular-nums text-blue-700">D {{ number_format($jl->debit, 0, ',', ' ') }}</p>
                        @else
                        <p class="text-sm font-semibold tabular-nums text-purple-700">C {{ number_format($jl->credit, 0, ',', ' ') }}</p>
                        @endif
                    </div>
                </div>
                @empty
                <p class="px-4 py-8 text-center text-sm text-gray-400">Toutes les écritures sont rapprochées.</p>
                @endforelse
            </div>
        </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
function matchingPanel() {
    return {
        selectedBankLine: null,
        selectBankLine(id) {
            this.selectedBankLine = this.selectedBankLine === id ? null : id;
        },
        async matchLine(journalLineId) {
            if (!this.selectedBankLine) {
                alert('Sélectionnez d\'abord une ligne du relevé bancaire.');
                return;
            }
            try {
                const resp = await fetch(`/comptabilite/rapprochement/lines/${this.selectedBankLine}/match`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ journal_entry_line_id: journalLineId }),
                });
                const data = await resp.json();
                if (data.ok) { window.location.reload(); }
                else { alert(data.message); }
            } catch (e) { alert('Erreur réseau. Réessayez.'); }
        },
        async unmatch(bankLineId) {
            if (!confirm('Supprimer ce rapprochement ?')) return;
            try {
                const resp = await fetch(`/comptabilite/rapprochement/lines/${bankLineId}/unmatch`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const data = await resp.json();
                if (data.ok) { window.location.reload(); }
                else { alert(data.message); }
            } catch (e) { alert('Erreur réseau. Réessayez.'); }
        },
    };
}
</script>
@endpush
@endsection
