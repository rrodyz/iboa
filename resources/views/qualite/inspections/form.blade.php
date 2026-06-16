@extends('layouts.erp')
@section('title', $inspection->exists ? 'Modifier contrôle' : 'Nouveau contrôle qualité')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('qualite.inspections.index') }}" class="hover:text-gray-700">Contrôles qualité</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $inspection->exists ? 'Modifier' : 'Nouveau' }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $inspection->exists ? 'Modifier le contrôle' : 'Nouveau contrôle qualité' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $inspection->exists ? route('qualite.inspections.update', $inspection) : route('qualite.inspections.store') }}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($inspection->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['reception'=>'Réception','en_cours'=>'En cours','produit_fini'=>'Produit fini'] as $k=>$v)<option value="{{ $k }}" @selected(old('type',$inspection->type)===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Verdict <span class="text-red-500">*</span></label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['conforme'=>'Conforme','partiel'=>'Partiel','non_conforme'=>'Non conforme'] as $k=>$v)<option value="{{ $k }}" @selected(old('status',$inspection->status)===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="inspected_at" value="{{ old('inspected_at', optional($inspection->inspected_at)->format('Y-m-d') ?? date('Y-m-d')) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Réception</label>
                <select name="reception_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">—</option>
                    @foreach($receptions as $r)<option value="{{ $r->id }}" @selected(old('reception_id',$inspection->reception_id)==$r->id)>{{ $r->number }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Produit</label>
                <select name="product_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">—</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id',$inspection->product_id)==$p->id)>{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Contrôleur</label>
                <select name="controller_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">—</option>
                    @foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('controller_id',$inspection->controller_id)==$e->id)>{{ $e->full_name }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantité contrôlée</label>
                <input type="number" name="quantity_checked" value="{{ old('quantity_checked', $inspection->quantity_checked) }}" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantité rejetée</label>
                <input type="number" name="quantity_rejected" value="{{ old('quantity_rejected', $inspection->quantity_rejected) }}" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes / défauts constatés</label>
            <textarea name="notes" rows="3" maxlength="2000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-indigo-300">{{ old('notes', $inspection->notes) }}</textarea>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('qualite.inspections.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
