@extends('layouts.erp')
@section('title', 'Numérotation des documents')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Numérotation des documents</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Numérotation des documents</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Format des numéros générés pour chaque type de document
                @if($fiscalYear)
                    — Exercice : <strong>{{ $fiscalYear->label ?? $fiscalYear->name }}</strong>
                @endif
            </p>
        </div>
        <div class="text-xs text-gray-500 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
            Format type : <code class="font-mono font-semibold text-blue-700">PRÉFIXE-ANNÉE-NNN</code>
        </div>
    </div>

    {{-- Info banner --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <strong>Important :</strong> les références déjà émises ne sont jamais modifiées.
        Toute opération sur le compteur est tracée dans l'historique d'audit.
        Les séquences sont automatiquement créées au début de chaque nouvel exercice.
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
        <strong>Action refusée :</strong>
        <ul class="mt-1 list-disc list-inside">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ── Tableaux par catégorie ─────────────────────────────────────────── --}}
    @php
        $categoryColors = [
            'Ventes'=>'blue','Achats'=>'amber','Trésorerie'=>'emerald',
            'Stock'=>'purple','Comptabilité'=>'indigo',
        ];
    @endphp

    @foreach($grouped as $category => $types)
    @php $color = $categoryColors[$category] ?? 'gray'; @endphp

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-{{ $color }}-100 text-{{ $color }}-700">
                {{ $category }}
            </span>
        </div>

        <div class="tbl-rx">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50/50 border-b border-gray-100">
                <tr>
                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Document</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Format actuel</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Prochain n°</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Compteur</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider" title="Numéro le plus élevé déjà émis en base">N° max émis</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Mode</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">État</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($types as $type)
                    @php
                        $seq = $sequences[$type] ?? null;
                        if (!$seq) continue;

                        $mu = $maxUsed[$type] ?? 0;

                        // Aperçu du prochain n°
                        $yearPart = '';
                        if ($seq->include_year) {
                            $yr       = $seq->year_format === '2' ? now()->format('y') : now()->format('Y');
                            $yearPart = $yr . ($seq->year_separator ?? '-');
                        }
                        $preview = ($seq->prefix ?? '')
                                 . $yearPart
                                 . str_pad((string)($seq->last_number + 1), $seq->padding, '0', STR_PAD_LEFT)
                                 . ($seq->suffix ?? '');

                        // Format affiché
                        $formatStr = trim(($seq->prefix ?? '')
                            . ($seq->include_year ? ($seq->year_format === '2' ? 'YY' : 'YYYY') . ($seq->year_separator ?? '-') : '')
                            . str_repeat('N', $seq->padding)
                            . ($seq->suffix ?? ''));
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">
                            {{ $labels[$type] ?? $type }}
                        </td>
                        <td class="px-3 py-3">
                            <code class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $formatStr }}</code>
                        </td>
                        <td class="px-3 py-3">
                            <code class="font-mono text-sm font-semibold text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded">{{ $preview }}</code>
                        </td>
                        <td class="px-3 py-3 text-center text-sm tabular-nums font-semibold text-gray-700">
                            {{ number_format($seq->last_number) }}
                        </td>
                        <td class="px-3 py-3 text-center">
                            @if($mu > 0)
                                <span class="text-xs tabular-nums {{ $mu > $seq->last_number ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                    {{ number_format($mu) }}@if($mu > $seq->last_number) ⚠@endif
                                </span>
                            @else
                                <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-center">
                            @if($seq->numbering_mode === 'manual')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">Manuel</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Auto</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-center">
                            @if($seq->is_locked)
                                <span class="text-red-500" title="Format verrouillé">🔒</span>
                            @else
                                <span class="text-gray-400" title="Libre">🔓</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('settings.sequences.edit', $seq) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 px-2 py-1 rounded">
                                ✏ Modifier
                            </a>
                            <a href="{{ route('settings.sequences.audit', $seq) }}"
                               class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-2 py-1 rounded">
                                🕒 Historique
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    @endforeach

    {{-- Legend --}}
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
        <h3 class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-3">Exemples de format</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs text-gray-600">
            <div class="flex items-center gap-2">
                <code class="font-mono font-semibold text-indigo-700 bg-white border border-indigo-200 px-2 py-1 rounded">FA-2026-001</code>
                <span>Préfixe + YYYY + 3 chiffres</span>
            </div>
            <div class="flex items-center gap-2">
                <code class="font-mono font-semibold text-indigo-700 bg-white border border-indigo-200 px-2 py-1 rounded">DEV-26-0001</code>
                <span>Préfixe + YY + 4 chiffres</span>
            </div>
            <div class="flex items-center gap-2">
                <code class="font-mono font-semibold text-indigo-700 bg-white border border-indigo-200 px-2 py-1 rounded">BL-00001</code>
                <span>Sans année + 5 chiffres</span>
            </div>
            <div class="flex items-center gap-2">
                <code class="font-mono font-semibold text-indigo-700 bg-white border border-indigo-200 px-2 py-1 rounded">CMD-2026-00042-A</code>
                <span>Préfixe + YYYY + 5 chiffres + suffixe</span>
            </div>
        </div>
    </div>
</div>
@endsection
