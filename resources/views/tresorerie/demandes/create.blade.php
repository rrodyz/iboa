@extends('layouts.erp')
@section('title', 'Nouvelle demande de paiement')

@section('breadcrumb')
    <a href="{{ route('tresorerie.dashboard') }}" class="hover:text-gray-700">Trésorerie</a>
    <span class="mx-1">/</span>
    <a href="{{ route('tresorerie.demandes.index') }}" class="hover:text-gray-700">Demandes</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Nouvelle</span>
@endsection

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-gray-900">Nouvelle demande de paiement</h1>
        <p class="text-sm text-gray-500 mt-0.5">Créée en brouillon — à soumettre pour validation</p>
    </div>

    <form method="POST" action="{{ route('tresorerie.demandes.store') }}"
          class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Objet <span class="text-red-500">*</span></label>
            <input type="text" name="object" value="{{ old('object') }}" maxlength="255" required placeholder="Ex. : Paiement loyer juin"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Fournisseur</label>
                <select name="supplier_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
                    <option value="">— Aucun (bénéficiaire libre) —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Bénéficiaire (si pas de fournisseur)</label>
                <input type="text" name="beneficiary" value="{{ old('beneficiary') }}" maxlength="150"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Montant (FCFA) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="1" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-right font-mono focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Échéance</label>
                <input type="date" name="due_date" value="{{ old('due_date') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Priorité</label>
                <select name="priority" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
                    @foreach(['normale'=>'Normale','basse'=>'Basse','haute'=>'Haute','urgente'=>'Urgente'] as $v=>$l)
                        <option value="{{ $v }}" @selected(old('priority','normale')===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Mode de paiement souhaité</label>
            <select name="payment_method_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-300">
                <option value="">— Indifférent —</option>
                @foreach($paymentMethods as $pm)
                    <option value="{{ $pm->id }}" @selected(old('payment_method_id') == $pm->id)>{{ $pm->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
            <textarea name="notes" rows="2" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm resize-none focus:ring-2 focus:ring-indigo-300">{{ old('notes') }}</textarea>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <a href="{{ route('tresorerie.demandes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Annuler</a>
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">Créer la demande</button>
        </div>
    </form>
</div>
@endsection
