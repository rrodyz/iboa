<?php

namespace App\Http\Requests\Treasury;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'                          => 'required|exists:clients,id',
            'payment_method_id'                  => 'nullable|exists:payment_methods,id',
            'cash_account_id'                    => 'nullable|exists:cash_accounts,id',
            'amount'                             => 'required|numeric|min:1',
            'payment_date'                       => 'required|date',
            'reference'                          => 'nullable|string|max:100',
            'phone_number'                       => 'nullable|string|max:20',
            'notes'                              => 'nullable|string',
            'force_duplicate'                    => 'nullable|boolean',
            'allocations'                        => 'nullable|array',
            'allocations.*.invoice_id'           => 'required_with:allocations.*|exists:invoices,id',
            'allocations.*.allocated_amount'     => 'required_with:allocations.*|numeric|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'                      => 'client',
            'payment_method_id'              => 'mode de paiement',
            'cash_account_id'                => 'caisse / compte',
            'payment_date'                   => 'date du paiement',
            'amount'                         => 'montant',
            'reference'                      => 'référence',
            'phone_number'                   => 'numéro de téléphone',
            'allocations.*.invoice_id'       => 'facture',
            'allocations.*.allocated_amount' => 'montant imputé',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'                        => 'Veuillez sélectionner un client.',
            'client_id.exists'                          => 'Le client sélectionné est invalide.',
            'amount.required'                           => 'Le montant encaissé est obligatoire.',
            'amount.numeric'                            => 'Le montant doit être un nombre valide.',
            'amount.min'                                => 'Le montant doit être supérieur à 0.',
            'payment_date.required'                     => 'La date de paiement est obligatoire.',
            'payment_date.date'                         => 'La date de paiement n\'est pas une date valide.',
            'payment_method_id.exists'                  => 'Le mode de paiement sélectionné est invalide.',
            'cash_account_id.exists'                    => 'La caisse sélectionnée est invalide.',
            'reference.max'                             => 'La référence ne peut pas dépasser 100 caractères.',
            'allocations.*.invoice_id.required_with'    => 'Chaque imputation doit référencer une facture.',
            'allocations.*.invoice_id.exists'           => 'Une facture sélectionnée pour imputation est invalide.',
            'allocations.*.allocated_amount.required_with' => 'Le montant imputé est obligatoire pour chaque facture.',
            'allocations.*.allocated_amount.numeric'    => 'Le montant imputé doit être un nombre valide.',
            'allocations.*.allocated_amount.min'        => 'Le montant imputé ne peut pas être négatif.',
        ];
    }
}
