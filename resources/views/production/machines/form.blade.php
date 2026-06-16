@extends('layouts.erp')
@section('title', $machine->exists ? 'Modifier machine' : 'Nouvelle machine')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.machines.index') }}" class="hover:text-gray-700">Machines</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $machine->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $machine->exists ? 'Modifier la machine' : 'Nouvelle machine' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $machine->exists ? route('production.machines.update', $machine) : route('production.machines.store') }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($machine->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $machine->code) }}" required maxlength="30"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['decoupe'=>'Découpe','profilage'=>'Profilage','mixte'=>'Mixte'] as $k=>$v)
                        <option value="{{ $k }}" @selected(old('type',$machine->type)===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $machine->name) }}" required maxlength="120"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Coût horaire (FCFA)</label>
                <input type="number" name="hourly_cost" value="{{ old('hourly_cost', $machine->hourly_cost) }}" min="0" step="1"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut <span class="text-red-500">*</span></label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['active'=>'Active','maintenance'=>'Maintenance','arret'=>'Arrêt'] as $k=>$v)
                        <option value="{{ $k }}" @selected(old('status',$machine->status ?? 'active')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="2" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300">{{ old('notes', $machine->notes) }}</textarea>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $machine->is_active ?? true)) class="rounded border-gray-300 text-indigo-600">
            Active
        </label>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('production.machines.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
