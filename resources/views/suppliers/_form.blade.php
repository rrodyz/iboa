{{--
    Reusable supplier form partial.
    Variables expected:
      $supplier    (optional, for edit mode — Supplier model)
      $formAction  — target URL
      $formMethod  — 'POST' or 'PUT'
--}}

@php
    $isEdit           = isset($supplier) && $supplier->exists;
    $existingContacts = $isEdit ? $supplier->contacts->toArray() : [];
    $existingAddresses = $isEdit ? $supplier->addresses->toArray() : [];
@endphp

<form action="{{ $formAction }}" method="POST" novalidate>
    @csrf
    @if($formMethod === 'PUT')
        @method('PUT')
    @endif

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
                    type: 'siege',
                    label: '',
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
                    ['id' => 'info',      'label' => 'Informations'],
                    ['id' => 'legal',     'label' => 'Infos légales'],
                    ['id' => 'contacts',  'label' => 'Contacts'],
                    ['id' => 'addresses', 'label' => 'Adresses'],
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

                {{-- Raison sociale --}}
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        Raison sociale / Nom <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name" name="name"
                           value="{{ old('name', $supplier->name ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-300 @enderror"
                           placeholder="Ex: Société de distribution SARL">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Code --}}
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">Code fournisseur</label>
                    <input type="text" id="code" name="code"
                           value="{{ old('code', $supplier->code ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono @error('code') border-red-300 @enderror"
                           placeholder="Généré automatiquement">
                    <p class="mt-1 text-xs text-gray-400">Laissez vide pour génération automatique</p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select id="type" name="type"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">— Sélectionner —</option>
                        @foreach(['entreprise' => 'Entreprise / Société', 'particulier' => 'Particulier'] as $val => $label)
                            <option value="{{ $val }}" {{ old('type', $supplier->type ?? 'entreprise') === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $supplier->email ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                           placeholder="contact@fournisseur.com">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone fixe</label>
                    <input type="text" id="phone" name="phone"
                           value="{{ old('phone', $supplier->phone ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="+229 21 XX XX XX">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone 2 / Mobile --}}
                <div>
                    <label for="phone2" class="block text-sm font-medium text-gray-700 mb-1">Mobile / Tél. 2</label>
                    <input type="text" id="phone2" name="phone2"
                           value="{{ old('phone2', $supplier->phone2 ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="+229 97 XX XX XX">
                    @error('phone2')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Website --}}
                <div class="md:col-span-2">
                    <label for="website" class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
                    <input type="url" id="website" name="website"
                           value="{{ old('website', $supplier->website ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('website') border-red-300 @enderror"
                           placeholder="https://www.fournisseur.com">
                    @error('website')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notes --}}
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                              placeholder="Informations complémentaires, conditions particulières...">{{ old('notes', $supplier->notes ?? '') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>

        {{-- ================================================================
             TAB 2 — Infos légales & localisation
        ================================================================ --}}
        <div x-show="activeTab === 'legal'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                {{-- IFU --}}
                <div>
                    <label for="ifu" class="block text-sm font-medium text-gray-700 mb-1">IFU</label>
                    <input type="text" id="ifu" name="ifu"
                           value="{{ old('ifu', $supplier->ifu ?? '') }}"
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
                           value="{{ old('rccm', $supplier->rccm ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                           placeholder="Ex: RB/COT/XX/X/XXXX">
                    @error('rccm')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Address --}}
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Adresse principale</label>
                    <input type="text" id="address" name="address"
                           value="{{ old('address', $supplier->address ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="N° rue, quartier, BP...">
                    @error('address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- City --}}
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700 mb-1">Ville</label>
                    <input type="text" id="city" name="city"
                           value="{{ old('city', $supplier->city ?? '') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Cotonou">
                    @error('city')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Country --}}
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1">Pays</label>
                    <input type="text" id="country" name="country"
                           value="{{ old('country', $supplier->country ?? 'Bénin') }}"
                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Bénin">
                    @error('country')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Active status --}}
                <div class="flex items-center gap-3 pt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           {{ old('is_active', $supplier->is_active ?? true) ? 'checked' : '' }}
                           class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm font-medium text-gray-700">Fournisseur actif</label>
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
                                    <option value="Prof">Prof</option>
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
                                       placeholder="Ex: Responsable commercial">
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
                                       placeholder="contact@fournisseur.com">
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
                                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                                <select :name="`addresses[${index}][type]`" x-model="addr.type"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="siege">Siège social</option>
                                    <option value="livraison">Livraison</option>
                                    <option value="facturation">Facturation</option>
                                </select>
                            </div>
                            {{-- Label --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Libellé</label>
                                <input type="text" :name="`addresses[${index}][label]`" x-model="addr.label"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                       placeholder="Ex: Entrepôt principal">
                            </div>
                            {{-- Address --}}
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Adresse <span class="text-red-500">*</span></label>
                                <input type="text" :name="`addresses[${index}][address]`" x-model="addr.address"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                       placeholder="N° rue, quartier, BP...">
                            </div>
                            {{-- City --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ville</label>
                                <input type="text" :name="`addresses[${index}][city]`" x-model="addr.city"
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                       placeholder="Cotonou">
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

        {{-- Form actions --}}
        <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-200">
            <a href="{{ isset($supplier) && $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                Annuler
            </a>
            <button type="submit"
                    class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                {{ $isEdit ? 'Enregistrer les modifications' : 'Créer le fournisseur' }}
            </button>
        </div>

    </div>
</form>
