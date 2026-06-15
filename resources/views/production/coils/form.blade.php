@extends('layouts.erp')
@section('title', $coil->exists ? 'Modifier bobine' : 'Réception bobine')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('production.coils.index') }}" class="hover:text-gray-700">Bobines</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $coil->exists ? 'Modifier' : 'Réception' }}</span>
@endsection

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold text-gray-900">{{ $coil->exists ? 'Modifier la bobine' : 'Réception d\'une bobine' }}</h1>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $coil->exists ? route('production.coils.update', $coil) : route('production.coils.store') }}"
          class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @if($coil->exists)@method('PUT')@endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Référence <span class="text-red-500">*</span></label>
                <input type="text" name="reference" value="{{ old('reference', $coil->reference) }}" required maxlength="60"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">N° de lot</label>
                <input type="text" name="lot_number" value="{{ old('lot_number', $coil->lot_number) }}" maxlength="60"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Article matière (stock)</label>
                <select name="product_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Aucun —</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id',$coil->product_id)==$p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                <select name="supplier_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Aucun —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id',$coil->supplier_id)==$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
                <input type="text" name="color" value="{{ old('color', $coil->color) }}" maxlength="60"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Épaisseur (mm)</label>
                <input type="number" name="thickness" value="{{ old('thickness', $coil->thickness) }}" step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Largeur (mm)</label>
                <input type="number" name="width" value="{{ old('width', $coil->width) }}" step="0.1" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Poids initial (kg) <span class="text-red-500">*</span></label>
                <input type="number" name="initial_weight" value="{{ old('initial_weight', $coil->initial_weight) }}" step="0.01" min="0.01" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Longueur estimée (m)</label>
                <input type="number" name="estimated_length" value="{{ old('estimated_length', $coil->estimated_length) }}" step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Prix d'achat total (FCFA) <span class="text-red-500">*</span></label>
                <input type="number" name="purchase_price" value="{{ old('purchase_price', $coil->purchase_price) }}" step="1" min="0" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de réception</label>
            <input type="date" name="received_at" value="{{ old('received_at', optional($coil->received_at)->format('Y-m-d') ?? date('Y-m-d')) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <p class="text-xs text-gray-400">Le coût au kg est calculé automatiquement (prix d'achat ÷ poids initial).</p>

        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
            <a href="{{ route('production.coils.index') }}" class="border border-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Annuler</a>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg">Enregistrer</button>
        </div>
    </form>
</div>
@endsection
