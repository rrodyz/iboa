@extends('layouts.erp')
@section('title', $maintenance->exists ? 'Modifier intervention' : 'Nouvelle intervention')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.maintenance.index') }}" class="hover:text-gray-700">Maintenance</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $maintenance->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $maintenance->exists ? 'Modifier l\'intervention' : 'Nouvelle intervention' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $maintenance->exists ? route('production.maintenance.update', $maintenance) : route('production.maintenance.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($maintenance->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Machine <span class="text-red-500">*</span></label>
                <select name="machine_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Choisir —</option>
                    @foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id',$maintenance->machine_id)==$m->id)>{{ $m->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['preventive'=>'Préventive','corrective'=>'Corrective'] as $k=>$v)<option value="{{ $k }}" @selected(old('type',$maintenance->type)===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut <span class="text-red-500">*</span></label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['planifie'=>'Planifiée','en_cours'=>'En cours','termine'=>'Terminée'] as $k=>$v)<option value="{{ $k }}" @selected(old('status',$maintenance->status ?? 'planifie')===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Intitulé <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $maintenance->title) }}" required maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date planifiée</label>
                <input type="date" name="planned_at" value="{{ old('planned_at', optional($maintenance->planned_at)->format('Y-m-d') ?? date('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Arrêt (min)</label>
                <input type="number" name="downtime_minutes" value="{{ old('downtime_minutes', $maintenance->downtime_minutes) }}" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Coût (FCFA)</label>
                <input type="number" name="cost" value="{{ old('cost', $maintenance->cost) }}" step="1" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Opérateur</label>
                <select name="operator_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('operator_id',$maintenance->operator_id)==$e->id)>{{ $e->full_name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300">{{ old('notes', $maintenance->notes) }}</textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('production.maintenance.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
