{{--
    Reusable client form partial.
    Variables expected:
      $client  (optional, for edit mode — Client model)
      $formAction  — POST url
      $formMethod  — 'POST' or 'PUT'
--}}

@php
    $isEdit       = isset($client) && $client->exists;
    $existingContacts = $isEdit ? $client->contacts->toArray() : [];
    $existingAddresses = $isEdit ? $client->addresses->toArray() : [];
@endphp

<div
    x-data="{
        activeTab: 'info',
        contacts: {{ Js::from($existingContacts) }},
        addresses: {{ Js::from($existingAddresses) }},

        addContact() {
            this.contacts.push({
                civility: '',
                first_name: '',
                last_name: '',
                job_title: '',
                phone: '',
                mobile: '',
                email: '',
                is_primary: false
            });
        },
        removeContact(index) {
            this.contacts.splice(index, 1);
        },

        addAddress() {
            this.addresses.push({
                type: 'livraison',
                address: '',
                city: '',
                country: 'Bénin',
                is_default: false
            });
        },
        removeAddress(index) {
            this.addresses.splice(index, 1);
        }
    }"
>

    {{-- Tab navigation --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex gap-1 overflow-x-auto">
            @foreach([
                ['id' => 'info',     'label' => 'Informations'],
                ['id' => 'legal',    'label' => 'Infos légales'],
                ['id' => 'contacts', 'label' => 'Contacts'],
                ['id' => 'addresses','label' => 'Adresses'],
            ] as $tab)
            <button type="button"
                    @click="activeTab = '{{ $tab['id'] }}'"
                    :class="activeTab === '{{ $tab['id'] }}'
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors focus:outline-none">
                {{ $tab['label'] }}
                @if($tab['id'] === 'contacts')
                    <span x-show="contacts.length > 0"
                          x-text="contacts.length"
                          class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold"></span>
                @endif
                @if($tab['id'] === 'addresses')
                    <span x-show="addresses.length > 0"
                          x-text="addresses.length"
                          class="ml-1.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold"></span>
                @endif
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ================================================================
         TAB 1 — Informations générales
    ================================================================ --}}
    <div x-show="activeTab === 'info'" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- Type de client --}}
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Type de client <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="type" value="particulier"
                               {{ old('type', $client->type ?? 'particulier') === 'particulier' ? 'checked' : '' }}
                               class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Particulier</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="type" value="entreprise"
                               {{ old('type', $client->type ?? '') === 'entreprise' ? 'checked' : '' }}
                               class="text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Entreprise</span>
                    </label>
                </div>
                @error('type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Raison sociale --}}
            <div class="md:col-span-2">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                    Raison sociale / Nom <span class="text-red-500">*</span>
                </label>
                <input type="text" id="name" name="name"
                       value="{{ old('name', $client->name ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="Ex: Société ABC ou Jean Dupont">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Code --}}
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code client</label>
                <input type="text" id="code" name="code"
                       value="{{ old('code', $client->code ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                       placeholder="Généré automatiquement">
                <p class="mt-1 text-xs text-gray-400">Laissez vide pour génération automatique (CLI-00001)</p>
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Civility --}}
            <div>
                <label for="civility" class="block text-sm font-medium text-gray-700 mb-1">Civilité</label>
                <select id="civility" name="civility"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">—</option>
                    @foreach(['M.' => 'M.', 'Mme' => 'Mme', 'Dr' => 'Dr', 'Prof' => 'Prof'] as $val => $label)
                        <option value="{{ $val }}" {{ old('civility', $client->civility ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email', $client->email ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="contact@example.com">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Phone --}}
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone fixe</label>
                <input type="text" id="phone" name="phone"
                       value="{{ old('phone', $client->phone ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="+229 21 XX XX XX">
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Mobile --}}
            <div>
                <label for="mobile" class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
                <input type="text" id="mobile" name="mobile"
                       value="{{ old('mobile', $client->mobile ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="+229 97 XX XX XX">
                @error('mobile')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Website --}}
            <div class="md:col-span-2">
                <label for="website" class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
                <input type="url" id="website" name="website"
                       value="{{ old('website', $client->website ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="https://www.example.com">
                @error('website')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notes --}}
            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
                <textarea id="notes" name="notes" rows="3"
                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                          placeholder="Informations complémentaires...">{{ old('notes', $client->notes ?? '') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </div>
    </div>

    {{-- ================================================================
         TAB 2 — Infos légales & commerciales
    ================================================================ --}}
    <div x-show="activeTab === 'legal'" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- IFU (tax number) --}}
            <div>
                <label for="ifu" class="block text-sm font-medium text-gray-700 mb-1">IFU / N° fiscal</label>
                <input type="text" id="ifu" name="ifu"
                       value="{{ old('ifu', $client->ifu ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                       placeholder="Ex: 1234567890123">
                @error('ifu')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- RCCM --}}
            <div>
                <label for="rccm" class="block text-sm font-medium text-gray-700 mb-1">RCCM</label>
                <input type="text" id="rccm" name="rccm"
                       value="{{ old('rccm', $client->rccm ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                       placeholder="Ex: RB/COT/XX/X/XXXX">
                @error('rccm')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Régime fiscal --}}
            <div>
                <label for="tax_regime" class="block text-sm font-medium text-gray-700 mb-1">Régime fiscal</label>
                <select id="tax_regime" name="tax_regime"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">— Sélectionner —</option>
                    @foreach([
                        'RNI'              => 'RNI — Régime du Normal d\'Imposition',
                        'RIS'              => 'RIS — Régime d\'Imposition Simplifié',
                        'TF'               => 'TF — Taxe Forfaitaire',
                        'Auto-Entrepreneur'=> 'Auto-Entrepreneur',
                        'Exonéré'          => 'Exonéré',
                        'Autre'            => 'Autre',
                    ] as $val => $label)
                        <option value="{{ $val }}" {{ old('tax_regime', $client->tax_regime ?? '') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('tax_regime')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Division fiscale --}}
            <div>
                <label for="tax_division" class="block text-sm font-medium text-gray-700 mb-1">Division fiscale</label>
                <input type="text" id="tax_division" name="tax_division"
                       value="{{ old('tax_division', $client->tax_division ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="Ex: DGE, CRI Cotonou, Direction Départementale…">
                @error('tax_division')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Taux de TVA (multi-sélection) --}}
            @php
                $selectedTaxIds = old('tax_rate_ids',
                    $isEdit ? $client->taxRates->pluck('id')->map(fn($id) => (string)$id)->toArray() : []
                );
            @endphp
            <div class="md:col-span-2"
                 x-data="{ selected: {{ Js::from($selectedTaxIds) }} }">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Taxes applicables</label>
                    <span x-show="selected.length > 0"
                          class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full"
                          x-text="selected.length + (selected.length > 1 ? ' taxes sélectionnées' : ' taxe sélectionnée')"></span>
                </div>
                @if(isset($taxRates) && $taxRates->count())
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($taxRates as $tr)
                    @php $trId = (string) $tr->id; @endphp
                    <label for="tax_rate_{{ $tr->id }}"
                           :class="selected.includes('{{ $trId }}')
                               ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-300'
                               : 'border-gray-200 bg-white hover:border-indigo-200 hover:bg-indigo-50/30'"
                           class="relative flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all select-none">
                        <input type="checkbox"
                               id="tax_rate_{{ $tr->id }}"
                               name="tax_rate_ids[]"
                               value="{{ $tr->id }}"
                               x-model="selected"
                               class="sr-only">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <p class="text-sm font-semibold text-gray-800">{{ number_format($tr->rate, 2, ',', '') }} %</p>
                                @if($tr->is_default)
                                <span class="text-[10px] font-medium bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded-full leading-none">Défaut</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5 truncate" title="{{ $tr->name }}">{{ $tr->name }}</p>
                            <p class="text-[10px] font-mono text-gray-400">{{ $tr->short_name }}</p>
                        </div>
                        {{-- Icône checkmark --}}
                        <span :class="selected.includes('{{ $trId }}') ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white'"
                              class="w-4 h-4 rounded border-2 mt-0.5 flex-shrink-0 flex items-center justify-center transition-all">
                            <svg x-show="selected.includes('{{ $trId }}')" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                    </label>
                    @endforeach
                </div>
                {{-- Résumé des taxes sélectionnées --}}
                <p x-show="selected.length === 0" class="mt-2 text-xs text-gray-400 italic">Aucune taxe sélectionnée — les taux de la facture seront appliqués par défaut.</p>
                @else
                <p class="text-sm text-gray-400 italic">Aucun taux actif configuré —
                    <a href="{{ route('settings.tax-rates.index') }}" class="text-indigo-600 hover:underline" target="_blank">Configurer les taux de TVA</a>
                </p>
                @endif
                @error('tax_rate_ids')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Credit limit --}}
            <div>
                <label for="credit_limit" class="block text-sm font-medium text-gray-700 mb-1">Limite de crédit (FCFA)</label>
                <input type="number" id="credit_limit" name="credit_limit" min="0" step="1000"
                       value="{{ old('credit_limit', $client->credit_limit ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="0">
                @error('credit_limit')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Payment days --}}
            <div>
                <label for="payment_days" class="block text-sm font-medium text-gray-700 mb-1">Délai de paiement (jours)</label>
                <input type="number" id="payment_days" name="payment_days" min="0" max="365"
                       value="{{ old('payment_days', $client->payment_days ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="30">
                @error('payment_days')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Default discount --}}
            <div>
                <label for="default_discount" class="block text-sm font-medium text-gray-700 mb-1">Remise par défaut (%)</label>
                <input type="number" id="default_discount" name="default_discount" min="0" max="100" step="0.01"
                       value="{{ old('default_discount', $client->default_discount ?? '') }}"
                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                       placeholder="0.00">
                @error('default_discount')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Active status --}}
            <div class="flex items-center gap-3 pt-6">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $client->is_active ?? true) ? 'checked' : '' }}
                       class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                <label for="is_active" class="text-sm font-medium text-gray-700">Client actif</label>
            </div>

        </div>
    </div>

    {{-- ================================================================
         TAB 3 — Contacts
    ================================================================ --}}
    <div x-show="activeTab === 'contacts'" x-cloak>

        <template x-if="contacts.length === 0">
            <div class="text-center py-12 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <p class="text-sm">Aucun contact ajouté</p>
                <p class="text-xs mt-1">Cliquez sur « Ajouter un contact » pour commencer</p>
            </div>
        </template>

        <div class="space-y-4">
            <template x-for="(contact, index) in contacts" :key="index">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 relative">
                    <div class="absolute top-3 right-3 flex items-center gap-2">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" :name="`contacts[${index}][is_primary]`" value="1"
                                   x-model="contact.is_primary"
                                   class="w-3.5 h-3.5 text-indigo-600 rounded border-gray-300">
                            <span class="text-xs text-gray-500">Principal</span>
                        </label>
                        <button type="button" @click="removeContact(index)"
                                class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pr-20">
                        {{-- Civility --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Civilité</label>
                            <select :name="`contacts[${index}][civility]`" x-model="contact.civility"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">—</option>
                                <option value="M.">M.</option>
                                <option value="Mme">Mme</option>
                                <option value="Dr">Dr</option>
                            </select>
                        </div>
                        {{-- First name --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Prénom</label>
                            <input type="text" :name="`contacts[${index}][first_name]`" x-model="contact.first_name"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="Prénom">
                        </div>
                        {{-- Last name --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nom <span class="text-red-500">*</span></label>
                            <input type="text" :name="`contacts[${index}][last_name]`" x-model="contact.last_name"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="Nom de famille">
                        </div>
                        {{-- Job title --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Poste</label>
                            <input type="text" :name="`contacts[${index}][job_title]`" x-model="contact.job_title"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="Ex: Directeur Commercial">
                        </div>
                        {{-- Phone --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Téléphone</label>
                            <input type="text" :name="`contacts[${index}][phone]`" x-model="contact.phone"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="+229 21 XX XX XX">
                        </div>
                        {{-- Mobile --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Mobile</label>
                            <input type="text" :name="`contacts[${index}][mobile]`" x-model="contact.mobile"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="+229 97 XX XX XX">
                        </div>
                        {{-- Email --}}
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                            <input type="email" :name="`contacts[${index}][email]`" x-model="contact.email"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="contact@example.com">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-4">
            <button type="button" @click="addContact()"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-dashed border-indigo-300 text-indigo-600 rounded-lg text-sm hover:bg-indigo-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter un contact
            </button>
        </div>
    </div>

    {{-- ================================================================
         TAB 4 — Adresses
    ================================================================ --}}
    <div x-show="activeTab === 'addresses'" x-cloak>

        <template x-if="addresses.length === 0">
            <div class="text-center py-12 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p class="text-sm">Aucune adresse ajoutée</p>
                <p class="text-xs mt-1">Cliquez sur « Ajouter une adresse » pour commencer</p>
            </div>
        </template>

        <div class="space-y-4">
            <template x-for="(addr, index) in addresses" :key="index">
                <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 relative">
                    <div class="absolute top-3 right-3 flex items-center gap-2">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" :name="`addresses[${index}][is_default]`" value="1"
                                   x-model="addr.is_default"
                                   class="w-3.5 h-3.5 text-indigo-600 rounded border-gray-300">
                            <span class="text-xs text-gray-500">Défaut</span>
                        </label>
                        <button type="button" @click="removeAddress(index)"
                                class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pr-24">
                        {{-- Type --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                            <select :name="`addresses[${index}][type]`" x-model="addr.type"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="livraison">Livraison</option>
                                <option value="facturation">Facturation</option>
                                <option value="siege">Siège social</option>
                            </select>
                        </div>
                        {{-- City --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Ville</label>
                            <input type="text" :name="`addresses[${index}][city]`" x-model="addr.city"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="Cotonou">
                        </div>
                        {{-- Address --}}
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Adresse <span class="text-red-500">*</span></label>
                            <input type="text" :name="`addresses[${index}][address]`" x-model="addr.address"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="N° rue, quartier...">
                        </div>
                        {{-- Country --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Pays</label>
                            <input type="text" :name="`addresses[${index}][country]`" x-model="addr.country"
                                   class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="Bénin">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-4">
            <button type="button" @click="addAddress()"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-dashed border-indigo-300 text-indigo-600 rounded-lg text-sm hover:bg-indigo-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Ajouter une adresse
            </button>
        </div>
    </div>

</div>
