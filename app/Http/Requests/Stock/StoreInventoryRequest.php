<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'type'         => 'nullable|in:tournant,annuel,complet',
            'notes'        => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Veuillez sélectionner un entrepôt.',
            'warehouse_id.exists'   => 'L\'entrepôt sélectionné est invalide.',
        ];
    }
}
