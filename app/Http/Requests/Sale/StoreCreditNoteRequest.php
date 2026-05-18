<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'invoice_id'                   => ['required', 'exists:invoices,id'],
            'issued_at'                    => ['required', 'date'],
            'reason'                       => ['nullable', 'string', 'max:200'],
            'notes'                        => ['nullable', 'string'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.description'          => ['required', 'string', 'max:255'],
            'items.*.product_id'           => ['nullable', 'exists:products,id'],
            'items.*.quantity'             => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'           => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate_value'       => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
