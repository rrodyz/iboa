<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Identification
            'name'              => 'required|string|max:200',
            'reference'         => 'nullable|string|max:50|unique:products,reference',
            'barcode'           => 'nullable|string|max:50|unique:products,barcode',
            'description'       => 'nullable|string',
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',

            // Classification
            'family_id'             => 'nullable|exists:product_families,id',
            'brand_id'              => 'nullable|exists:brands,id',
            'unit_id'               => 'nullable|exists:units,id',
            'tax_rate_id'           => 'nullable|exists:tax_rates,id',
            'sale_account_id'       => 'nullable|exists:accounts,id',
            'purchase_account_id'   => 'nullable|exists:accounts,id',
            'stock_account_id'      => 'nullable|exists:accounts,id',
            'default_supplier_id'   => 'nullable|exists:suppliers,id',
            'supplier_reference'    => 'nullable|string|max:80',
            'delivery_delay_days'   => 'nullable|integer|min:0|max:365',
            'type'                  => 'required|in:simple,service,compose',

            // Comportement
            'is_stockable'      => 'boolean',
            'is_purchasable'    => 'boolean',
            'is_sellable'       => 'boolean',
            'is_active'         => 'boolean',

            // Traçabilité
            'has_serial_number' => 'boolean',
            'has_lot_number'    => 'boolean',
            'has_expiry_date'   => 'boolean',

            // Tarification
            'purchase_price'        => 'nullable|integer|min:0',
            'sale_price'            => 'nullable|integer|min:0',
            'min_sale_price'        => 'nullable|integer|min:0',
            'margin_rate_target'    => 'nullable|numeric|min:0|max:999.99',
            'valuation_method'      => 'nullable|in:cmp,fifo,lifo',

            // Stock & seuils
            'stock_min'         => 'nullable|numeric|min:0',
            'stock_max'         => 'nullable|numeric|min:0|gte:stock_min',
            'reorder_point'     => 'nullable|numeric|min:0',

            // Poids
            'weight'            => 'nullable|numeric|min:0',
            'weight_unit'       => 'nullable|in:kg,g,t',

            // Composants (type composé)
            'components'                            => 'nullable|array',
            'components.*.component_product_id'     => 'required_with:components|exists:products,id',
            'components.*.quantity'                 => 'required_with:components|numeric|min:0.001',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                              => 'désignation',
            'reference'                         => 'référence',
            'sale_price'                        => 'prix de vente',
            'tax_rate_id'                       => 'taux TVA',
            'stock_max'                         => 'stock maximum',
            'reorder_point'                     => 'seuil de réapprovisionnement',
            'components.*.component_product_id' => 'composant',
            'components.*.quantity'             => 'quantité du composant',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_stockable'      => $this->boolean('is_stockable',      true),
            'is_purchasable'    => $this->boolean('is_purchasable',    true),
            'is_sellable'       => $this->boolean('is_sellable',       true),
            'is_active'         => $this->boolean('is_active',         true),
            'has_serial_number' => $this->boolean('has_serial_number', false),
            'has_lot_number'    => $this->boolean('has_lot_number',    false),
            'has_expiry_date'   => $this->boolean('has_expiry_date',   false),
        ]);
    }
}
