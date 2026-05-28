@extends('layouts.erp')
@section('title', 'CRM — Modifier contact')

@section('breadcrumb')
    <a href="{{ route('crm.dashboard') }}" class="hover:text-gray-700">CRM</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.contacts.index') }}" class="hover:text-gray-700">Contacts</a>
    <span class="mx-1">/</span>
    <a href="{{ route('crm.contacts.show', $contact) }}" class="hover:text-gray-700">{{ $contact->name }}</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Modifier</span>
@endsection

@section('content')
<form method="POST" action="{{ route('crm.contacts.update', $contact) }}" class="space-y-5">
    @csrf
    @method('PUT')

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Modifier — {{ $contact->name }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.contacts.show', $contact) }}"
               class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                Annuler
            </a>
            <button type="submit"
                    class="px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                Mettre à jour
            </button>
        </div>
    </div>

    @include('crm.contacts._form')
</form>
@endsection
