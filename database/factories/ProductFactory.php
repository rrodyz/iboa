<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'reference'        => 'PRD-' . str_pad($counter++, 5, '0', STR_PAD_LEFT),
            'name'             => $this->faker->words(3, true),
            'type'             => $this->faker->randomElement(['simple', 'compose', 'service']),
            'is_stockable'     => true,
            'is_purchasable'   => true,
            'is_sellable'      => true,
            'has_lot_number'   => false,
            'has_serial_number'=> false,
            'has_expiry_date'  => false,
            'purchase_price'   => $this->faker->numberBetween(1_000, 500_000),
            'sale_price'       => $this->faker->numberBetween(2_000, 700_000),
            'stock_min'        => 5,
            'stock_max'        => 500,
            'valuation_method' => 'cmp',
            'is_active'        => true,
        ];
    }
}
