{{-- Nom --}}
<div>
    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
        Nom <span class="text-red-500">*</span>
    </label>
    <input type="text" id="name" name="name"
           value="{{ old('name', $unit->name ?? '') }}"
           required placeholder="Ex: Pièce, Kilogramme, Litre…"
           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500 focus:border-teal-500 @error('name') border-red-400 bg-red-50 @enderror">
    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

{{-- Abréviation --}}
<div>
    <label for="abbreviation" class="block text-sm font-medium text-gray-700 mb-1">
        Abréviation <span class="text-red-500">*</span>
    </label>
    <input type="text" id="abbreviation" name="abbreviation"
           value="{{ old('abbreviation', $unit->abbreviation ?? '') }}"
           required placeholder="Ex: pcs, kg, L, m…" maxlength="20"
           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-teal-500 focus:border-teal-500 @error('abbreviation') border-red-400 bg-red-50 @enderror">
    @error('abbreviation')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>

{{-- Type --}}
<div>
    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
    <select id="type" name="type"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
        <option value="">— Sélectionner —</option>
        @foreach(['quantite' => 'Quantité', 'poids' => 'Poids', 'volume' => 'Volume', 'longueur' => 'Longueur', 'surface' => 'Surface', 'temps' => 'Temps', 'autre' => 'Autre'] as $val => $label)
        <option value="{{ $val }}" {{ old('type', $unit->type ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

{{-- Décimales --}}
<div>
    <label for="decimal_places" class="block text-sm font-medium text-gray-700 mb-1">Nombre de décimales</label>
    <input type="number" id="decimal_places" name="decimal_places"
           value="{{ old('decimal_places', $unit->decimal_places ?? 2) }}"
           min="0" max="6"
           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-teal-500">
    <p class="text-xs text-gray-400 mt-1">Précision des quantités pour cette unité (0 = entier)</p>
</div>

{{-- Actif --}}
<div class="flex items-center gap-3 py-3 border-t border-gray-100">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" id="is_active" name="is_active" value="1"
           {{ old('is_active', ($unit->is_active ?? true) ? '1' : '') ? 'checked' : '' }}
           class="w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-teal-500">
    <label for="is_active" class="text-sm font-medium text-gray-700">Unité active</label>
</div>
