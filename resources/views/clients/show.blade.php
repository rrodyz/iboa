@extends('layouts.erp')
@section('title', $client->displayName())

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('clients.index') }}" class="hover:text-gray-700">Clients</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $client->displayName() }}</span>
@endsection

@section('content')
<div class="space-y-6" x-data="{ showInteractionModal: false }">

    {{-- ================================================================
         Header
    ================================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-indigo-700 font-bold text-xl">
                        {{ strtoupper(substr($client->displayName(), 0, 2)) }}
                    </span>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $client->displayName() }}</h1>
                        @if($client->type === 'entreprise')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Entreprise</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Particulier</span>
                        @endif
                        @if($client->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Actif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactif</span>
                        @endif
                        {{-- Badge CRM : lien vers la fiche prospect d'origine --}}
                        @php $crmContact = \App\Models\CrmContact::where('client_id', $client->id)->first(); @endphp
                        @if($crmContact)
                            <a href="{{ route('crm.contacts.show', $crmContact) }}"
                               class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-cyan-100 text-cyan-700 hover:bg-cyan-200 transition-colors">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                CRM
                            </a>
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 font-mono mt-0.5">{{ $client->code }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('ventes.factures.create', ['client_id' => $client->id]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Nouvelle facture
                </a>
                <a href="{{ route('clients.edit', $client) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                <a href="{{ route('clients.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================
         2-column layout: info + stats
    ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Info card --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Informations
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                @if($client->email)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Email</dt>
                    <dd class="mt-0.5">
                        <a href="mailto:{{ $client->email }}" class="text-indigo-600 hover:underline">{{ $client->email }}</a>
                    </dd>
                </div>
                @endif

                @if($client->phone)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $client->phone }}</dd>
                </div>
                @endif

                @if($client->mobile)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $client->mobile }}</dd>
                </div>
                @endif

                @if($client->website)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Site web</dt>
                    <dd class="mt-0.5">
                        <a href="{{ $client->website }}" target="_blank" rel="noopener"
                           class="text-indigo-600 hover:underline truncate block">{{ $client->website }}</a>
                    </dd>
                </div>
                @endif

                @if($client->ifu)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">IFU / N° fiscal</dt>
                    <dd class="mt-0.5 font-mono text-gray-900">{{ $client->ifu }}</dd>
                </div>
                @endif

                @if($client->rccm)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">RCCM</dt>
                    <dd class="mt-0.5 font-mono text-gray-900">{{ $client->rccm }}</dd>
                </div>
                @endif

                @if($client->credit_limit)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Limite de crédit</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ number_format($client->credit_limit, 0, ',', ' ') }} FCFA</dd>
                </div>
                @endif

                @if($client->payment_days)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Délai de paiement</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $client->payment_days }} jours</dd>
                </div>
                @endif

                @if($client->default_discount)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Remise par défaut</dt>
                    <dd class="mt-0.5 text-gray-900">{{ $client->default_discount }} %</dd>
                </div>
                @endif
            </div>

            @if($client->notes)
            <div class="pt-3 border-t border-gray-100">
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Notes</dt>
                <dd class="text-sm text-gray-700 whitespace-pre-wrap">{{ $client->notes }}</dd>
            </div>
            @endif
        </div>

        {{-- Right: Stats --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Statistiques</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total facturé</span>
                        <span class="text-sm font-semibold text-gray-900 tabular-nums">
                            {{ number_format($totalInvoiced, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Total payé</span>
                        <span class="text-sm font-semibold text-green-700 tabular-nums">
                            {{ number_format($totalPaid, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div class="pt-2 border-t border-gray-100 flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">Solde dû</span>
                        <span class="text-sm font-bold tabular-nums {{ $balance > 0 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ number_format($balance, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Activité</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Factures</span>
                        <span class="font-medium">{{ $client->invoices_count }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Interactions</span>
                        <span class="font-medium">{{ $client->interactions_count }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Contacts</span>
                        <span class="font-medium">{{ $client->contacts_count }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Adresses</span>
                        <span class="font-medium">{{ $client->addresses_count }}</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100">
                        <span class="text-gray-500">Créé le</span>
                        <span class="font-medium">{{ $client->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ================================================================
         Contacts section
    ================================================================ --}}
    @if($client->contacts->count() > 0)
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Contacts
            <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                {{ $client->contacts->count() }}
            </span>
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($client->contacts as $contact)
            <div class="border border-gray-200 rounded-lg p-4 relative">
                @if($contact->is_primary)
                <span class="absolute top-3 right-3 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                    Principal
                </span>
                @endif
                <div class="flex items-center gap-3 mb-3 pr-16">
                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-gray-600 text-xs font-bold">
                            {{ strtoupper(substr($contact->first_name ?: $contact->last_name, 0, 1)) }}{{ strtoupper(substr($contact->last_name, 0, 1)) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">
                            {{ trim(($contact->civility ? $contact->civility.' ' : '').$contact->first_name.' '.$contact->last_name) }}
                        </p>
                        @if($contact->job_title)
                        <p class="text-xs text-gray-500">{{ $contact->job_title }}</p>
                        @endif
                    </div>
                </div>
                <div class="space-y-1 text-xs text-gray-600">
                    @if($contact->phone)
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $contact->phone }}
                    </div>
                    @endif
                    @if($contact->mobile)
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        {{ $contact->mobile }}
                    </div>
                    @endif
                    @if($contact->email)
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a href="mailto:{{ $contact->email }}" class="text-indigo-600 hover:underline">{{ $contact->email }}</a>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ================================================================
         Addresses section
    ================================================================ --}}
    @if($client->addresses->count() > 0)
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Adresses
            <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                {{ $client->addresses->count() }}
            </span>
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($client->addresses as $address)
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    @php
                        $typeLabels = ['livraison' => ['label' => 'Livraison', 'color' => 'bg-blue-100 text-blue-700'],
                                       'facturation' => ['label' => 'Facturation', 'color' => 'bg-orange-100 text-orange-700'],
                                       'siege' => ['label' => 'Siège social', 'color' => 'bg-purple-100 text-purple-700']];
                        $typeInfo = $typeLabels[$address->type] ?? ['label' => ucfirst($address->type), 'color' => 'bg-gray-100 text-gray-600'];
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $typeInfo['color'] }}">
                        {{ $typeInfo['label'] }}
                    </span>
                    @if($address->is_default)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                        Défaut
                    </span>
                    @endif
                </div>
                <div class="text-sm text-gray-700 space-y-0.5">
                    <p class="font-medium">{{ $address->address }}</p>
                    @if($address->city || $address->region)
                    <p class="text-gray-500">{{ implode(', ', array_filter([$address->city, $address->region])) }}</p>
                    @endif
                    @if($address->country)
                    <p class="text-gray-500">{{ $address->country }}</p>
                    @endif
                    @if($address->phone)
                    <p class="text-gray-500 text-xs mt-1">{{ $address->phone }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ================================================================
         Interactions timeline + modal
    ================================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                Interactions
                @if($client->interactions->count() > 0)
                <span class="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">
                    {{ $client->interactions->count() }}
                </span>
                @endif
            </h2>
            <button type="button"
                    @click="showInteractionModal = true"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle interaction
            </button>
        </div>

        @if($client->interactions->count() === 0)
        <div class="text-center py-10 text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p class="text-sm">Aucune interaction enregistrée</p>
        </div>
        @else
        <div class="relative">
            {{-- Timeline vertical line --}}
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-100" aria-hidden="true"></div>
            <div class="space-y-4">
                @foreach($client->interactions as $interaction)
                @php
                    $typeConfig = [
                        'appel'  => ['icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'color' => 'bg-blue-100 text-blue-600', 'label' => 'Appel'],
                        'email'  => ['icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'color' => 'bg-yellow-100 text-yellow-600', 'label' => 'Email'],
                        'rdv'    => ['icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'color' => 'bg-green-100 text-green-600', 'label' => 'RDV'],
                        'visite' => ['icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z', 'color' => 'bg-purple-100 text-purple-600', 'label' => 'Visite'],
                        'autre'  => ['icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'color' => 'bg-gray-100 text-gray-600', 'label' => 'Autre'],
                    ];
                    $tc = $typeConfig[$interaction->type] ?? $typeConfig['autre'];
                @endphp
                <div class="relative flex gap-4 pl-10">
                    {{-- Icon bubble --}}
                    <div class="absolute left-0 w-8 h-8 rounded-full {{ $tc['color'] }} flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tc['icon'] }}"/>
                        </svg>
                    </div>
                    {{-- Content --}}
                    <div class="flex-1 bg-gray-50 rounded-lg p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-xs font-semibold text-gray-500 uppercase">{{ $tc['label'] }}</span>
                                <p class="text-sm font-medium text-gray-900 mt-0.5">{{ $interaction->subject }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-xs text-gray-500">{{ $interaction->occurred_at->format('d/m/Y') }}</p>
                                @if($interaction->user)
                                <p class="text-xs text-gray-400">{{ $interaction->user->name }}</p>
                                @endif
                            </div>
                        </div>
                        @if($interaction->notes)
                        <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap">{{ $interaction->notes }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ================================================================
         Recent invoices
    ================================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Factures récentes
                @if($client->invoices_count > 0)
                <span class="ml-1 text-xs font-normal text-gray-400">({{ $client->invoices_count }} au total)</span>
                @endif
            </h2>
            <a href="{{ route('ventes.factures.index', ['client_id' => $client->id]) }}"
               class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                Voir toutes →
            </a>
        </div>

        @if($client->invoices->count() === 0)
        <div class="text-center py-8 text-gray-400 text-sm">
            Aucune facture pour ce client.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                        <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Montant TTC</th>
                        <th class="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($client->invoices as $invoice)
                    @php
                        $statusMap = [
                            'brouillon'           => ['label' => 'Brouillon',     'class' => 'bg-gray-100 text-gray-600'],
                            'emise'               => ['label' => 'Émise',         'class' => 'bg-blue-100 text-blue-700'],
                            'envoyee'             => ['label' => 'Envoyée',       'class' => 'bg-indigo-100 text-indigo-700'],
                            'partiellement_payee' => ['label' => 'Part. payée',   'class' => 'bg-orange-100 text-orange-700'],
                            'payee'               => ['label' => 'Payée',         'class' => 'bg-green-100 text-green-700'],
                            'en_retard'           => ['label' => 'En retard',     'class' => 'bg-red-100 text-red-700'],
                            'annulee'             => ['label' => 'Annulée',       'class' => 'bg-gray-100 text-gray-500'],
                        ];
                        $st = $statusMap[$invoice->status] ?? ['label' => $invoice->status, 'class' => 'bg-gray-100 text-gray-600'];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5 font-mono">
                            <a href="{{ route('ventes.factures.show', $invoice) }}"
                               class="text-indigo-600 hover:text-indigo-800 font-semibold">
                                {{ $invoice->number ?? '—' }}
                            </a>
                        </td>
                        <td class="px-3 py-2.5 text-gray-600">{{ $invoice->issued_at?->format('d/m/Y') ?? $invoice->created_at->format('d/m/Y') }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-gray-900">
                            {{ number_format($invoice->total_ttc ?? 0, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $st['class'] }}">
                                {{ $st['label'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ================================================================
         Pièces jointes
    ================================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <x-attachments.manager model="Client" :id="$client->id" />
    </div>

</div>

{{-- ================================================================
     Interaction Modal (Alpine.js x-show)
================================================================ --}}
<div x-show="showInteractionModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     @keydown.escape.window="showInteractionModal = false">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black bg-opacity-40"
         @click="showInteractionModal = false"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Modal panel --}}
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">Nouvelle interaction</h3>
            <button type="button" @click="showInteractionModal = false"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form action="{{ route('clients.interactions.store', $client) }}" method="POST" class="p-5 space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="int_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Type <span class="text-red-500">*</span>
                    </label>
                    <select id="int_type" name="type"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="appel">Appel téléphonique</option>
                        <option value="email">Email</option>
                        <option value="rdv">Rendez-vous</option>
                        <option value="visite">Visite</option>
                        <option value="autre">Autre</option>
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="int_occurred_at" class="block text-sm font-medium text-gray-700 mb-1">
                        Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="int_occurred_at" name="occurred_at"
                           value="{{ old('occurred_at', now()->format('Y-m-d')) }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('occurred_at')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="int_subject" class="block text-sm font-medium text-gray-700 mb-1">
                    Sujet <span class="text-red-500">*</span>
                </label>
                <input type="text" id="int_subject" name="subject"
                       value="{{ old('subject') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="Objet de l'interaction...">
                @error('subject')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="int_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="int_notes" name="notes" rows="3"
                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                          placeholder="Compte-rendu, points discutés...">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" @click="showInteractionModal = false"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    Annuler
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

</div>

@push('scripts')
<script>
    // Auto-open interaction modal if there are validation errors for interaction fields
    @if($errors->has('type') || $errors->has('subject') || $errors->has('occurred_at') || $errors->has('notes'))
    document.addEventListener('alpine:init', () => {
        document.querySelector('[x-data]').__x.$data.showInteractionModal = true;
    });
    @endif
</script>
@endpush

@endsection
