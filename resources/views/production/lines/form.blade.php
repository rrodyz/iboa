@extends('layouts.erp')
@section('title', $line->exists ? 'Modifier ligne' : 'Nouvelle ligne')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.lines.index') }}" class="hover:text-gray-700">Lignes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $line->exists ? 'Modifier' : 'Nouvelle' }}</span>
@endsection

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $line->exists ? 'Modifier la ligne' : 'Nouvelle ligne' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $line->exists ? route('production.lines.update', $line) : route('production.lines.store') }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($line->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                <input type="text" name="code" value="{{ old('code', $line->code) }}" required maxlength="30"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Machine</label>
                <select name="machine_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Aucune —</option>
                    @foreach($machines as $m)
                        <option value="{{ $m->id }}" @selected(old('machine_id',$line->machine_id)==$m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $line->name) }}" required maxlength="120"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $line->is_active ?? true)) class="rounded border-gray-300 text-indigo-600">
            Active
        </label>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('production.lines.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
