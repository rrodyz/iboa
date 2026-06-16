<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionMachineFactory extends Factory
{
    protected $model = ProductionMachine::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'company_id'  => Company::query()->value('id') ?? Company::factory(),
            'code'        => 'MAC-' . str_pad($counter++, 3, '0', STR_PAD_LEFT),
            'name'        => 'Machine ' . $this->faker->randomElement(['Découpe', 'Profilage', 'Mixte']),
            'type'        => $this->faker->randomElement(['decoupe', 'profilage', 'mixte']),
            'hourly_cost' => $this->faker->numberBetween(3_000, 12_000),
            'status'      => 'active',
            'is_active'   => true,
        ];
    }
}
