<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:150',
            // [ANTI-DUPLICATE] Code, email, IFU et RCCM doivent être uniques.
            // Validation FormRequest (UX) + index unique DB (défense en profondeur).
            'code'             => 'nullable|string|max:30|unique:clients,code',
            'type'             => 'required|in:particulier,entreprise',
            'email'            => 'nullable|email|max:150|unique:clients,email',
            'phone'            => 'nullable|string|max:20',
            'mobile'           => 'nullable|string|max:20',
            'website'          => 'nullable|url|max:150',
            'ifu'              => 'nullable|string|max:50|unique:clients,ifu',
            'rccm'             => 'nullable|string|max:50|unique:clients,rccm',
            'tax_regime'       => 'nullable|string|max:100',
            'tax_division'     => 'nullable|string|max:150',
            'tax_rate_ids'     => 'nullable|array',
            'tax_rate_ids.*'   => 'integer|exists:tax_rates,id',
            'credit_limit'     => 'nullable|numeric|min:0',
            'payment_days'     => 'nullable|integer|min:0|max:365',
            'default_discount' => 'nullable|numeric|min:0|max:100',
            'notes'            => 'nullable|string',
            // contacts
            'contacts'                  => 'nullable|array',
            'contacts.*.civility'       => 'nullable|string|max:10',
            'contacts.*.first_name'     => 'nullable|string|max:80',
            'contacts.*.last_name'      => 'required_with:contacts.*.first_name|string|max:80',
            'contacts.*.phone'          => 'nullable|string|max:20',
            'contacts.*.mobile'         => 'nullable|string|max:20',
            'contacts.*.email'          => 'nullable|email|max:150',
            'contacts.*.job_title'      => 'nullable|string|max:100',
            'contacts.*.is_primary'     => 'nullable|boolean',
            // addresses
            'addresses'                 => 'nullable|array',
            'addresses.*.type'          => 'required_with:addresses.*|in:livraison,facturation,siege',
            'addresses.*.address'       => 'required_with:addresses.*|string|max:200',
            'addresses.*.city'          => 'nullable|string|max:100',
            'addresses.*.country'       => 'nullable|string|max:100',
            'addresses.*.is_default'    => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'             => 'raison sociale',
            'type'             => 'type de client',
            'email'            => 'email',
            'phone'            => 'téléphone',
            'credit_limit'     => 'limite de crédit',
            'payment_days'     => 'délai de paiement',
            'default_discount' => 'remise par défaut',
            'ifu'              => 'IFU / NIF',
            'rccm'             => 'RCCM',
        ];
    }

    /**
     * [ANTI-DUPLICATE] Messages d'erreur explicites pour les unicités.
     */
    public function messages(): array
    {
        return [
            'code.unique'  => 'Ce client existe déjà avec ce code interne.',
            'email.unique' => 'Ce client existe déjà avec cette adresse email.',
            'ifu.unique'   => 'Ce client existe déjà avec ce numéro IFU/NIF.',
            'rccm.unique'  => 'Ce client existe déjà avec ce numéro RCCM.',
        ];
    }
}
