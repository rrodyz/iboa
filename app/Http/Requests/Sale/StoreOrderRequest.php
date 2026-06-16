<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'                    => 'required|exists:clients,id',
            'issued_at'                    => 'required|date',
            'delivery_date'                => 'nullable|date',
            'delivery_warehouse_id'        => 'nullable|exists:warehouses,id',
            'delivery_address'             => 'nullable|string',
            'billing_address'              => 'nullable|string',
            'reference'                    => 'nullable|string|max:50',
            'global_discount_amount'       => 'nullable|numeric|min:0',
            'global_discount_percent'      => 'nullable|numeric|min:0|max:100',
            'notes'                        => 'nullable|string',
            'terms'                        => 'nullable|string',
            'footer_note'                  => 'nullable|string',

            'items'                        => 'required|array|min:1',
            'items.*.product_id'           => 'nullable|exists:products,id',
            'items.*.description'          => 'required|string|max:255',
            'items.*.unit_id'              => 'nullable|exists:units,id',
            'items.*.quantity'             => 'required|numeric|min:0.0001',
            'items.*.nb_toles'             => 'nullable|numeric|min:0',
            'items.*.metrage_par_tole'     => 'nullable|numeric|min:0',
            'items.*.unit_price'           => 'required|numeric|min:0',
            'items.*.discount_percent'     => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate_id'          => 'nullable|exists:tax_rates,id',
            'items.*.tax_rate_value'       => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'                => 'client',
            'issued_at'                => 'date d\'émission',
            'delivery_date'            => 'date de livraison prévue',
            'delivery_warehouse_id'    => 'entrepôt de livraison',
            'delivery_address'         => 'adresse de livraison',
            'billing_address'          => 'adresse de facturation',
            'reference'                => 'référence',
            'global_discount_percent'  => 'remise globale (%)',
            'global_discount_amount'   => 'remise globale (montant)',
            'items'                    => 'lignes',
            'items.*.description'      => 'description',
            'items.*.quantity'         => 'quantité',
            'items.*.unit_price'       => 'prix unitaire',
            'items.*.discount_percent' => 'remise (%)',
            'items.*.tax_rate_value'   => 'taux TVA',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'           => 'Veuillez sélectionner un client.',
            'client_id.exists'             => 'Le client sélectionné est invalide.',
            'issued_at.required'           => 'La date d\'émission est obligatoire.',
            'issued_at.date'               => 'La date d\'émission n\'est pas une date valide.',
            'delivery_date.date'           => 'La date de livraison n\'est pas une date valide.',

            'items.required'               => 'Veuillez ajouter au moins une ligne à la commande.',
            'items.min'                    => 'Veuillez ajouter au moins une ligne à la commande.',

            'items.*.description.required' => 'La description est obligatoire pour chaque ligne.',
            'items.*.description.max'      => 'La description ne peut pas dépasser 255 caractères.',
            'items.*.quantity.required'    => 'La quantité est obligatoire pour chaque ligne.',
            'items.*.quantity.numeric'     => 'La quantité doit être un nombre valide.',
            'items.*.quantity.min'         => 'La quantité doit être supérieure à 0.',
            'items.*.unit_price.required'  => 'Le prix unitaire est obligatoire pour chaque ligne.',
            'items.*.unit_price.numeric'   => 'Le prix unitaire doit être un nombre valide.',
            'items.*.unit_price.min'       => 'Le prix unitaire ne peut pas être négatif.',
            'items.*.discount_percent.min' => 'La remise ne peut pas être négative.',
            'items.*.discount_percent.max' => 'La remise ne peut pas dépasser 100 %.',
            'items.*.tax_rate_value.min'   => 'Le taux de TVA ne peut pas être négatif.',
            'items.*.tax_rate_value.max'   => 'Le taux de TVA ne peut pas dépasser 100 %.',
        ];
    }
}
