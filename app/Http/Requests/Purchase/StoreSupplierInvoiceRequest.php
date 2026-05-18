<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'              => 'required|exists:suppliers,id',
            'received_at'              => 'required|date',
            'due_at'                   => 'nullable|date|after_or_equal:received_at',
            'supplier_invoice_number'  => 'nullable|string|max:50',
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
            'supplier_id' => 'fournisseur',
            'received_at' => 'date de réception',
            'due_at'      => 'date d\'échéance',
            'items'       => 'lignes',
            'items.*.description' => 'description',
            'items.*.quantity'    => 'quantité',
            'items.*.unit_price'  => 'prix unitaire',
        ];
    }
}
