<?php

namespace Database\Factories;

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoilFactory extends Factory
{
    protected $model = Coil::class;

    public function definition(): array
    {
        static $counter = 1;

        $weight = $this->faker->numberBetween(800, 3000);
        $price  = $weight * $this->faker->numberBetween(400, 900);

        return [
            'company_id'       => Company::query()->value('id') ?? Company::factory(),
            'reference'        => 'BOB-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'lot_number'       => 'LOT-' . $this->faker->numberBetween(1000, 9999),
            'color'            => $this->faker->randomElement(['Rouge', 'Bleu', 'Vert', 'Galva', 'Blanc']),
            'thickness'        => $this->faker->randomElement([0.30, 0.40, 0.50]),
            'width'            => $this->faker->randomElement([1000, 1200, 1250]),
            'initial_weight'   => $weight,
            'remaining_weight' => $weight,
            'estimated_length' => round($weight / 4, 2),
            'purchase_price'   => $price,
            'cost_per_kg'      => round($price / $weight, 2),
            'received_at'      => now()->subDays($this->faker->numberBetween(0, 60)),
            'status'           => 'disponible',
        ];
    }
}
