@extends('layouts.erp')
@section('title', 'Rapports production')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Rapports</span>
@endsection

@section('content')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Rapports de production</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $report['title'] }} — du {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('production.reports', array_merge(request()->only('type','from','to'), ['export'=>'pdf'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">PDF</a>
            <a href="{{ route('production.reports', array_merge(request()->only('type','from','to'), ['export'=>'excel'])) }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">Excel</a>
        </div>
    </div>

    {{-- Filtres --}}
    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Type de rapport</label>
            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-56" onchange="this.form.submit()">
                @foreach($types as $k => $lbl)<option value="{{ $k }}" @selected($type===$k)>{{ $lbl }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Du</label>
            <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Au</label>
            <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-700">Générer</button>
    </form>

    {{-- Tableau --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead>
                    <tr>
                        @foreach($report['headers'] as $i => $h)
                        <th class="{{ in_array($i, $report['numeric']) ? 'text-right' : 'text-left' }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                    <tr>
                        @foreach($row as $i => $cell)
                        <td class="{{ in_array($i, $report['numeric']) ? 'text-right font-mono tabular-nums text-gray-900' : 'text-gray-700' }}">
                            {{ in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell }}
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="{{ count($report['headers']) }}" class="px-4 py-12 text-center text-gray-400">Aucune donnée sur la période.</td></tr>
                    @endforelse
                </tbody>
                @if($report['totals'])
                <tfoot>
                    <tr class="font-semibold bg-gray-50">
                        @foreach($report['totals'] as $i => $cell)
                        <td class="{{ in_array($i, $report['numeric']) ? 'text-right font-mono tabular-nums text-gray-900' : 'text-gray-700' }}">
                            {{ in_array($i, $report['numeric']) && is_numeric($cell) ? number_format($cell, (floor($cell) == $cell ? 0 : 2), ',', ' ') : $cell }}
                        </td>
                        @endforeach
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
