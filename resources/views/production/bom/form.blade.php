@extends('layouts.erp')
@section('title', $bom->exists ? 'Modifier nomenclature' : 'Nouvelle nomenclature')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.bom.index') }}" class="hover:text-gray-700">Nomenclatures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $bom->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $initialLines = old('lines', $bom->exists ? $bom->lines->map(fn($l)=>[
        'product_id'=>$l->product_id,'label'=>$l->label,'quantity_per_meter'=>$l->quantity_per_meter,
        'unit_id'=>$l->unit_id,'waste_rate'=>$l->waste_rate,
    ])->values()->all() : []);
@endphp
<div class="max-w-4xl mx-auto space-y-5" x-data="{ lines: {{ Js::from($initialLines) }} }">
    <h1 class="text-2xl font-bold text-gray-900">{{ $bom->exists ? 'Modifier la nomenclature' : 'Nouvelle nomenclature' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $bom->exists ? route('production.bom.update', $bom) : route('production.bom.store') }}" class="space-y-5">
        @csrf
        @if($bom->exists)@method('PUT')@endif

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $bom->name) }}" required maxlength="150"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Produit fini</label>
                    <select name="product_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucun —</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id',$bom->product_id)==$p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type de tôle</label>
                    <input type="text" name="sheet_type" value="{{ old('sheet_type', $bom->sheet_type) }}" maxlength="60"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Épaisseur (mm)</label>
                    <input type="number" name="thickness" value="{{ old('thickness', $bom->thickness) }}" step="0.01" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Largeur bobine (mm)</label>
                    <input type="number" name="coil_width" value="{{ old('coil_width', $bom->coil_width) }}" step="0.1" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Largeur utile (mm)</label>
                    <input type="number" name="usable_width" value="{{ old('usable_width', $bom->usable_width) }}" step="0.1" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conso / mètre (kg)</label>
                    <input type="number" name="consumption_per_meter" value="{{ old('consumption_per_meter', $bom->consumption_per_meter) }}" step="0.0001" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Taux chute std. (%)</label>
                    <input type="number" name="standard_waste_rate" value="{{ old('standard_waste_rate', $bom->standard_waste_rate) }}" step="0.01" min="0" max="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Temps machine / u (min)</label>
                    <input type="number" name="machine_time_per_unit" value="{{ old('machine_time_per_unit', $bom->machine_time_per_unit) }}" step="0.01" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">MO / u (FCFA)</label>
                    <input type="number" name="labor_per_unit" value="{{ old('labor_per_unit', $bom->labor_per_unit) }}" step="1" min="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Coût standard / unité (FCFA) — base d'analyse des écarts</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Matière std</label>
                        <input type="number" name="std_material_cost" value="{{ old('std_material_cost', $bom->std_material_cost) }}" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">MO std</label>
                        <input type="number" name="std_labor_cost" value="{{ old('std_labor_cost', $bom->std_labor_cost) }}" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Machine std</label>
                        <input type="number" name="std_machine_cost" value="{{ old('std_machine_cost', $bom->std_machine_cost) }}" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Indirect std</label>
                        <input type="number" name="std_overhead_cost" value="{{ old('std_overhead_cost', $bom->std_overhead_cost) }}" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
                    </div>
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bom->is_active ?? true)) class="rounded border-gray-300 text-indigo-600">
                Active
            </label>
        </div>

        {{-- Composants --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Composants / accessoires</h2>
                <button type="button" @click="lines.push({product_id:'',label:'',quantity_per_meter:'',unit_id:'',waste_rate:''})"
                        class="text-indigo-600 text-sm font-medium hover:underline">+ Ajouter une ligne</button>
            </div>

            <div class="tbl-scroll">
                <table class="tbl w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Article</th>
                            <th class="text-left">Libellé</th>
                            <th class="text-right">Qté / mètre</th>
                            <th class="text-left">Unité</th>
                            <th class="text-right">Chute %</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, i) in lines" :key="i">
                            <tr>
                                <td>
                                    <select :name="`lines[${i}][product_id]`" x-model="line.product_id" class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
                                        <option value="">—</option>
                                        @foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="text" :name="`lines[${i}][label]`" x-model="line.label" class="w-full border border-gray-200 rounded px-2 py-1 text-sm" placeholder="ex. Faîtière"></td>
                                <td><input type="number" step="0.0001" min="0" :name="`lines[${i}][quantity_per_meter]`" x-model="line.quantity_per_meter" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td>
                                    <select :name="`lines[${i}][unit_id]`" x-model="line.unit_id" class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
                                        <option value="">—</option>
                                        @foreach($units as $u)<option value="{{ $u->id }}">{{ $u->abbreviation ?? $u->name }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" :name="`lines[${i}][waste_rate]`" x-model="line.waste_rate" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td class="text-right"><button type="button" @click="lines.splice(i,1)" class="text-gray-400 hover:text-red-600 text-sm">✕</button></td>
                            </tr>
                        </template>
                        <tr x-show="lines.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Aucun composant. Cliquez « Ajouter une ligne ».</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('production.bom.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
