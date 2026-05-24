@extends('layouts.erp')
@section('title', $employe->full_name)

@section('breadcrumb')
    <a href="{{ route('rh.employes.index') }}" class="hover:text-gray-700">Employés</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $employe->full_name }}</span>
@endsection

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ $employe->full_name }}</h1>
        <p class="text-sm text-gray-500 mt-1">Matricule <span class="font-mono">{{ $employe->matricule }}</span> · {{ $employe->category_label }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('rh.employes.edit', $employe) }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            Modifier
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

{{-- Colonne gauche : infos --}}
<div class="lg:col-span-2 space-y-5">

    {{-- Identité --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">Identité</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-gray-400">Sexe</dt><dd>{{ $employe->gender === 'M' ? 'Masculin' : 'Féminin' }}</dd></div>
            <div><dt class="text-gray-400">Date de naissance</dt><dd>{{ $employe->birth_date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Nationalité</dt><dd>{{ $employe->nationality ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">N° CIN</dt><dd class="font-mono">{{ $employe->cin_number ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">N° CNSS</dt><dd class="font-mono">{{ $employe->cnss_number ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Téléphone</dt><dd>{{ $employe->phone ?? '—' }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-400">Email</dt><dd>{{ $employe->email ?? '—' }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-400">Adresse</dt><dd>{{ $employe->address ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Poste --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-4">Poste & Emploi</h2>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-gray-400">Département</dt><dd>{{ $employe->department?->name ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Intitulé poste</dt><dd>{{ $employe->job_title ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Catégorie</dt><dd>{{ $employe->category_label }}</dd></div>
            <div><dt class="text-gray-400">Date d'embauche</dt><dd>{{ $employe->hiring_date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Situation familiale</dt>
                <dd>{{ ['celibataire'=>'Célibataire','marie'=>'Marié(e)','veuf'=>'Veuf/Veuve','divorce'=>'Divorcé(e)'][$employe->family_status] }}</dd>
            </div>
            <div><dt class="text-gray-400">Enfants à charge</dt><dd>{{ $employe->nb_children }}</dd></div>
            <div><dt class="text-gray-400">Parts fiscales IUTS</dt><dd class="font-semibold">{{ number_format($employe->nb_parts, 1) }} parts</dd></div>
            <div><dt class="text-gray-400">Banque / Compte</dt>
                <dd class="font-mono text-xs">{{ $employe->bank_name ? $employe->bank_name . ' — ' . $employe->bank_account : '—' }}</dd>
            </div>
        </dl>
    </div>

    {{-- Contrats --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Contrats</h2>
            <button onclick="document.getElementById('modal-contract').classList.remove('hidden')"
                    class="text-xs text-blue-600 hover:underline">+ Nouveau contrat</button>
        </div>
        <div class="space-y-3">
            @forelse($employe->contracts as $contract)
            <div class="flex items-center justify-between p-3 rounded-lg border {{ $contract->status === 'actif' ? 'border-green-200 bg-green-50' : 'border-gray-100 bg-gray-50' }}">
                <div>
                    <span class="font-medium text-sm">{{ $contract->type }}</span>
                    <span class="text-xs text-gray-500 ml-2">Du {{ $contract->start_date->format('d/m/Y') }}{{ $contract->end_date ? ' au ' . $contract->end_date->format('d/m/Y') : '' }}</span>
                </div>
                <div class="text-right">
                    <div class="font-mono font-semibold text-sm">{{ number_format($contract->base_salary, 0, ',', ' ') }} FCFA</div>
                    <span class="text-xs {{ $contract->status === 'actif' ? 'text-green-600' : 'text-gray-400' }}">{{ ucfirst($contract->status) }}</span>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400">Aucun contrat.</p>
            @endforelse
        </div>
    </div>

    {{-- Primes / Indemnités --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Primes & Indemnités</h2>
            <button onclick="document.getElementById('modal-allowance').classList.remove('hidden')"
                    class="text-xs text-blue-600 hover:underline">+ Ajouter</button>
        </div>
        @if($employe->allowances->isNotEmpty())
        <table class="min-w-full text-sm">
            <thead><tr class="text-xs text-gray-400 border-b">
                <th class="pb-2 text-left">Type</th>
                <th class="pb-2 text-center">Imposable</th>
                <th class="pb-2 text-right">Montant</th>
                <th class="pb-2"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
            @foreach($employe->allowances as $allowance)
            <tr>
                <td class="py-2 font-medium">{{ $allowance->type?->name }}</td>
                <td class="py-2 text-center text-xs">{{ $allowance->type?->is_taxable ? '✓ IUTS' : '— Exo.' }}</td>
                <td class="py-2 text-right font-mono">{{ number_format($allowance->amount, 0, ',', ' ') }} F</td>
                <td class="py-2 text-right">
                    <form method="POST" action="{{ route('rh.employes.allowances.destroy', [$employe, $allowance]) }}"
                          onsubmit="return confirm('Supprimer cette prime ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">✕</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-gray-400">Aucune prime enregistrée.</p>
        @endif
    </div>

</div>

{{-- Colonne droite : statut + simulation --}}
<div class="space-y-5">
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-3">Statut</h2>
        @php $color = $employe->status_color @endphp
        <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-{{ $color }}-100 text-{{ $color }}-700">
            {{ $employe->status_label }}
        </span>
        @if($employe->activeContract)
        <div class="mt-4 pt-4 border-t border-gray-100 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Salaire de base</span>
                <span class="font-mono font-semibold">{{ number_format($employe->activeContract->base_salary, 0, ',', ' ') }} F</span>
            </div>
            @php
                $base = $employe->activeContract->base_salary;
                $cnssEmp = round($base * 5.5 / 100);
                $cnssEmp = min($cnssEmp, round(650000 * 5.5 / 100));
                $imposable = $base - $cnssEmp;
                $parts = $employe->nb_parts;
            @endphp
            <div class="flex justify-between text-red-600">
                <span>CNSS (5,5%)</span>
                <span class="font-mono">- {{ number_format($cnssEmp, 0, ',', ' ') }} F</span>
            </div>
            <div class="flex justify-between text-blue-600 border-t pt-2">
                <span>Base IUTS</span>
                <span class="font-mono">{{ number_format($imposable, 0, ',', ' ') }} F</span>
            </div>
            <div class="text-xs text-gray-400">Parts fiscales : {{ number_format($parts, 1) }}</div>
        </div>
        @endif
    </div>

    <a href="{{ route('rh.paie.index') }}"
       class="block text-center px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">
        Voir les bulletins de paie
    </a>
</div>

</div>

{{-- Modal : Nouveau contrat --}}
<div id="modal-contract" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Nouveau contrat</h3>
        <form method="POST" action="{{ route('rh.employes.contracts.store', $employe) }}">
            @csrf
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach(['CDI'=>'CDI','CDD'=>'CDD','stage'=>'Stage','consultant'=>'Consultant'] as $v=>$l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salaire (FCFA)</label>
                        <input type="number" name="base_salary" min="0" step="1000" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date fin (CDD)</label>
                        <input type="date" name="end_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-contract').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Ajouter prime --}}
<div id="modal-allowance" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Ajouter une prime / indemnité</h3>
        <form method="POST" action="{{ route('rh.employes.allowances.store', $employe) }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de prime</label>
                    <select name="payroll_allowance_type_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($allowanceTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->is_taxable ? 'imposable' : 'exonérée' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA)</label>
                        <input type="number" name="amount" min="0" step="100" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono text-right">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dès le</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jusqu'au</label>
                        <input type="date" name="end_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-allowance').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Annuler</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ajouter</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
