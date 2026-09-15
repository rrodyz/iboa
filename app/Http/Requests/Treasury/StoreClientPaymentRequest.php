<?php

namespace App\Http\Requests\Treasury;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * [PARITÉ SAGE X3] `net_amount` = montant reçu − frais bancaires (informatif).
     * N'affecte NI l'allocation aux factures NI l'écriture comptable (calculées
     * sur `amount`). Recalculé côté serveur pour éviter toute incohérence front.
     */
    protected function prepareForValidation(): void
    {
        $amount = (int) $this->input('amount', 0);
        $fees   = (int) $this->input('bank_fees', 0);
        $this->merge([
            'bank_fees'  => max(0, $fees),
            'net_amount' => max(0, $amount - max(0, $fees)),
        ]);
    }

    public function rules(): array
    {
        $paymentMethod = PaymentMethod::query()->find($this->input('payment_method_id'));

        return [
            'client_id'                          => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'order_id'                           => [
                'nullable',
                'integer',
                Rule::exists('orders', 'id')->where(fn ($query) => $query
                    ->where('client_id', $this->input('client_id'))
                    ->where('status', '!=', 'annule')
                    ->whereNull('deleted_at')),
            ],
            'payment_method_id'                  => ['required', Rule::exists('payment_methods', 'id')->where('is_active', true)],
            // [RELATION MÉTIER] Tout encaissement entre dans UNE caisse/banque —
            // sans elle, la transaction de caisse et l'imputation comptable (571/521)
            // ne peuvent pas être rattachées au bon compte.
            'cash_account_id'                    => ['required', Rule::exists('cash_accounts', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'amount'                             => 'required|numeric|min:1',
            'payment_date'                       => 'required|date',
            'reference'                          => [Rule::requiredIf((bool) $paymentMethod?->requires_reference), 'nullable', 'string', 'max:100'],
            'phone_number'                       => [Rule::requiredIf((bool) $paymentMethod?->is_mobile_money), 'nullable', 'string', 'max:20'],
            'notes'                              => 'nullable|string',
            'is_acompte'                         => 'nullable|boolean',
            'force_duplicate'                    => 'nullable|boolean',
            'allocations'                        => 'nullable|array',
            'allocations.*.invoice_id'           => 'required_with:allocations.*|exists:invoices,id',
            'allocations.*.allocated_amount'     => 'required_with:allocations.*|numeric|min:0',
            // [PARITÉ SAGE X3] Champs descriptifs (métadonnées, sans impact monétaire)
            'bank_fees'                          => 'nullable|integer|min:0',
            'net_amount'                         => 'nullable|integer|min:0',
            'value_date'                         => 'nullable|date',
            'piece_number'                       => 'nullable|string|max:60',
            'bank_reference'                     => 'nullable|string|max:100',
            'treasury_journal'                   => 'required|string|max:20',
            'payment_condition'                  => 'nullable|string|max:60',
            'cost_center'                        => 'nullable|string|max:30',
            'analytic_section'                   => 'nullable|string|max:30',
            'project'                            => 'nullable|string|max:60',
            'salesperson'                        => 'nullable|string|max:100',
            'site'                               => 'required|string|max:40',
            'observations'                       => 'nullable|string|max:1000',
            'documents'                          => [Rule::requiredIf((bool) $paymentMethod?->attachment_required), 'nullable', 'array', 'min:1'],
            'documents.*'                        => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'                      => 'client',
            'order_id'                       => 'commande client',
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
            'order_id.exists'                           => 'La commande sélectionnée n’appartient pas au client choisi.',
            'amount.required'                           => 'Le montant encaissé est obligatoire.',
            'amount.numeric'                            => 'Le montant doit être un nombre valide.',
            'amount.min'                                => 'Le montant doit être supérieur à 0.',
            'payment_date.required'                     => 'La date de paiement est obligatoire.',
            'payment_date.date'                         => 'La date de paiement n\'est pas une date valide.',
            'payment_method_id.exists'                  => 'Le mode de paiement sélectionné est invalide.',
            'payment_method_id.required'                => 'Veuillez sélectionner un mode de paiement.',
            'reference.required'                        => 'La référence est obligatoire pour ce mode de paiement.',
            'phone_number.required'                     => 'Le numéro de téléphone est obligatoire pour un règlement Mobile Money.',
            'cash_account_id.exists'                    => 'La caisse sélectionnée est invalide.',
            'treasury_journal.required'                 => 'Le journal de trésorerie est obligatoire.',
            'site.required'                             => 'Le site est obligatoire.',
            'documents.required'                        => 'Un justificatif est obligatoire pour ce mode de paiement.',
            'reference.max'                             => 'La référence ne peut pas dépasser 100 caractères.',
            'allocations.*.invoice_id.required_with'    => 'Chaque imputation doit référencer une facture.',
            'allocations.*.invoice_id.exists'           => 'Une facture sélectionnée pour imputation est invalide.',
            'allocations.*.allocated_amount.required_with' => 'Le montant imputé est obligatoire pour chaque facture.',
            'allocations.*.allocated_amount.numeric'    => 'Le montant imputé doit être un nombre valide.',
            'allocations.*.allocated_amount.min'        => 'Le montant imputé ne peut pas être négatif.',
        ];
    }
}
