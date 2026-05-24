@extends('layouts.erp')
@section('title', 'Paie – ' . $run->period_label)

@section('breadcrumb')
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">Paie</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $run->period_label }}</span>
@endsection

@section('content')
<div x-data="payrollShow()" x-init="loadVariables()">

{{-- ── En-tête ──────────────────────────────────────────────────────────────── --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Bulletin — {{ $run->period_label }}</h1>
        @php $c = $run->status_color @endphp
        <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-700">
            {{ $run->status_label }}
        </span>
        @if($run->validatedBy)
        <span class="ml-2 text-xs text-gray-400">Validé par {{ $run->validatedBy->name }} le {{ $run->validated_at->format('d/m/Y à H:i') }}</span>
        @endif
        @if($run->paid_at)
        <span class="ml-2 text-xs text-emerald-600 font-medium">Payé le {{ $run->paid_at->format('d/m/Y') }}</span>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 justify-end">
        {{-- Calculer --}}
        @if($run->isEditable())
        <form method="POST" action="{{ route('rh.paie.calculate', $run) }}">
            @csrf
            <button class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                Calculer la paie
            </button>
        </form>
        @endif

        {{-- Valider --}}
        @if($run->status === 'calcule')
        <form method="POST" action="{{ route('rh.paie.validate', $run) }}"
              onsubmit="return confirm('Valider ce bulletin ? Cette action est irréversible.')">
            @csrf
            <button class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Valider le bulletin
            </button>
        </form>
        @endif

        {{-- Marquer payé --}}
        @if($run->status === 'valide')
        <button @click="$refs.modalPaid.classList.remove('hidden')"
                class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Marquer payé
        </button>
        @endif

        {{-- Exports --}}
        @if($run->status !== 'brouillon')
        <div x-data="{open:false}" class="relative">
            <button @click="open=!open" class="inline-flex items-center gap-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exports <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.away="open=false"
                 class="absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1">
                <a href="{{ route('rh.paie.recap-pdf', $run) }}" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">📄 Récap mensuel PDF</a>
                <a href="{{ route('rh.paie.cnss-pdf', $run) }}" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">📋 Bordereau CNSS PDF</a>
                <a href="{{ route('rh.paie.iuts-pdf', $run) }}" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">📋 État IUTS PDF</a>
                <hr class="my-1 border-gray-100">
                <a href="{{ route('rh.paie.livre-paie-xlsx', $run) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-emerald-700 hover:bg-emerald-50">📊 Livre de paie Excel</a>
                <a href="{{ route('rh.paie.cnss-xlsx', $run) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-emerald-700 hover:bg-emerald-50">📊 CNSS Excel</a>
                <a href="{{ route('rh.paie.iuts-xlsx', $run) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-emerald-700 hover:bg-emerald-50">📊 IUTS Excel</a>
                <hr class="my-1 border-gray-100">
                <a href="{{ route('rh.paie.virement-csv', $run) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">📋 Ordre de virement CSV</a>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ── KPIs ─────────────────────────────────────────────────────────────────── --}}
@if($run->total_brut > 0)
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3 mb-6">
    @foreach([
        ['Effectif',        $run->employee_count.' emp.',                                          'indigo'],
        ['Total brut',      number_format($run->total_brut,0,',',' ').' F',                        'gray'],
        ['CNSS salarial',   number_format($run->total_cnss_employee,0,',',' ').' F',               'red'],
        ['CNSS patronal',   number_format($run->total_cnss_employer,0,',',' ').' F',               'amber'],
        ['IUTS',            number_format($run->total_iuts,0,',',' ').' F',                        'purple'],
        ['Net à payer',     number_format($run->total_net,0,',',' ').' F',                         'emerald'],
    ] as [$l,$v,$col])
    <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
        <div class="text-xs text-gray-500">{{ $l }}</div>
        <div class="font-mono font-bold text-{{ $col }}-700 text-sm mt-1">{{ $v }}</div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Variables mensuelles (saisie) ──────────────────────────────────────── --}}
