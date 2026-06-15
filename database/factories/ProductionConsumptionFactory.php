<?php

namespace Database\Factories;

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionConsumptionFactory extends Factory
{
    protected $model = ProductionConsumption::class;

    public function definition(): array
    {
        $weight = $this->faker->numberBetween(50, 300);

        return [
            'company_id'          => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'coil_id'             => Coil::factory(),
            'weight_consumed'     => $weight,
            'length_consumed'     => round($weight / 4, 2),
            'cost'                => $weight * $this->faker->numberBetween(400, 800),
            'consumed_at'         => now(),
        ];
    }
}
