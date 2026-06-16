<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionLineFactory extends Factory
{
    protected $model = ProductionLine::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'machine_id' => ProductionMachine::query()->value('id') ?? ProductionMachine::factory(),
            'code'       => 'LIG-' . str_pad($counter++, 3, '0', STR_PAD_LEFT),
            'name'       => 'Ligne ' . $this->faker->numberBetween(1, 9),
            'is_active'  => true,
        ];
    }
}
