<?php

namespace Database\Factories;

use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\BomLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class BomLineFactory extends Factory
{
    protected $model = BomLine::class;

    public function definition(): array
    {
        return [
            'bill_of_material_id' => BillOfMaterial::factory(),
            'label'               => $this->faker->randomElement(['Faîtière', 'Rive', 'Closoir', 'Vis autoperçeuse']),
            'quantity_per_meter'  => $this->faker->randomFloat(4, 0.05, 0.5),
            'waste_rate'          => $this->faker->randomFloat(2, 0, 5),
            'sort_order'          => 0,
        ];
    }
}
