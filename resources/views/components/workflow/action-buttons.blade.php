{{--
    Composant : boutons d'action du workflow de validation.

    Props :
      $document     : le modèle (Quote, Order, DeliveryNote, Invoice, CreditNote)
      $submitRoute  : nom de route pour 'submit'     (ex. 'ventes.devis.submit')
      $validateRoute: nom de route pour 'validate-internal'
      $rejectRoute  : nom de route pour 'reject-internal'
      $cancelRoute  : nom de route pour 'cancel-internal'
      $routeParam   : paramètre de route (ex. $quote->id)

    Affiche uniquement les boutons correspondant au statut actuel + les permissions.
--}}
@props([
    'document',
    'submitRoute'   => null,
    'validateRoute' => null,
    'rejectRoute'   => null,
    'cancelRoute'   => null,
    'routeParam',
])

<div class="flex flex-wrap gap-2 items-center" x-data="workflowActions()">

    {{-- SOUMETTRE À VALIDATION (brouillon → en_attente_validation) --}}
    @if($document->isSubmittable() && $submitRoute && auth()->user()->can('sales.submit'))
        <form method="POST" action="{{ route($submitRoute, $routeParam) }}">
            @csrf
            <button type="submit"
                    onclick="return confirm('Soumettre ce document à validation interne ?')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-yellow-500 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                </svg>
                Soumettre
            </button>
        </form>
    @endif

    {{-- VALIDER (en_attente_validation → statut validé) --}}
    @if($document->isValidatable() && $validateRoute && auth()->user()->can('sales.validate'))
        <form method="POST" action="{{ route($validateRoute, $routeParam) }}">
            @csrf
            <button type="submit"
                    onclick="return confirm('Valider ce document ?')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-green-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Valider
            </button>
        </form>
    @endif

    {{-- REFUSER avec motif (en_attente_validation → brouillon) --}}
    @if($document->isRejectable() && $rejectRoute && auth()->user()->can('sales.reject'))
        <button type="button"
                x-on:click="$dispatch('open-modal', 'reject-modal-{{ $routeParam }}')"
                class="inline-flex items-center gap-1.5 rounded-md bg-orange-500 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>
            Refuser
        </button>

        {{-- Modal motif refus --}}
        <x-modal name="reject-modal-{{ $routeParam }}" :show="false">
            <form method="POST" action="{{ route($rejectRoute, $routeParam) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Refuser le document</h2>
                <p class="text-sm text-gray-500 mb-4">Le document sera renvoyé en brouillon. Le motif est obligatoire pour la traçabilité.</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motif de refus <span class="text-red-500">*</span></label>
                <textarea name="motif" rows="3" required minlength="5" maxlength="500"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm"
                          placeholder="Ex: Montant incorrect, pièces manquantes..."></textarea>
                <div class="mt-4 flex gap-3 justify-end">
                    <button type="button" x-on:click="$dispatch('close')"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit"
                            class="rounded-md bg-orange-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-700">
                        Confirmer le refus
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- ANNULER avec motif --}}
    @if($document->isCancellable() && $cancelRoute && auth()->user()->can('sales.cancel'))
        <button type="button"
                x-on:click="$dispatch('open-modal', 'cancel-modal-{{ $routeParam }}')"
                class="inline-flex items-center gap-1.5 rounded-md bg-red-500 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Annuler doc.
        </button>

        {{-- Modal motif annulation --}}
        <x-modal name="cancel-modal-{{ $routeParam }}" :show="false">
            <form method="POST" action="{{ route($cancelRoute, $routeParam) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-semibold text-gray-900 mb-1">Annuler le document</h2>
                <p class="text-sm text-gray-500 mb-4">Cette action est irréversible. Le motif est obligatoire pour l'audit.</p>
                <label class="block text-sm font-medium text-gray-700 mb-1">Motif d'annulation <span class="text-red-500">*</span></label>
                <textarea name="motif" rows="3" required minlength="5" maxlength="500"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                          placeholder="Ex: Doublon, erreur de saisie, annulation client..."></textarea>
                <div class="mt-4 flex gap-3 justify-end">
                    <button type="button" x-on:click="$dispatch('close')"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Retour
                    </button>
                    <button type="submit"
                            class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                        Confirmer l'annulation
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

</div>

@once
@push('scripts')
<script>
function workflowActions() {
    return {};
}
</script>
@endpush
@endonce
