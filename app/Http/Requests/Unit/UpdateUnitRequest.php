<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('unit')->id;

        return [
            'name'           => "required|string|max:100|unique:units,name,{$id}",
            'abbreviation'   => "required|string|max:20|unique:units,abbreviation,{$id}",
            'type'           => 'nullable|in:quantite,poids,volume,longueur,surface,temps,autre',
            'decimal_places' => 'nullable|integer|min:0|max:6',
            'is_active'      => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => "Le nom de l'unité est obligatoire.",
            'name.unique'           => 'Ce nom d\'unité existe déjà.',
            'abbreviation.required' => "L'abréviation est obligatoire.",
            'abbreviation.unique'   => 'Cette abréviation existe déjà.',
        ];
    }
}
