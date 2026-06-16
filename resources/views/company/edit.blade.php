@extends('layouts.erp')
@section('title', 'Paramétrage société')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Accueil</a>
    <span class="mx-1">/</span>
    <span class="text-gray-900 font-medium">Paramétrage société</span>
@endsection

@section('content')
<div x-data="{ tab: '{{ old('_tab', 'general') }}' }" class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Paramétrage de la société</h1>
            <p class="text-sm text-gray-500 mt-1">Configurez les informations de votre entreprise</p>
        </div>
        @if($company->logo)
        <img src="{{ url(Storage::url($company->logo)) }}" alt="Logo" class="h-14 rounded-lg object-contain border border-gray-200 p-1">
        @endif
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6 overflow-x-auto">
            @foreach([
                ['key' => 'general',   'label' => 'Général'],
                ['key' => 'legal',     'label' => 'Légal & Fiscal'],
                ['key' => 'documents', 'label' => 'Documents'],
                ['key' => 'banque',    'label' => 'Banque'],
            ] as $t)
            <button @click="tab = '{{ $t['key'] }}'"
                    :class="tab === '{{ $t['key'] }}' ? 'border-blue-600 text-blue-700 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    class="whitespace-nowrap py-3 px-1 border-b-2 text-sm transition-colors">
                {{ $t['label'] }}
            </button>
            @endforeach
        </nav>
    </div>

    <!-- Tab: Général -->
    <div x-show="tab === 'general'" class="bg-white rounded-xl border border-gray-200">
        <form action="{{ route('company.update.general') }}" method="POST" enctype="multipart/form-data" data-turbo="false" class="p-6 space-y-6">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="general">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Raison sociale <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $company->name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nom commercial</label>
                    <input type="text" name="trade_name" value="{{ old('trade_name', $company->trade_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slogan</label>
                    <input type="text" name="slogan" value="{{ old('slogan', $company->slogan) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone principal</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone secondaire</label>
                    <input type="text" name="phone2" value="{{ old('phone2', $company->phone2) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $company->email) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
                    <input type="url" name="website" value="{{ old('website', $company->website) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                    <input type="text" name="address" value="{{ old('address', $company->address) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                    <input type="text" name="city" value="{{ old('city', $company->city) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                    <input type="text" name="country" value="{{ old('country', $company->country ?? 'Burkina Faso') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo</label>
                    <input type="file" name="logo" accept="image/*"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm file:mr-3 file:py-1 file:px-3 file:border-0 file:bg-blue-50 file:text-blue-700 file:rounded">
                    @if($company->logo)
                    <p class="text-xs text-gray-500 mt-1">Logo actuel — laisser vide pour conserver</p>
                    @endif
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Légal -->
    <div x-show="tab === 'legal'" class="bg-white rounded-xl border border-gray-200">
        <form action="{{ route('company.update.legal') }}" method="POST" class="p-6 space-y-6">
            @csrf @method('PUT')
            <input type="hidden" name="_tab" value="legal">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Forme juridique</label>
                    <select name="legal_form" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Sélectionner --</option>
                        @foreach(['SARL', 'SA', 'SAS', 'EI', 'SUARL', 'GIE', 'Association'] as $form)
                        <option value="{{ $form }}" {{ old('legal_form', $company->legal_form) === $form ? 'selected' : '' }}>{{ $form }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">RCCM</label>
                    <input type="text" name="rccm" value="{{ old('rccm', $company->rccm) }}" placeholder="BF-OUA-2020-B-12345"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IFU / Numéro fiscal</label>
                    <input type="text" name="ifu" value="{{ old('ifu', $company->ifu) }}" placeholder="00123456789"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NIF</label>
                    <input type="text" name="nif" value="{{ old('nif', $company->nif) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capital social (FCFA)</label>
                    <input type="number" name="share_capital" value="{{ old('share_capital', $company->share_capital) }}" min="0" step="100000"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assujetti à la TVA</label>
                    <div class="flex items-center gap-4 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="is_vat_subject" value="1" {{ old('is_vat_subject', $company->is_vat_subject) ? 'checked' : '' }}
                                   class="text-blue-600 focus:ring-blue-500">
                            <span class="text-sm">Oui</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="is_vat_subject" value="0" {{ !old('is_vat_subject', $company->is_vat_subject) ? 'checked' : '' }}
                                   class="text-blue-600 focus:ring-blue-500">
                            <span class="text-sm">Non</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Taux TVA par défaut (%)</label>
                    <input type="number" name="vat_number" value="{{ old('vat_number', $company->vat_number ?? 18) }}" min="0" max="100" step="0.5"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Documents -->
    <div x-show="tab === 'documents'" class="bg-white rounded-xl border border-gray-200">
        <form action="{{ route('company.update.documents') }}" method="POST" enctype="multipart/form-data" data-turbo="false" class="p-6 space-y-6">
            @csrf @method('PUT')
            @php $ds = $company->documentSetting; @endphp
            <input type="hidden" name="_tab" value="documents">

            {{-- Mise en page --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Mise en page</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div x-data="{ color: '{{ old('primary_color', $ds?->primary_color ?? '#1e40af') }}' }">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Couleur principale</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="primary_color" x-model="color"
                                   class="w-12 h-10 border border-gray-300 rounded cursor-pointer">
                            <input type="text" x-model="color" placeholder="#1e40af"
                                   class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Police</label>
                        <select name="font_family" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            @foreach(['DejaVu Sans' => 'DejaVu Sans (défaut)', 'DejaVu Serif' => 'DejaVu Serif', 'Helvetica' => 'Helvetica', 'Times New Roman' => 'Times New Roman'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('font_family', $ds?->font_family ?? 'DejaVu Sans') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Format page</label>
                        <select name="page_size" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            @foreach(['A4', 'A5', 'Letter'] as $size)
                            <option value="{{ $size }}" {{ old('page_size', $ds?->page_size ?? 'A4') === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Orientation</label>
                        <select name="orientation" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="portrait" {{ old('orientation', $ds?->orientation ?? 'portrait') === 'portrait' ? 'selected' : '' }}>Portrait</option>
                            <option value="landscape" {{ old('orientation', $ds?->orientation) === 'landscape' ? 'selected' : '' }}>Paysage</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input type="hidden" name="show_logo" value="0">
                        <input type="checkbox" id="show_logo" name="show_logo" value="1" {{ old('show_logo', $ds?->show_logo ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                        <label for="show_logo" class="text-sm font-medium text-gray-700 cursor-pointer">Afficher le logo</label>
                    </div>

                    <div x-data="{ wm: {{ old('show_watermark', $ds?->show_watermark ?? false) ? 'true' : 'false' }} }" class="flex flex-col gap-2">
                        <div class="flex items-center gap-3 pt-6">
                            <input type="hidden" name="show_watermark" value="0">
                            <input type="checkbox" id="show_watermark" name="show_watermark" value="1" x-model="wm"
                                   class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                            <label for="show_watermark" class="text-sm font-medium text-gray-700 cursor-pointer">Afficher un filigrane</label>
                        </div>
                        <input type="text" name="watermark_text" x-show="wm" value="{{ old('watermark_text', $ds?->watermark_text ?? 'CONFIDENTIEL') }}"
                               placeholder="Texte du filigrane"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            {{-- Colonnes affichées sur les documents --}}
            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Colonnes affichées sur les documents</h3>
                @php
                    $defaultCols = ['reference','description','quantity','unit_price','discount','tax','total_ht','total_ttc'];
                    $savedCols   = old('product_columns', $ds?->product_columns ?? $defaultCols);
                    $colLabels   = [
                        'reference'   => 'Référence',
                        'description' => 'Description',
                        'longueur'    => 'Longueur',
                        'epaisseur'   => 'Épaisseur',
                        'quantity'    => 'Quantité',
                        'unit_price'  => 'Prix unitaire',
                        'discount'    => 'Remise %',
                        'tax'         => 'TVA %',
                        'total_ht'    => 'Total HT',
                        'total_ttc'   => 'Total TTC',
                    ];
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($colLabels as $colKey => $colLabel)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="product_columns[]" value="{{ $colKey }}"
                               {{ in_array($colKey, (array)$savedCols) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                        <span class="text-sm text-gray-700">{{ $colLabel }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Textes --}}
            <div class="border-t border-gray-100 pt-5 grid grid-cols-1 gap-4">
                <h3 class="text-sm font-semibold text-gray-700">Textes</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pied de page personnalisé</label>
                    <textarea name="footer_text" rows="2"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">{{ old('footer_text', $ds?->footer_text) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conditions générales de vente (CGV)</label>
                    <textarea name="terms_conditions" rows="4"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">{{ old('terms_conditions', $ds?->terms_conditions) }}</textarea>
                </div>
            </div>

            {{-- Signature & cachet --}}
            <div class="border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Signature & cachet</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nom signataire</label>
                        <input type="text" name="signature_name" value="{{ old('signature_name', $ds?->signature_name) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titre du signataire</label>
                        <input type="text" name="signature_title" value="{{ old('signature_title', $ds?->signature_title) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image de signature</label>
                        <input type="file" name="signature_image" accept="image/*"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm file:mr-3 file:py-1 file:px-3 file:border-0 file:bg-blue-50 file:text-blue-700 file:rounded">
                        @if($ds?->signature_image)
                        <p class="text-xs text-gray-400 mt-1">Signature existante — laisser vide pour conserver</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cachet (tampon)</label>
                        <input type="file" name="stamp_image" accept="image/*"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm file:mr-3 file:py-1 file:px-3 file:border-0 file:bg-blue-50 file:text-blue-700 file:rounded">
                        @if($ds?->stamp_image)
                        <p class="text-xs text-gray-400 mt-1">Cachet existant — laisser vide pour conserver</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Banque -->
    <div x-show="tab === 'banque'" x-data="{ showForm: false, editId: null, editData: {} }" class="space-y-4">

        <!-- Add button -->
        <div class="flex justify-end">
            <button @click="showForm = true; editId = null; editData = {}"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter un compte
            </button>
        </div>

        <!-- Form -->
        <div x-show="showForm" x-cloak class="bg-white rounded-xl border border-blue-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4" x-text="editId ? 'Modifier le compte' : 'Nouveau compte bancaire'"></h3>
            {{--
                URL : on utilise toujours route() côté Blade (respecte le base path /iboa/public).
                Pour l'update on injecte editId dans l'URL via x-bind:action.
                Le _method=PUT est dans un <template x-if> → retiré du DOM en mode création (sinon Laravel l'interprète toujours).
            --}}
            <form x-bind:action="editId
                    ? '{{ url('parametrage/banque') }}/' + editId
                    : '{{ route('company.bank.store') }}'"
                  method="POST" data-turbo="false"
                  class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <template x-if="editId">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Banque <span class="text-red-500">*</span></label>
                    <input type="text" name="bank_name" :value="editData.bank_name" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Titulaire <span class="text-red-500">*</span></label>
                    <input type="text" name="account_holder" :value="editData.account_holder" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de compte <span class="text-red-500">*</span></label>
                    <input type="text" name="account_number" :value="editData.account_number" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Agence</label>
                    <input type="text" name="branch" :value="editData.branch"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IBAN</label>
                    <input type="text" name="iban" :value="editData.iban"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">SWIFT/BIC</label>
                    <input type="text" name="swift_bic" :value="editData.swift_bic"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-center gap-3">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" id="is_default" name="is_default" value="1" :checked="editData.is_default"
                           class="w-4 h-4 text-blue-600 rounded">
                    <label for="is_default" class="text-sm font-medium text-gray-700">Compte principal</label>
                </div>
                {{-- [PONT BANCAIRE] Crée le compte de trésorerie opérationnel associé --}}
                <div class="md:col-span-2 flex items-start gap-3 bg-indigo-50 border border-indigo-100 rounded-lg p-3">
                    <input type="hidden" name="sync_treasury" value="0">
                    <input type="checkbox" id="sync_treasury" name="sync_treasury" value="1" :checked="editData.cash_account_id"
                           class="w-4 h-4 mt-0.5 text-indigo-600 rounded">
                    <label for="sync_treasury" class="text-sm text-gray-700">
                        <span class="font-medium text-indigo-700">Créer le compte de trésorerie associé</span><br>
                        <span class="text-xs text-gray-500">Rend ce compte opérationnel (rapprochement bancaire, soldes, transactions) — évite la double saisie.</span>
                    </label>
                </div>
                <div class="md:col-span-2 flex gap-3 justify-end">
                    <button type="button" @click="showForm = false"
                            class="border border-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Existing accounts list -->
        @if($company->bankAccounts->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400 text-sm">
            Aucun compte bancaire enregistré.
        </div>
        @else
        <div class="space-y-3">
            @foreach($company->bankAccounts as $account)
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-semibold text-gray-900 text-sm">{{ $account->bank_name }}</span>
                        @if($account->is_default)
                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-medium">Principal</span>
                        @endif
                        @unless($account->is_active)
                        <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5 rounded-full">Inactif</span>
                        @endunless
                        @if($account->cash_account_id)
                        <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full font-medium" title="Compte de trésorerie opérationnel lié">⇄ Trésorerie</span>
                        @endif
                    </div>
                    <p class="text-sm text-gray-600">{{ $account->account_holder }} — {{ $account->account_number }}</p>
                    @if($account->branch)<p class="text-xs text-gray-400">{{ $account->branch }}</p>@endif
                    @if($account->iban)<p class="text-xs text-gray-400 font-mono">IBAN: {{ $account->iban }}</p>@endif
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="showForm = true; editId = {{ $account->id }}; editData = {{ $account->toJson() }}"
                            class="text-gray-400 hover:text-blue-600 p-1.5 rounded hover:bg-blue-50 transition-colors" title="Modifier">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <form action="{{ route('company.bank.destroy', $account) }}" method="POST" onsubmit="return confirm('Supprimer ce compte ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-gray-400 hover:text-red-600 p-1.5 rounded hover:bg-red-50 transition-colors" title="Supprimer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
