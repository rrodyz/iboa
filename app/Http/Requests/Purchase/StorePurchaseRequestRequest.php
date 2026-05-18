<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase_requests.create');
    }

    public function rules(): array
    {
        return [
            'department'   => ['nullable', 'string', 'max:100'],
            'justification'=> ['nullable', 'string', 'max:255'],
            'needed_at'    => ['nullable', 'date', 'after_or_equal:today'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description'         => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.unit_id'             => ['nullable', 'integer', 'exists:units,id'],
            'items.*.quantity'            => ['required', 'numeric', 'min:0.001'],
            'items.*.estimated_price'     => ['nullable', 'integer', 'min:0'],
            'items.*.notes'               => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'needed_at'           => 'date souhaitée',
            'items'               => 'lignes',
            'items.*.quantity'    => 'quantité',
            'items.*.description' => 'description',
        ];
    }
}
