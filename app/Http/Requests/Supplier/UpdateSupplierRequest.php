<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')->id;

        return [
            'name'    => 'required|string|max:150',
            'code'    => 'nullable|string|max:30|unique:suppliers,code,' . $supplierId,
            'type'    => 'nullable|in:particulier,entreprise',
            'email'   => 'nullable|email|max:150',
            'phone'   => 'nullable|string|max:20',
            'phone2'  => 'nullable|string|max:20',
            'website' => 'nullable|url|max:150',
            'ifu'     => 'nullable|string|max:50',
            'rccm'    => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'notes'   => 'nullable|string',
            'is_active' => 'nullable|boolean',

            'contacts'              => 'nullable|array',
            'contacts.*.civility'   => 'nullable|string|max:10',
            'contacts.*.first_name' => 'nullable|string|max:80',
            'contacts.*.last_name'  => 'nullable|string|max:80',
            'contacts.*.job_title'  => 'nullable|string|max:100',
            'contacts.*.phone'      => 'nullable|string|max:20',
            'contacts.*.mobile'     => 'nullable|string|max:20',
            'contacts.*.email'      => 'nullable|email|max:150',
            'contacts.*.is_primary' => 'nullable|boolean',

            'addresses'               => 'nullable|array',
            'addresses.*.type'        => 'nullable|in:livraison,facturation,siege',
            'addresses.*.label'       => 'nullable|string|max:100',
            'addresses.*.address'     => 'nullable|string|max:255',
            'addresses.*.city'        => 'nullable|string|max:100',
            'addresses.*.country'     => 'nullable|string|max:100',
            'addresses.*.is_default'  => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'  => 'raison sociale',
            'code'  => 'code fournisseur',
            'email' => 'adresse e-mail',
            'ifu'   => 'IFU',
            'rccm'  => 'RCCM',
        ];
    }
}
