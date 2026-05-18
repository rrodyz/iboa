<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class SaveInventoryCountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                    => 'required|array',
            'items.*.id'               => 'required|integer|exists:inventory_items,id',
            'items.*.counted_quantity' => 'nullable|numeric|min:0',
            'items.*.notes'            => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'               => 'Aucun article à enregistrer.',
            'items.*.id.required'          => 'Identifiant article manquant.',
            'items.*.counted_quantity.min' => 'La quantité ne peut pas être négative.',
        ];
    }
}
