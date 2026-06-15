@extends('layouts.erp')
@section('title', $nc->exists ? 'Traiter NC' : 'Nouvelle non-conformité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.non-conformities.index') }}" class="hover:text-gray-700">Non-conformités</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $nc->exists ? 'Traiter' : 'Nouvelle' }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $nc->exists ? 'Traiter la non-conformité' : 'Nouvelle non-conformité' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $nc->exists ? route('qualite.non-conformities.update', $nc) : route('qualite.non-conformities.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($nc->exists)@method('PUT')@endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $nc->title) }}" required maxlength="200" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Gravité <span class="text-red-500">*</span></label>
                <select name="severity" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['mineure'=>'Mineure','majeure'=>'Majeure','critique'=>'Critique'] as $k=>$v)<option value="{{ $k }}" @selected(old('severity',$nc->severity)===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut <span class="text-red-500">*</span></label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['ouverte'=>'Ouverte','en_cours'=>'En cours','cloturee'=>'Clôturée'] as $k=>$v)<option value="{{ $k }}" @selected(old('status',$nc->status)===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                <select name="responsible_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('responsible_id',$nc->responsible_id)==$e->id)>{{ $e->full_name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Échéance</label>
                <input type="date" name="due_date" value="{{ old('due_date', optional($nc->due_date)->format('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Contrôle qualité lié</label>
            <select name="quality_inspection_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                <option value="">—</option>
                @foreach($inspections as $i)<option value="{{ $i->id }}" @selected(old('quality_inspection_id',$nc->quality_inspection_id)==$i->id)>{{ $i->reference }}</option>@endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300">{{ old('description', $nc->description) }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Action corrective (CAPA)</label>
            <textarea name="corrective_action" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300" placeholder="Mesure corrective + préventive…">{{ old('corrective_action', $nc->corrective_action) }}</textarea>
            <p class="text-xs text-gray-400 mt-1">Passez le statut à « Clôturée » une fois l'action vérifiée efficace.</p>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('qualite.non-conformities.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
