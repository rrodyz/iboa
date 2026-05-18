<?php

namespace App\Http\Requests\ProductFamily;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductFamilyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('family')->id;

        return [
            'name'                => 'required|string|max:100',
            'code'                => "nullable|string|max:30|unique:product_families,code,{$id}",
            'parent_id'           => "nullable|exists:product_families,id|not_in:{$id}",
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
            'name.required'    => 'Le nom de la famille est obligatoire.',
            'code.unique'      => 'Ce code famille existe déjà.',
            'parent_id.not_in' => 'Une famille ne peut pas être sa propre parente.',
        ];
    }
}
