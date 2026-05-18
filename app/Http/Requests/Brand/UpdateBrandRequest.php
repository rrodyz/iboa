<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('brand')->id;

        return [
            'name'        => "required|string|max:100|unique:brands,name,{$id}",
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la marque est obligatoire.',
            'name.unique'   => 'Cette marque existe déjà.',
        ];
    }
}
