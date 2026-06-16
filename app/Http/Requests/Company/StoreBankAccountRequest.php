<?php
namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'bank_name'      => 'required|string|max:100',
            'account_holder' => 'required|string|max:150',
            'account_number' => 'required|string|max:50',
            'iban'           => 'nullable|string|max:34',
            'swift_bic'      => 'nullable|string|max:11',
            'branch'         => 'nullable|string|max:100',
            'is_default'     => 'boolean',
            'is_active'      => 'boolean',
            'sync_treasury'  => 'boolean',
        ];
    }
}
