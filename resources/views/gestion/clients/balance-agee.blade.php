@extends('layouts.erp')
@section('title', 'Balance âgée clients')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Balance âgée</span>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Balance âgée clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">Créances en cours ventilées par ancienneté — au {{ $today->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2 self-start flex-wrap">
            <a href="{{ route('clients.balance-agee.export-excel', array_filter(['client_id' => $clientId])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-3 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('clients.balance-agee.export-pdf', array_filter(['client_id' => $clientId])) }}"
               class="inline-flex items-center gap-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-medium px-3 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            <a href="{{ route('clients.releve') }}"     class="text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:bg-indigo-50 px-3 py-2 rounded-lg transition-colors">Relevé client</a>
            <a href="{{ route('clients.grand-livre') }}" class="text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:bg-indigo-50 px-3 py-2 rounded-lg transition-colors">Grand livre</a>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex gap-3">
            <select name="client_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 w-72">
                <option value="">Tous les clients</option>
                @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                Filtrer
            </button>
            @if($clientId)
            <a href="{{ route('clients.balance-agee') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-3 py-2 rounded-lg transition-colors">✕</a>
            @endif
        </div>
    </form>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        @php
            $kpis = [
                ['label' => 'Total dû',    'value' => $totals['total'],    'color' => 'gray',   'text' => 'text-gray-900'],
                ['label' => 'Non échu',    'value' => $totals['non_echu'], 'color' => 'blue',   'text' => 'text-blue-700'],
                ['label' => '1 – 30 j',   'value' => $totals['j1_30'],    'color' => 'yellow', 'text' => 'text-yellow-700'],
                ['label' => '31 – 60 j',  'value' => $totals['j31_60'],   'color' => 'orange', 'text' => 'text-orange-700'],
                ['label' => '61 – 90 j',  'value' => $totals['j61_90'],   'color' => 'red',    'text' => 'text-red-700'],
                ['label' => '+ 90 j',     'value' => $totals['j90p'],     'color' => 'red',    'text' => 'text-red-900'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">{{ $kpi['label'] }}</p>
            <p class="text-lg font-bold {{ $kpi['text'] }} tabular-nums">
                {{ number_format($kpi['value'], 0, ',', ' ') }}
                <span class="text-xs font-normal text-gray-400">F</span>
            </p>
        </div>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Total dû</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-blue-600 uppercase tracking-wider bg-blue-50/50">Non échu</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-yellow-600 uppercase tracking-wider bg-yellow-50/50">1 – 30 j</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-orange-600 uppercase tracking-wider bg-orange-50/50">31 – 60 j</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-red-600 uppercase tracking-wider bg-red-50/50">61 – 90 j</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-red-800 uppercase tracking-wider bg-red-100/50">+ 90 j</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <a href="{{ route('clients.show', $row['client_id']) }}" class="font-medium text-blue-600 hover:text-blue-800">
                                {{ $row['name'] }}
                            </a>
                            @if($row['code'])<p class="text-xs text-gray-400">{{ $row['code'] }}</p>@endif
                        </td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums text-gray-900">
                            {{ number_format($row['total'], 0, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-700 bg-blue-50/30">
                            {{ $row['non_echu'] > 0 ? number_format($row['non_echu'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-yellow-700 bg-yellow-50/30">
                            {{ $row['j1_30'] > 0 ? number_format($row['j1_30'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-orange-700 bg-orange-50/30">
                            {{ $row['j31_60'] > 0 ? number_format($row['j31_60'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-red-700 bg-red-50/30">
                            {{ $row['j61_90'] > 0 ? number_format($row['j61_90'], 0, ',', ' ') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-semibold text-red-900 bg-red-100/30">
                            {{ $row['j90p'] > 0 ? number_format($row['j90p'], 0, ',', ' ') : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            Aucune créance en cours.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($rows->count())
                <tfoot>
                    <tr class="bg-gray-800 text-white">
                        <td class="px-4 py-3 font-bold text-xs uppercase">TOTAL</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($totals['total'],    0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($totals['non_echu'], 0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($totals['j1_30'],    0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($totals['j31_60'],   0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($totals['j61_90'],   0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($totals['j90p'],     0, ',', ' ') }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
