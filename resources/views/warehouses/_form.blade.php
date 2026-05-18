{{-- Shared form fields for create & edit --}}

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">

    {{-- Nom --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Nom de l'entrepôt <span class="text-red-500">*</span>
        </label>
        <input type="text" name="name" value="{{ old('name', $warehouse->name) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 @error('name') border-red-400 @enderror"
               placeholder="ex: Entrepôt Central, Dépôt Nord…" required autofocus>
        @error('name')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Code --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">
            Code <span class="text-red-500">*</span>
        </label>
        <input type="text" name="code" value="{{ old('code', $warehouse->code) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono uppercase focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 @error('code') border-red-400 @enderror"
               placeholder="ex: WH-01, DEPOT-NORD…" required
               oninput="this.value = this.value.toUpperCase()">
        <p class="mt-1 text-xs text-gray-400">Code unique, utilisé dans les mouvements de stock.</p>
        @error('code')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Ville --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Ville</label>
        <input type="text" name="city" value="{{ old('city', $warehouse->city) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
               placeholder="ex: Abidjan, Douala…">
        @error('city')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Adresse --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Adresse</label>
        <input type="text" name="address" value="{{ old('address', $warehouse->address) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
               placeholder="Rue, quartier, commune…">
        @error('address')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Responsable --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Responsable</label>
        <input type="text" name="manager_name" value="{{ old('manager_name', $warehouse->manager_name) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
               placeholder="Nom du responsable">
        @error('manager_name')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Téléphone --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Téléphone</label>
        <input type="text" name="phone" value="{{ old('phone', $warehouse->phone) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
               placeholder="+225 07 00 00 00 00">
        @error('phone')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Options --}}
    <div class="md:col-span-2 flex flex-col sm:flex-row gap-4 pt-1">
        <label class="flex items-center gap-3 cursor-pointer group">
            <div class="relative">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $warehouse->exists ? $warehouse->is_active : true) ? 'checked' : '' }}
                       class="sr-only peer">
                <div class="w-10 h-5 bg-gray-200 peer-checked:bg-emerald-500 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-emerald-400"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-700">Actif</span>
                <p class="text-xs text-gray-400">Les entrepôts inactifs n'apparaissent pas dans les formulaires</p>
            </div>
        </label>

        <label class="flex items-center gap-3 cursor-pointer group">
            <div class="relative">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1"
                       {{ old('is_default', $warehouse->is_default ?? false) ? 'checked' : '' }}
                       class="sr-only peer">
                <div class="w-10 h-5 bg-gray-200 peer-checked:bg-indigo-500 rounded-full transition-colors peer-focus:ring-2 peer-focus:ring-indigo-400"></div>
                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-700">Entrepôt par défaut</span>
                <p class="text-xs text-gray-400">Pré-sélectionné lors de la création de mouvements</p>
            </div>
        </label>
    </div>

</div>
