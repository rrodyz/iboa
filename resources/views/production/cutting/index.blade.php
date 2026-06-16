@extends('layouts.erp')
@section('title', 'Optimisation de découpe')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.dashboard') }}" class="hover:text-gray-700">Production</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Optimisation découpe</span>
@endsection

@section('content')
@php $initLines = old('items', $input['items'] ?? [['length'=>'','quantity'=>'']]); @endphp
<div class="max-w-4xl mx-auto space-y-5" x-data="{ lines: {{ Js::from(array_values($initLines)) }} }">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Optimisation de découpe</h1>
        <p class="text-sm text-gray-500 mt-0.5">Plan de coupe 1D (cutting stock) — minimise les chutes</p>
    </div>

    @if(!empty($error))
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">{{ $error }}</div>
    @endif

    <form method="POST" action="{{ route('production.cutting.optimize') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Longueur stock</label>
                <input type="number" name="stock_length" value="{{ old('stock_length', $input['stock_length'] ?? 6000) }}" step="0.01" min="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Trait de scie (kerf)</label>
                <input type="number" name="kerf" value="{{ old('kerf', $input['kerf'] ?? 0) }}" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div class="border-t border-gray-100 pt-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-semibold text-gray-900 text-sm">Pièces à couper</h2>
                <button type="button" @click="lines.push({length:'',quantity:''})" class="text-indigo-600 text-sm font-medium hover:underline">+ Ajouter</button>
            </div>
            <div class="tbl-scroll">
                <table class="tbl w-full">
                    <thead><tr><th class="text-left">Longueur</th><th class="text-right">Quantité</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(l, i) in lines" :key="i">
                            <tr>
                                <td><input type="number" step="0.01" min="0" :name="`items[${i}][length]`" x-model="l.length" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono" placeholder="ex. 2000"></td>
                                <td><input type="number" min="0" :name="`items[${i}][quantity]`" x-model="l.quantity" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono" placeholder="ex. 5"></td>
                                <td class="text-right"><button type="button" @click="lines.splice(i,1)" class="text-gray-400 hover:text-red-600 text-sm">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg">Optimiser</button>
        </div>
    </form>

    @if($plan)
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4"><p class="text-xs font-medium text-indigo-600 uppercase tracking-wider">Barres / bobines</p><p class="text-lg font-bold text-indigo-800 mt-1">{{ $plan['bars_count'] }}</p></div>
        <div class="bg-green-50 border border-green-200 rounded-2xl p-4"><p class="text-xs font-medium text-green-600 uppercase tracking-wider">Utilisé</p><p class="text-lg font-bold text-green-800 tabular-nums mt-1">{{ number_format($plan['used'],0,',',' ') }}</p></div>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4"><p class="text-xs font-medium text-red-600 uppercase tracking-wider">Chutes</p><p class="text-lg font-bold text-red-800 tabular-nums mt-1">{{ number_format($plan['waste'],0,',',' ') }}</p></div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4"><p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Rendement</p><p class="text-lg font-bold text-emerald-800 tabular-nums mt-1">{{ number_format($plan['yield'],1,',',' ') }} %</p></div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
        <h2 class="font-semibold text-gray-900">Plan de coupe</h2>
        @foreach($plan['bars'] as $idx => $bar)
        <div>
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                <span>Barre {{ $idx+1 }} — {{ implode(' + ', array_map(fn($c)=>number_format($c,0,',',' '), $bar['cuts'])) }}</span>
                <span>Chute : <span class="{{ $bar['waste']>0 ? 'text-red-600 font-medium' : 'text-green-600' }}">{{ number_format($bar['waste'],0,',',' ') }}</span></span>
            </div>
            <div class="flex h-7 rounded overflow-hidden border border-gray-200">
                @foreach($bar['cuts'] as $c)
                <div class="bg-indigo-400 border-r border-white flex items-center justify-center text-[10px] text-white font-medium" style="width: {{ max(3, $c / $plan['stock_length'] * 100) }}%">{{ number_format($c,0,',',' ') }}</div>
                @endforeach
                @if($bar['waste'] > 0)
                <div class="bg-red-200 flex items-center justify-center text-[10px] text-red-700" style="width: {{ max(2, $bar['waste'] / $plan['stock_length'] * 100) }}%">chute</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
