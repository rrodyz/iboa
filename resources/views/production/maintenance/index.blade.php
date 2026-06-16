@extends('layouts.erp')
@section('title', 'Maintenance machines')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Maintenance</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Maintenance machines</h1>
            <p class="text-sm text-gray-500 mt-0.5">Préventive · corrective · disponibilité (MTBF / MTTR)</p>
        </div>
        @can('production.update')
        <a href="{{ route('production.maintenance.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nouvelle intervention
        </a>
        @endcan
    </div>

    @if(count($due))
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
        <p class="text-sm font-semibold text-amber-800 mb-1">⚠ Maintenance préventive due ({{ count($due) }})</p>
        <p class="text-sm text-amber-700">{{ collect($due)->pluck('name')->implode(' · ') }}</p>
    </div>
    @endif

    {{-- Disponibilité par machine --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Disponibilité machines (30 j)</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Machine</th><th class="text-right">Disponibilité</th><th class="text-right">Arrêts</th><th class="text-right">Pannes</th><th class="text-right">MTBF</th><th class="text-right">MTTR</th><th class="text-right">Coût</th></tr></thead>
                <tbody>
                    @forelse($machineKpis as $k)
                    <tr>
                        <td class="text-gray-800 font-medium">{{ $k['machine']->name }}</td>
                        <td class="text-right">
                            @php $av = $k['availability']; $ac = $av >= 90 ? 'text-green-600' : ($av >= 75 ? 'text-amber-600' : 'text-red-600'); @endphp
                            <span class="font-bold tabular-nums {{ $ac }}">{{ number_format($av,1,',',' ') }} %</span>
                        </td>
                        <td class="text-right tabular-nums text-gray-600">{{ number_format($k['downtime_h'],1,',',' ') }} h</td>
                        <td class="text-right tabular-nums {{ $k['failures']>0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">{{ $k['failures'] }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ $k['mtbf_h'] !== null ? number_format($k['mtbf_h'],1,',',' ').' h' : '—' }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ $k['mttr_h'] !== null ? number_format($k['mttr_h'],1,',',' ').' h' : '—' }}</td>
                        <td class="text-right tabular-nums text-gray-700">{{ number_format($k['cost'],0,',',' ') }} F</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune machine active.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Interventions --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-900">Interventions</h2></div>
        <div class="tbl-scroll">
            <table class="tbl tbl-sticky w-full">
                <thead><tr><th class="text-left">Machine</th><th class="text-left">Type</th><th class="text-left">Intitulé</th><th class="text-left">Statut</th><th class="text-left">Planifiée</th><th class="text-right">Arrêt</th><th></th></tr></thead>
                <tbody>
                    @forelse($maintenances as $m)
                    <tr>
                        <td class="text-gray-800">{{ $m->machine?->name ?? '—' }}</td>
                        <td>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $m->type==='corrective' ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700' }}">{{ $m->typeLabel() }}</span>
                        </td>
                        <td class="text-gray-700 text-sm">{{ $m->title }}</td>
                        <td>
                            @php $sc = match($m->status){ 'planifie'=>'bg-gray-100 text-gray-600','en_cours'=>'bg-amber-100 text-amber-700',default=>'bg-green-100 text-green-700' }; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $m->statusLabel() }}</span>
                        </td>
                        <td class="text-gray-500 text-xs">{{ optional($m->planned_at)->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-right tabular-nums text-gray-600">{{ $m->downtime_minutes > 0 ? number_format($m->downtime_minutes/60,1,',',' ').' h' : '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('production.update')
                            @if($m->status === 'planifie')
                            <form method="POST" action="{{ route('production.maintenance.start', $m) }}" class="inline">@csrf<button class="text-amber-600 hover:underline text-xs font-medium">Démarrer</button></form>
                            @elseif($m->status === 'en_cours')
                            <form method="POST" action="{{ route('production.maintenance.finish', $m) }}" class="inline">@csrf<button class="text-green-600 hover:underline text-xs font-medium">Terminer</button></form>
                            @endif
                            <a href="{{ route('production.maintenance.edit', $m) }}" class="text-indigo-600 hover:underline text-xs ml-2">Modifier</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucune intervention.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($maintenances->hasPages())<div class="px-4 py-3 border-t border-gray-100">{{ $maintenances->links() }}</div>@endif
    </div>
</div>
@endsection
