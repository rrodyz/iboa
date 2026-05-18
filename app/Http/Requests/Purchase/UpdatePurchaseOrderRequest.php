<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'              => 'required|exists:suppliers,id',
            'issued_at'                => 'required|date',
            'expected_at'              => 'nullable|date',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'nullable|exists:products,id',
            'items.*.description'      => 'required|string|max:500',
            'items.*.quantity'         => 'required|numeric|min:0.0001',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate_value'   => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id'    => 'fournisseur',
            'issued_at'      => 'date de commande',
            'expected_at'    => 'date de livraison prévue',
            'items'          => 'lignes',
            'items.*.description' => 'description',
            'items.*.quantity'    => 'quantité',
            'items.*.unit_price'  => 'prix unitaire',
        ];
    }
}
