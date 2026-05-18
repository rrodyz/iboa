<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('warehouse')->id;

        return [
            'name'         => 'required|string|max:120',
            'code'         => "required|string|max:20|unique:warehouses,code,{$id}",
            'address'      => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:80',
            'manager_name' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:30',
            'is_default'   => 'boolean',
            'is_active'    => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => "Le nom de l'entrepôt est obligatoire.",
            'code.required' => "Le code de l'entrepôt est obligatoire.",
            'code.unique'   => 'Ce code entrepôt existe déjà.',
        ];
    }
}
