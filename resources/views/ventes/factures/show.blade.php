@extends('layouts.erp')
@section('title', 'Facture '.$invoice->number)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <a href="{{ route('ventes.factures.index') }}" class="hover:text-gray-700">Factures</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">{{ $invoice->number }}</span>
@endsection

@section('content')
<div class="space-y-6"
     x-data="{
        showCancelModal: false,
        cancelReason: '',
        get canSubmitCancel() { return this.cancelReason.trim().length >= 5; }
     }">

    {{-- Workflow bar --}}
    @include('partials._workflow-ventes', [
        'currentStep'  => in_array($invoice->status, ['payee']) ? 'paiement' : 'facture',
        'quote'        => $invoice->order?->quote ?? null,
        'order'        => $invoice->order ?? null,
        'deliveryNote' => $invoice->deliveryNote ?? null,
        'invoice'      => $invoice,
    ])

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $invoice->number }}</h1>
                @php
                    $statusBadges = [
                        'brouillon'           => 'badge-gray',
                        'emise'               => 'badge-blue',
                        'envoyee'             => 'badge-indigo',
                        'partiellement_payee' => 'badge-orange',
                        'payee'               => 'badge-green',
                        'en_retard'           => 'badge-red',
                        'annulee'             => 'badge-red',
                    ];
                    $statusLabels = [
                        'brouillon'           => 'Brouillon',
                        'emise'               => 'Émise',
                        'envoyee'             => 'Envoyée',
                        'partiellement_payee' => 'Partiellement payée',
                        'payee'               => 'Payée',
                        'en_retard'           => 'En retard',
                        'annulee'             => 'Annulée',
                    ];
                    $isOverdue = $invoice->due_at && $invoice->due_at->isPast()
                        && !in_array($invoice->status, ['payee', 'annulee']);
                @endphp
                <span class="badge {{ $statusBadges[$invoice->status] ?? 'badge-gray' }}">
                    {{ $statusLabels[$invoice->status] ?? $invoice->status }}
                </span>
                @if($isOverdue)
                    <span class="badge bg-red-600 text-white font-bold">EN RETARD</span>
                @endif
                <span class="text-gray-500 text-sm">{{ $invoice->client?->name }}</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- PDF --}}
                <a href="{{ route('ventes.factures.pdf', $invoice) }}" class="btn btn-secondary" title="Télécharger le PDF">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Télécharger PDF
                </a>
                <a href="{{ route('ventes.factures.pdf', $invoice) }}?preview=1" target="_blank" class="btn btn-secondary" title="Aperçu PDF">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Aperçu
                </a>

                {{-- Valider --}}
                @if($invoice->status === 'brouillon')
                <form action="{{ route('ventes.factures.validate', $invoice) }}" method="POST"
                      onsubmit="return confirm('Valider cette facture ? Elle ne pourra plus être modifiée.')">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Valider
                    </button>
                </form>
                @endif

                {{-- Envoyer par email --}}
                @if($invoice->client?->email && $invoice->status !== 'brouillon')
                <form action="{{ route('ventes.factures.send-email', $invoice) }}" method="POST"
                      onsubmit="return confirm('Envoyer la facture à {{ addslashes($invoice->client->email) }} ?')">
                    @csrf
                    <button type="submit" class="btn btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Email
                    </button>
                </form>
                @endif

                {{-- Enregistrer un paiement --}}
                @if(!in_array($invoice->status, ['brouillon', 'payee', 'annulee']))
                <a href="{{ route('tresorerie.encaissements.create', ['client_id' => $invoice->client_id]) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Encaisser
                </a>
                @endif

                {{-- Créer un avoir --}}
                @if(!in_array($invoice->status, ['brouillon', 'annulee']))
                <a href="{{ route('ventes.avoirs.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-purple">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    Avoir
                </a>
                @endif

                {{-- Modifier --}}
                @if($invoice->status === 'brouillon')
                <a href="{{ route('ventes.factures.edit', $invoice) }}" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                @endif

                {{-- [UX-2] Convertir une proforma en facture standard --}}
                @if($invoice->type === 'proforma' && in_array($invoice->status, ['emise', 'envoyee']))
                <form action="{{ route('ventes.factures.convert-proforma', $invoice) }}" method="POST"
                      onsubmit="return confirm('Convertir cette proforma en facture standard ?\n\nUne nouvelle facture (compta + stock) sera générée. La proforma sera marquée annulée.')">
                    @csrf
                    <button type="submit" class="btn btn-teal" title="Convertir en facture standard avec impact compta + stock">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        Convertir en facture
                    </button>
                </form>
                @endif

                {{-- [UX-1] Annuler avec motif (contre-passation) --}}
                @if(in_array($invoice->status, ['emise', 'envoyee', 'en_retard']) && $invoice->paid_amount == 0)
                <button type="button" @click="showCancelModal = true; cancelReason = ''"
                        class="inline-flex items-center gap-2 px-3 py-2 border border-red-300 text-red-600 hover:bg-red-50 rounded-lg text-sm font-medium transition-colors"
                        title="Annuler la facture avec contre-passation comptable">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Annuler
                </button>
                @endif

                <a href="{{ route('ventes.factures.index') }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>
    </div>

    {{-- En-tête document avec logo --}}
    @php
        $statusLabelsLh = [
            'brouillon'           => ['label' => 'Brouillon',            'class' => 'bg-gray-100 text-gray-700'],
            'emise'               => ['label' => 'Émise',                'class' => 'bg-blue-100 text-blue-700'],
            'envoyee'             => ['label' => 'Envoyée',              'class' => 'bg-indigo-100 text-indigo-700'],
            'partiellement_payee' => ['label' => 'Part. payée',          'class' => 'bg-orange-100 text-orange-700'],
            'payee'               => ['label' => 'Payée',                'class' => 'bg-green-100 text-green-700'],
            'en_retard'           => ['label' => 'En retard',            'class' => 'bg-red-100 text-red-700'],
            'annulee'             => ['label' => 'Annulée',              'class' => 'bg-red-100 text-red-700'],
        ];
    @endphp
    @include('partials._doc-letterhead', [
        'docType'   => 'FACTURE',
        'docNumber' => $invoice->number,
        'docDate'   => $invoice->issued_at?->format('d/m/Y') ?? '—',
        'docStatus' => $statusLabelsLh[$invoice->status] ?? null,
        'docExtra'  => array_values(array_filter([
            $invoice->due_at ? ['label' => 'Échéance', 'value' => $invoice->due_at->format('d/m/Y')] : null,
            $invoice->client ? ['label' => 'Client',   'value' => $invoice->client->name]              : null,
        ])),
    ])

    {{-- 2 colonnes: info + totaux --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h2 class="text-base font-semibold text-gray-900">Informations</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Client</dt>
                    <dd class="mt-0.5 font-semibold text-gray-900">{{ $invoice->client?->name ?? '—' }}</dd>
                    @if($invoice->client?->phone)<dd class="text-gray-500 text-xs">{{ $invoice->client->phone }}</dd>@endif
                    @if($invoice->client?->email)<dd class="text-gray-500 text-xs">{{ $invoice->client->email }}</dd>@endif
                    @php
                        $addr = $invoice->client?->addresses
                            ?->firstWhere('type', 'facturation')
                            ?? $invoice->client?->addresses?->firstWhere('is_default', true)
                            ?? $invoice->client?->addresses?->first();
                    @endphp
                    @if($addr)
                    <dd class="text-gray-500 text-xs mt-1 leading-snug">
                        @if($addr->address)<span>{{ $addr->address }}</span><br>@endif
                        @if($addr->city || $addr->country)
                            <span>{{ implode(', ', array_filter([$addr->city, $addr->country])) }}</span>
                        @endif
                    </dd>
                    @elseif($invoice->client?->address || $invoice->client?->city)
                    <dd class="text-gray-500 text-xs mt-1 leading-snug">
                        @if($invoice->client->address)<span>{{ $invoice->client->address }}</span><br>@endif
                        @if($invoice->client->city || $invoice->client->country)
                            <span>{{ implode(', ', array_filter([$invoice->client->city, $invoice->client->country])) }}</span>
                        @endif
                    </dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Numéro</dt>
                    <dd class="mt-0.5 font-mono font-semibold text-gray-900">{{ $invoice->number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'émission</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'échéance</dt>
                    <dd class="mt-0.5 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-700' }}">
                        {{ $invoice->due_at?->format('d/m/Y') ?? '—' }}
                    </dd>
                </div>
                @if($invoice->payment_terms)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Conditions paiement</dt>
                    <dd class="mt-0.5 text-gray-700">{{ $invoice->payment_terms }}</dd>
                </div>
                @endif
                @if($invoice->order)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Commande d'origine</dt>
                    <dd class="mt-0.5">
                        <a href="{{ route('ventes.commandes.show', $invoice->order) }}" class="text-blue-600 hover:underline font-mono">{{ $invoice->order->number }}</a>
                    </dd>
                </div>
                @endif
                @if($invoice->notes)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</dt>
                    <dd class="mt-0.5 text-gray-700 whitespace-pre-wrap text-xs">{{ $invoice->notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Totaux --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 h-fit">
            <h2 class="text-base font-semibold text-gray-900">Récapitulatif</h2>
            <div class="flex justify-between text-sm text-gray-600">
                <span>Montant HT</span>
                <span class="font-medium tabular-nums">{{ number_format($invoice->subtotal_ht, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600">
                <span>TVA</span>
                <span class="font-medium tabular-nums">{{ number_format($invoice->total_tax, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($invoice->global_discount_amount > 0)
            <div class="flex justify-between text-sm text-gray-600">
                <span>Remise globale</span>
                <span class="font-medium tabular-nums text-orange-600">— {{ number_format($invoice->global_discount_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="border-t border-gray-200 pt-3 flex justify-between">
                <span class="text-sm font-bold text-gray-900">Montant TTC</span>
                <span class="text-sm font-bold text-gray-900 tabular-nums">{{ number_format($invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>

            {{-- Retenues à la source --}}
            @if(!empty($invoice->withholding_details))
                @foreach($invoice->withholding_details as $w)
                <div class="flex justify-between text-sm text-amber-700">
                    <span>Retenue {{ $w['short_name'] ?? $w['name'] }} {{ number_format($w['rate'], 2, ',', '') }}%</span>
                    <span class="font-medium tabular-nums">— {{ number_format($w['amount'], 0, ',', ' ') }} FCFA</span>
                </div>
                @endforeach
            @endif

            {{-- Net à payer --}}
            <div class="border-t-2 border-indigo-200 pt-3 flex justify-between">
                <span class="text-base font-bold text-gray-900">NET À PAYER</span>
                <span class="text-base font-bold text-indigo-700 tabular-nums">{{ number_format($invoice->net_to_pay ?: $invoice->total_ttc, 0, ',', ' ') }} FCFA</span>
            </div>

            @if($invoice->paid_amount > 0)
            <div class="flex justify-between text-sm text-gray-600 border-t border-gray-100 pt-2">
                <span>Déjà payé</span>
                <span class="font-medium tabular-nums text-green-600">{{ number_format($invoice->paid_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            @if($invoice->remaining_amount > 0)
            <div class="flex justify-between text-sm border-t border-gray-100 pt-2">
                <span class="font-bold text-red-700">Reste à payer</span>
                <span class="font-bold tabular-nums text-red-700">{{ number_format($invoice->remaining_amount, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Lignes --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Lignes de facture</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Description</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Qté</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Prix Unit.</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Remise%</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">TVA%</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total HT</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total TTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoice->items as $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 text-gray-900">{{ $item->description }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums">{{ number_format($item->quantity, 2, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums">{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums hidden md:table-cell">{{ ($item->discount_percent ?? 0) > 0 ? number_format($item->discount_percent, 2, ',', ' ').'%' : '—' }}</td>
                        <td class="px-4 py-3 text-right text-gray-600 tabular-nums">{{ number_format($item->tax_rate_value, 2, ',', ' ') }}%</td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums font-medium">{{ number_format($item->line_total_ht, 0, ',', ' ') }} FCFA</td>
                        <td class="px-4 py-3 text-right text-gray-900 tabular-nums font-semibold">{{ number_format($item->line_total_ttc, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Aucune ligne.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Paiements reçus --}}
    @if($invoice->payments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Paiements reçus</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Mode</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant alloué</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Référence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($invoice->payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-700">{{ $payment->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $payment->paymentMethod?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-green-600">
                            {{ number_format($payment->pivot->amount ?? $payment->amount, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">{{ $payment->reference ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Avoirs liés --}}
    @if($invoice->creditNotes->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Avoirs liés</h2>
            <a href="{{ route('ventes.avoirs.create', ['invoice_id' => $invoice->id]) }}"
               class="text-xs text-purple-600 hover:text-purple-800 font-medium border border-purple-200 hover:bg-purple-50 px-3 py-1.5 rounded-lg transition-colors">
                + Nouvel avoir
            </a>
        </div>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Numéro</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Motif</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Montant TTC</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($invoice->creditNotes as $cn)
                @php
                    $cnBadges = ['brouillon' => 'bg-gray-100 text-gray-600', 'valide' => 'bg-purple-100 text-purple-700', 'applique' => 'bg-green-100 text-green-700', 'annule' => 'bg-red-100 text-red-600'];
                    $cnLabels = ['brouillon' => 'Brouillon', 'valide' => 'Validé', 'applique' => 'Appliqué', 'annule' => 'Annulé'];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono font-semibold text-purple-700">
                        <a href="{{ route('ventes.avoirs.show', $cn) }}" class="hover:text-purple-900">{{ $cn->number }}</a>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $cn->issued_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">{{ $cn->reason ?? '—' }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-purple-700 font-semibold">{{ number_format($cn->total_ttc, 0, ',', ' ') }} FCFA</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $cnBadges[$cn->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $cnLabels[$cn->status] ?? $cn->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('ventes.avoirs.show', $cn) }}" class="p-1.5 text-gray-400 hover:text-purple-600 hover:bg-purple-50 rounded" title="Voir">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- [UX-4] Historique d'audit --}}
    @if(isset($audits) && $audits->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Historique
            </h2>
            <span class="text-xs text-gray-400">{{ $audits->count() }} opération(s)</span>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($audits as $audit)
            @php
                $actionColors = [
                    'created'        => 'bg-blue-100 text-blue-700',
                    'updated'        => 'bg-gray-100 text-gray-700',
                    'validated'      => 'bg-emerald-100 text-emerald-700',
                    'sent'           => 'bg-indigo-100 text-indigo-700',
                    'cancelled'      => 'bg-red-100 text-red-700',
                    'paid'           => 'bg-green-100 text-green-700',
                    'partially_paid' => 'bg-amber-100 text-amber-700',
                    'overdue'        => 'bg-orange-100 text-orange-700',
                    'deleted'        => 'bg-red-100 text-red-700',
                    'restored'       => 'bg-purple-100 text-purple-700',
                ];
                $actionLabels = [
                    'created'        => 'Création',
                    'updated'        => 'Modification',
                    'validated'      => 'Validation',
                    'sent'           => 'Envoi par email',
                    'cancelled'      => 'Annulation',
                    'paid'           => 'Payée',
                    'partially_paid' => 'Paiement partiel',
                    'overdue'        => 'Passage en retard',
                    'deleted'        => 'Suppression',
                    'restored'       => 'Restauration',
                ];
                $cls = $actionColors[$audit->action] ?? 'bg-gray-100 text-gray-700';
                $label = $actionLabels[$audit->action] ?? $audit->action;
            @endphp
            <div class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50/50">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $cls }} flex-shrink-0">
                    {{ $label }}
                </span>
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-gray-600">
                        <span class="font-medium text-gray-800">{{ $audit->user_name ?? 'Système' }}</span>
                        <span class="text-gray-400">·</span>
                        <span>{{ $audit->created_at->format('d/m/Y H:i:s') }}</span>
                    </div>
                    @if($audit->action === 'updated' && $audit->new_values)
                    @php
                        $diffFields = array_diff_key($audit->new_values, ['number' => 1]);
                    @endphp
                    @if(!empty($diffFields))
                    <div class="mt-1 text-xs text-gray-500">
                        @foreach($diffFields as $field => $newVal)
                            @php
                                $oldVal = $audit->old_values[$field] ?? null;
                                if (is_array($newVal) || is_array($oldVal)) continue;
                            @endphp
                            <div class="flex items-baseline gap-1.5">
                                <span class="font-mono font-medium text-gray-700">{{ $field }}</span>
                                <span class="text-gray-400 line-through">{{ $oldVal !== null ? \Illuminate\Support\Str::limit((string) $oldVal, 50) : '∅' }}</span>
                                <span class="text-gray-400">→</span>
                                <span class="text-gray-800">{{ \Illuminate\Support\Str::limit((string) $newVal, 50) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @endif
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- [UX-1] Modale d'annulation avec motif obligatoire --}}
    <div x-show="showCancelModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="showCancelModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.outside="showCancelModal = false">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Annuler la facture {{ $invoice->number }}
                </h3>
            </div>
            <form action="{{ route('ventes.factures.cancel', $invoice) }}" method="POST" data-turbo="false">
                @csrf
                <div class="px-6 py-5 space-y-3">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                        <strong>⚠ Conséquences :</strong>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            <li>Contre-passation comptable automatique</li>
                            <li>Statut passe à « Annulée »</li>
                            <li>La commande parent (si elle existe) revient à son état précédent</li>
                            <li>Action irréversible — pour une correction partielle, créez plutôt un avoir</li>
                        </ul>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Motif de l'annulation <span class="text-red-500">*</span>
                        </label>
                        <textarea name="reason" x-model="cancelReason" rows="3" required minlength="5" maxlength="500"
                                  placeholder="Ex : Erreur de saisie client / Annulation commerciale demandée / …"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"></textarea>
                        <p class="text-[10px] text-gray-400 mt-1">Conservé dans l'historique d'audit comptable</p>
                    </div>
                </div>
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">
                    <button type="button" @click="showCancelModal = false"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg hover:bg-white">
                        Fermer
                    </button>
                    <button type="submit" :disabled="!canSubmitCancel"
                            class="bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                        Confirmer l'annulation
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
