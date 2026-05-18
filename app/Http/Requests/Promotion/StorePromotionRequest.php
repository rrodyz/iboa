<?php

namespace App\Http\Requests\Promotion;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:150',
            'description'  => 'nullable|string',
            'type'         => 'required|in:pourcentage,montant_fixe,prix_special',
            'value'        => 'required|numeric|min:0',
            'starts_at'    => 'nullable|date',
            'ends_at'      => 'nullable|date|after_or_equal:starts_at',
            'min_quantity' => 'nullable|numeric|min:0',
            'product_id'   => 'nullable|exists:products,id',
            'family_id'    => 'nullable|exists:product_families,id',
            'is_active'    => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Le nom de la promotion est obligatoire.',
            'type.required'  => 'Le type de promotion est obligatoire.',
            'value.required' => 'La valeur de la remise est obligatoire.',
            'value.min'      => 'La valeur ne peut pas être négative.',
            'ends_at.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
