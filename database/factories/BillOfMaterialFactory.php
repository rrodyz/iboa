<?php

namespace Database\Factories;

use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillOfMaterialFactory extends Factory
{
    protected $model = BillOfMaterial::class;

    public function definition(): array
    {
        return [
            'company_id'            => Company::query()->value('id') ?? Company::factory(),
            'name'                  => 'Bac ' . $this->faker->randomElement(['0.40', '0.50']) . ' ' . $this->faker->randomElement(['Galva', 'Prélaqué']),
            'sheet_type'            => 'bac',
            'thickness'             => $this->faker->randomElement([0.40, 0.50]),
            'coil_width'            => 1200,
            'usable_width'          => 1000,
            'standard_waste_rate'   => $this->faker->randomFloat(2, 2, 8),
            'consumption_per_meter' => $this->faker->randomFloat(4, 2.5, 4),
            'machine_time_per_unit' => $this->faker->randomFloat(2, 1, 5),
            'labor_per_unit'        => $this->faker->numberBetween(100, 500),
            'is_active'             => true,
        ];
    }
}
