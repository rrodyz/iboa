@extends('layouts.erp')
@section('title', $routing->exists ? 'Modifier gamme' : 'Nouvelle gamme')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.routings.index') }}" class="hover:text-gray-700">Gammes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $routing->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
@php
    $initialOps = old('operations', $routing->exists ? $routing->operations->map(fn($o)=>[
        'name'=>$o->name,'work_center_id'=>$o->work_center_id,'sequence'=>$o->sequence,
        'setup_minutes'=>$o->setup_minutes,'run_minutes_per_unit'=>$o->run_minutes_per_unit,
    ])->values()->all() : []);
@endphp
<div class="max-w-4xl mx-auto space-y-5" x-data="{ ops: {{ Js::from($initialOps) }} }">
    <h1 class="text-2xl font-bold text-gray-900">{{ $routing->exists ? 'Modifier la gamme' : 'Nouvelle gamme opératoire' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $routing->exists ? route('production.routings.update', $routing) : route('production.routings.store') }}" class="space-y-5">
        @csrf
        @if($routing->exists)@method('PUT')@endif

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code', $routing->code) }}" required maxlength="30" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $routing->name) }}" required maxlength="150" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nomenclature</label>
                    <select name="bill_of_material_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                        <option value="">— Aucune —</option>
                        @foreach($boms as $b)<option value="{{ $b->id }}" @selected(old('bill_of_material_id',$routing->bill_of_material_id)==$b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $routing->is_active ?? true)) class="rounded border-gray-300 text-indigo-600"> Active
            </label>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Opérations</h2>
                <button type="button" @click="ops.push({name:'',work_center_id:'',sequence:(ops.length+1)*10,setup_minutes:'',run_minutes_per_unit:''})" class="text-indigo-600 text-sm font-medium hover:underline">+ Ajouter une opération</button>
            </div>
            <div class="tbl-scroll">
                <table class="tbl w-full">
                    <thead><tr><th class="text-left">Séq.</th><th class="text-left">Opération</th><th class="text-left">Centre</th><th class="text-right">Réglage (min)</th><th class="text-right">Temps/u (min)</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(op, i) in ops" :key="i">
                            <tr>
                                <td><input type="number" min="0" :name="`operations[${i}][sequence]`" x-model="op.sequence" class="w-16 border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td><input type="text" :name="`operations[${i}][name]`" x-model="op.name" class="w-full border border-gray-200 rounded px-2 py-1 text-sm" placeholder="ex. Profilage"></td>
                                <td>
                                    <select :name="`operations[${i}][work_center_id]`" x-model="op.work_center_id" class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
                                        <option value="">—</option>
                                        @foreach($centers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="number" step="0.5" min="0" :name="`operations[${i}][setup_minutes]`" x-model="op.setup_minutes" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td><input type="number" step="0.01" min="0" :name="`operations[${i}][run_minutes_per_unit]`" x-model="op.run_minutes_per_unit" class="w-full border border-gray-200 rounded px-2 py-1 text-sm text-right font-mono"></td>
                                <td class="text-right"><button type="button" @click="ops.splice(i,1)" class="text-gray-400 hover:text-red-600 text-sm">✕</button></td>
                            </tr>
                        </template>
                        <tr x-show="ops.length === 0"><td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">Aucune opération. Cliquez « Ajouter une opération ».</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('production.routings.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
