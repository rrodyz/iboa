<?php

namespace App\Http\Requests\ProductFamily;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductFamilyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                => 'required|string|max:100',
            'code'                => 'nullable|string|max:30|unique:product_families,code',
            'parent_id'           => 'nullable|exists:product_families,id',
            'description'         => 'nullable|string|max:500',
            'sale_account_id'     => 'nullable|exists:accounts,id',
            'purchase_account_id' => 'nullable|exists:accounts,id',
            'stock_account_id'    => 'nullable|exists:accounts,id',
            'is_active'           => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la famille est obligatoire.',
            'code.unique'   => 'Ce code famille existe déjà.',
            'parent_id.exists' => 'La famille parente sélectionnée est invalide.',
        ];
    }
}
