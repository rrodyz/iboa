@extends('layouts.erp')
@section('title', 'Historique - ' . $label)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('settings.sequences.index') }}" class="hover:text-gray-700">Numérotation</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Historique : {{ $label }}</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Historique des modifications</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Séquence <strong>{{ $label }}</strong> — toutes les opérations sont tracées (audit immuable).
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.sequences.edit', $sequence) }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                ✏ Modifier
            </a>
            <a href="{{ route('settings.sequences.index') }}"
               class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-sm font-medium px-4 py-2 rounded-lg">
                ← Retour
            </a>
        </div>
    </div>

    {{-- Statut courant --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider">Compteur</div>
            <div class="font-mono font-bold text-gray-900 text-lg tabular-nums">{{ number_format($sequence->last_number) }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider">Mode</div>
            <div class="mt-1">
                @if($sequence->numbering_mode === 'manual')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">Manuel</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Automatique</span>
                @endif
            </div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider">Verrou</div>
            <div class="font-medium text-gray-900">{{ $sequence->is_locked ? '🔒 Verrouillé' : '🔓 Libre' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 uppercase tracking-wider">Dernière modif.</div>
            <div class="text-gray-700 text-xs">
                {{ $sequence->updated_at?->format('d/m/Y H:i') ?? '—' }}
                @if($sequence->lastModifier)
                    <br>par {{ $sequence->lastModifier->name }}
                @endif
            </div>
        </div>
    </div>

    {{-- Audit table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="tbl-rx">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Utilisateur</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Avant</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Après</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Motif</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($audits as $a)
                <tr class="hover:bg-gray-50/50 align-top">
                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                        {{ $a->created_at->format('d/m/Y H:i:s') }}
                    </td>
                    <td class="px-4 py-3 text-gray-700 text-sm">{{ $a->user?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @php
                            $colors = [
                                'create'=>'bg-blue-100 text-blue-700',
                                'update_format'=>'bg-indigo-100 text-indigo-700',
                                'set_counter'=>'bg-orange-100 text-orange-700',
                                'reset_counter'=>'bg-red-100 text-red-700',
                                'lock'=>'bg-gray-200 text-gray-700',
                                'unlock'=>'bg-gray-100 text-gray-700',
                                'next_number'=>'bg-green-50 text-green-700',
                            ];
                            $cls = $colors[$a->action] ?? 'bg-gray-100 text-gray-700';
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $cls }}">
                            {{ $a->action_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs font-mono text-gray-600">
                        @if($a->before)
                            <pre class="whitespace-pre-wrap max-w-xs">{{ json_encode($a->before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs font-mono text-gray-600">
                        @if($a->after)
                            <pre class="whitespace-pre-wrap max-w-xs">{{ json_encode($a->after, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs italic">{{ $a->reason ?: '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400 italic">
                        Aucune opération enregistrée pour cette séquence.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">{{ $audits->links() }}</div>
</div>
@endsection
