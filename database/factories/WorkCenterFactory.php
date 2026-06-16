<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkCenterFactory extends Factory
{
    protected $model = WorkCenter::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'company_id'             => Company::query()->value('id') ?? Company::factory(),
            'machine_id'             => ProductionMachine::query()->value('id'),
            'code'                   => 'CT-' . str_pad((string) $counter++, 3, '0', STR_PAD_LEFT),
            'name'                   => 'Centre ' . $this->faker->randomElement(['Découpe', 'Profilage', 'Pliage', 'Soudure', 'Finition']),
            'capacity_hours_per_day' => $this->faker->randomElement([8, 16, 24]),
            'cost_per_hour'          => $this->faker->numberBetween(2_000, 15_000),
            'efficiency_rate'        => $this->faker->numberBetween(75, 100),
            'is_active'              => true,
        ];
    }
}
