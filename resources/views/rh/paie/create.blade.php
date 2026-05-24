@extends('layouts.erp')
@section('title', 'Nouveau bulletin de paie')
@section('breadcrumb')
    <a href="{{ route('rh.paie.index') }}" class="hover:text-gray-700">Paie</a>
    <span class="mx-1">/</span><span>Nouveau bulletin</span>
@endsection

@section('content')
<div class="max-w-lg mx-auto">
<h1 class="text-2xl font-bold text-gray-900 mb-6">Nouveau bulletin de paie</h1>

<div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 text-sm text-blue-700">
    <strong>{{ $activeCount }}</strong> employé(s) actif(s) avec contrat seront inclus dans le calcul.
</div>

@if($errors->any())
<div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('rh.paie.store') }}">
@csrf
<x-form-guard />
<div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mois <span class="text-red-500">*</span></label>
            <select name="period_month" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach([1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'] as $m=>$l)
                    <option value="{{ $m }}" @selected($m == $suggestMonth)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Année <span class="text-red-500">*</span></label>
            <input type="number" name="period_year" value="{{ $suggestYear }}" min="2020" max="2100" required
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono text-center">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
    </div>
</div>
<div class="flex justify-end gap-3 mt-4">
    <a href="{{ route('rh.paie.index') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Annuler</a>
    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Créer le bulletin</button>
</div>
</form>
</div>
@endsection
