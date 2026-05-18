<?php
namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'trade_name'  => 'nullable|string|max:150',
            'slogan'      => 'nullable|string|max:255',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'region'      => 'nullable|string|max:100',
            'country'     => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone'       => 'nullable|string|max:20',
            'phone2'      => 'nullable|string|max:20',
            'fax'         => 'nullable|string|max:20',
            'email'       => 'nullable|email|max:150',
            'website'     => 'nullable|url|max:150',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'  => 'raison sociale',
            'email' => 'adresse email',
            'logo'  => 'logo',
        ];
    }
}
