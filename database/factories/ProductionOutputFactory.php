<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOutputFactory extends Factory
{
    protected $model = ProductionOutput::class;

    public function definition(): array
    {
        $length   = $this->faker->randomElement([3, 4, 6]);
        $quantity = $this->faker->numberBetween(5, 40);

        return [
            'company_id'          => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'product_id'          => Product::query()->value('id') ?? Product::factory(),
            'length'              => $length,
            'color'               => $this->faker->randomElement(['Rouge', 'Bleu', 'Galva']),
            'thickness'           => 0.40,
            'quantity'            => $quantity,
            'total_meters'        => $length * $quantity,
            'produced_at'         => now(),
        ];
    }
}
