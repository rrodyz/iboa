@extends('layouts.erp')
@section('title', $center->exists ? 'Modifier centre' : 'Nouveau centre de travail')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.work-centers.index') }}" class="hover:text-gray-700">Centres de travail</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $center->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $center->exists ? 'Modifier le centre' : 'Nouveau centre de travail' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $center->exists ? route('production.work-centers.update', $center) : route('production.work-centers.store') }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($center->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $center->code) }}" required maxlength="30"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Machine rattachée</label>
                <select name="machine_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Aucune —</option>
                    @foreach($machines as $m)<option value="{{ $m->id }}" @selected(old('machine_id',$center->machine_id)==$m->id)>{{ $m->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $center->name) }}" required maxlength="120"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Capacité (h/jour)</label>
                <input type="number" name="capacity_hours_per_day" value="{{ old('capacity_hours_per_day', $center->capacity_hours_per_day ?? 8) }}" step="0.5" min="0" max="24"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Coût horaire (FCFA)</label>
                <input type="number" name="cost_per_hour" value="{{ old('cost_per_hour', $center->cost_per_hour) }}" step="1" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rendement (%)</label>
                <input type="number" name="efficiency_rate" value="{{ old('efficiency_rate', $center->efficiency_rate ?? 100) }}" step="1" min="0" max="100"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300">{{ old('notes', $center->notes) }}</textarea>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $center->is_active ?? true)) class="rounded border-gray-300 text-indigo-600">
            Actif
        </label>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('production.work-centers.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
