<?php
namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'legal_form'           => 'nullable|string|max:50',
            'rccm'                 => 'nullable|string|max:50',
            'ifu'                  => 'nullable|string|max:30',
            'nif'                  => 'nullable|string|max:30',
            'is_vat_subject'       => 'boolean',
            'vat_number'           => 'nullable|numeric|min:0|max:100',
            'share_capital'        => 'nullable|integer|min:0',
            'share_capital_currency' => 'nullable|string|max:3',
        ];
    }
}