@if($run->isEditable())
<div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-amber-800">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Variables mensuelles — HS / Absences / Primes ponctuelles / Avances
        </h2>
        <button @click="showVarForm=!showVarForm"
                class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-medium hover:bg-amber-700">
            + Ajouter une variable
        </button>
    </div>

    {{-- Formulaire d'ajout --}}
    <div x-show="showVarForm" x-cloak class="bg-white rounded-lg border border-amber-200 p-4 mb-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Employé *</label>
                <select x-model="newVar.employee_id" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="">— Choisir —</option>
                    @foreach($run->items->sortBy('employee_name') as $item)
                    <option value="{{ $item->employee_id }}">{{ $item->employee_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                <select x-model="newVar.type" @change="onTypeChange()" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    @foreach(\App\Models\PayrollVariable::TYPES as $key=>$meta)
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Libellé</label>
                <input type="text" x-model="newVar.label" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Unité</label>
                <select x-model="newVar.unit" class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="heures">Heures</option>
                    <option value="jours">Jours</option>
                    <option value="forfait">Forfait</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Quantité</label>
                <input type="number" x-model="newVar.qty" min="0" step="0.5"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Montant (FCFA) *</label>
                <input type="number" x-model="newVar.amount" min="0" step="100"
                       class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-right font-mono">
            </div>
            <div class="flex items-end gap-3">
                <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                    <input type="checkbox" x-model="newVar.is_taxable" class="rounded"> Imposable
                </label>
                <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                    <input type="checkbox" x-model="newVar.is_social_charged" class="rounded"> CNSS
                </label>
            </div>
            <div class="flex items-end justify-end gap-2">
                <button @click="saveVariable()" :disabled="saving"
                        class="px-4 py-1.5 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700 disabled:opacity-50">
                    <span x-text="saving ? 'Enregistrement…' : 'Ajouter'"></span>
                </button>
                <button @click="showVarForm=false" class="px-3 py-1.5 border border-gray-300 text-gray-600 rounded-lg text-sm">Annuler</button>
            </div>
        </div>
    </div>

    {{-- Liste des variables --}}
    <div x-show="variables.length > 0">
        <table class="min-w-full text-xs bg-white rounded-lg overflow-hidden">
            <thead class="bg-gray-100 text-gray-500 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Employé</th>
                    <th class="px-3 py-2 text-left">Libellé</th>
                    <th class="px-3 py-2 text-center">Unité</th>
                    <th class="px-3 py-2 text-right">Qté</th>
                    <th class="px-3 py-2 text-right">Montant</th>
                    <th class="px-3 py-2 text-center">Gain/Ret.</th>
                    <th class="px-3 py-2 text-center">Imp.</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="v in variables" :key="v.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium" x-text="v.employee?.employee_name || empName(v.employee_id)"></td>
                        <td class="px-3 py-2 text-gray-600" x-text="v.label"></td>
                        <td class="px-3 py-2 text-center text-gray-400" x-text="v.unit"></td>
                        <td class="px-3 py-2 text-right font-mono" x-text="v.qty > 0 ? v.qty : '—'"></td>
                        <td class="px-3 py-2 text-right font-mono font-semibold"
                            :class="v.is_gain ? 'text-green-700' : 'text-red-600'"
                            x-text="(v.is_gain ? '+' : '-') + new Intl.NumberFormat('fr-FR').format(v.amount) + ' F'"></td>
                        <td class="px-3 py-2 text-center" x-text="v.is_gain ? 'Gain' : 'Retenue'"></td>
                        <td class="px-3 py-2 text-center" x-text="v.is_taxable ? '✓' : '—'"></td>
                        <td class="px-3 py-2 text-right">
                            <button @click="deleteVariable(v.id)" class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div x-show="variables.length === 0" class="text-center text-sm text-amber-700/60 py-3">
        Aucune variable saisie. Les HS, absences et avances seront prises en compte au calcul.
    </div>
</div>
@endif

{{-- ── Tableau des bulletins individuels ──────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-200 overflow-x-auto">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Bulletins individuels — {{ $run->employee_count }} employé(s)</h2>
        @if($run->status !== 'brouillon')
        <span class="text-xs text-gray-400">Coût total employeur : <strong class="text-gray-700">{{ number_format($run->total_brut + $run->total_cnss_employer, 0, ',', ' ') }} F</strong></span>
        @endif
    </div>
    <table class="min-w-full divide-y divide-gray-200 text-xs">
        <thead class="bg-gray-50 text-gray-500 uppercase">
            <tr>
                <th class="px-3 py-3 text-left">Mat.</th>
                <th class="px-3 py-3 text-left">Employé / Poste</th>
                <th class="px-3 py-3 text-right">Base</th>
                <th class="px-3 py-3 text-right">HS</th>
                <th class="px-3 py-3 text-right">Primes</th>
                <th class="px-3 py-3 text-right">Absences</th>
                <th class="px-3 py-3 text-right font-semibold">Brut</th>
                <th class="px-3 py-3 text-right">CNSS (e)</th>
                <th class="px-3 py-3 text-right">CNSS (p)</th>
                <th class="px-3 py-3 text-right">IUTS</th>
                <th class="px-3 py-3 text-right">Avances</th>
                <th class="px-3 py-3 text-right font-bold text-emerald-700">Net</th>
                <th class="px-3 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        @forelse($run->items as $item)
        <tr class="hover:bg-gray-50">
            <td class="px-3 py-2 font-mono text-gray-400">{{ $item->employee_matricule }}</td>
            <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ $item->employee_name }}</div>
                <div class="text-gray-400">{{ $item->department_name }} · {{ $item->job_title }}</div>
                <div class="text-gray-300">{{ $item->worked_days }}/{{ $item->total_days }} j · {{ $item->nb_parts }} part(s)</div>
            </td>
            <td class="px-3 py-2 text-right font-mono">{{ number_format($item->base_salary, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-blue-600">
                @if($item->hs_25_amount + $item->hs_50_amount + $item->hs_nuit_amount > 0)
                {{ number_format($item->hs_25_amount + $item->hs_50_amount + $item->hs_nuit_amount, 0, ',', ' ') }}
                @else —
                @endif
            </td>
            <td class="px-3 py-2 text-right font-mono text-indigo-600">
                {{ $item->total_allowances_taxable + $item->primes_exceptionnelles > 0
                    ? number_format($item->total_allowances_taxable + $item->primes_exceptionnelles, 0, ',', ' ')
                    : '—' }}
            </td>
            <td class="px-3 py-2 text-right font-mono text-orange-600">
                @if($item->absence_amount > 0)
                -{{ number_format($item->absence_amount, 0, ',', ' ') }}
                <div class="text-gray-300">{{ $item->absence_days }}j</div>
                @else —
                @endif
            </td>
            <td class="px-3 py-2 text-right font-mono font-semibold text-gray-900">{{ number_format($item->salaire_brut, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-red-600">{{ number_format($item->cnss_employee, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-amber-600">{{ number_format($item->cnss_employer, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-purple-600">{{ number_format($item->iuts_amount, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right font-mono text-red-500">
                {{ $item->avances_deductions > 0 ? '-'.number_format($item->avances_deductions, 0, ',', ' ') : '—' }}
            </td>
            <td class="px-3 py-2 text-right font-mono font-bold text-emerald-700">{{ number_format($item->salaire_net, 0, ',', ' ') }}</td>
            <td class="px-3 py-2 text-right">
                @if($run->status !== 'brouillon')
                <a href="{{ route('rh.paie.bulletin-pdf', [$run, $item]) }}" target="_blank"
                   class="text-blue-500 hover:text-blue-700" title="Bulletin PDF individuel">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </a>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="13" class="px-4 py-10 text-center text-gray-400">
            @if($run->status === 'brouillon')
                Saisissez les variables mensuelles si nécessaire, puis cliquez sur « Calculer la paie ».
            @else Aucun employé traité.
            @endif
        </td></tr>
        @endforelse
        </tbody>
        @if($run->items->count() > 0)
        <tfoot class="bg-gray-50 font-semibold text-xs border-t-2 border-gray-300">
            <tr>
                <td colspan="6" class="px-3 py-2 text-right text-gray-500 uppercase text-xs">Totaux</td>
                <td class="px-3 py-2 text-right font-mono">{{ number_format($run->total_brut, 0, ',', ' ') }}</td>
                <td class="px-3 py-2 text-right font-mono text-red-600">{{ number_format($run->total_cnss_employee, 0, ',', ' ') }}</td>
                <td class="px-3 py-2 text-right font-mono text-amber-600">{{ number_format($run->total_cnss_employer, 0, ',', ' ') }}</td>
                <td class="px-3 py-2 text-right font-mono text-purple-600">{{ number_format($run->total_iuts, 0, ',', ' ') }}</td>
                <td></td>
                <td class="px-3 py-2 text-right font-mono text-emerald-700">{{ number_format($run->total_net, 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

{{-- Modal paiement --}}
<div x-ref="modalPaid" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-80 p-6">
        <h3 class="text-base font-semibold mb-4">Confirmer le paiement</h3>
        <form method="POST" action="{{ route('rh.paie.mark-paid', $run) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de paiement</label>
                <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <p class="text-xs text-gray-500 mb-4">
                Net total à payer : <strong>{{ number_format($run->total_net, 0, ',', ' ') }} FCFA</strong>
                pour {{ $run->employee_count }} employé(s).
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" @click="$refs.modalPaid.classList.add('hidden')"
                        class="px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Annuler</button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">Confirmer</button>
            </div>
        </form>
    </div>
</div>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function payrollShow() {
    return {
        variables: [],
        showVarForm: false,
        saving: false,
        newVar: {
            employee_id: '', type: 'hs_25', label: '', qty: 0,
            unit: 'heures', amount: 0, is_gain: true, is_taxable: true, is_social_charged: true,
        },

        loadVariables() {
            fetch('{{ route('rh.paie.variables', $run) }}')
                .then(r => r.json()).then(data => { this.variables = data; });
        },

        onTypeChange() {
            const typesMeta = @json(\App\Models\PayrollVariable::TYPES);
            const meta = typesMeta[this.newVar.type];
            if (meta) {
                this.newVar.label = meta.label;
                this.newVar.unit  = meta.unit;
                this.newVar.is_gain = meta.gain;
                this.newVar.is_taxable = meta.taxable;
                this.newVar.is_social_charged = meta.taxable;
            }
        },

        saveVariable() {
            if (!this.newVar.employee_id) { alert('Choisissez un employé.'); return; }
            if (!this.newVar.amount)      { alert('Saisissez un montant.'); return; }
            this.saving = true;
            fetch('{{ route('rh.paie.variables.store', $run) }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(this.newVar),
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                this.variables.push(data.variable);
                this.newVar = { employee_id:'', type:'hs_25', label:'', qty:0, unit:'heures', amount:0, is_gain:true, is_taxable:true, is_social_charged:true };
                this.showVarForm = false;
            })
            .finally(() => this.saving = false);
        },

        deleteVariable(id) {
            if (!confirm('Supprimer cette variable ?')) return;
            fetch(`{{ url('rh/paie/'.$run->id.'/variables') }}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            }).then(() => {
                this.variables = this.variables.filter(v => v.id !== id);
            });
        },

        empName(id) {
            const item = @json($run->items->keyBy('employee_id'));
            return item[id]?.employee_name ?? 'Employé #'+id;
        },
    };
}
</script>
@endpush
@endsection
