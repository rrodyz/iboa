<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionWaste;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionWasteFactory extends Factory
{
    protected $model = ProductionWaste::class;

    public function definition(): array
    {
        $weight = $this->faker->numberBetween(2, 30);

        return [
            'company_id'          => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'type'                => $this->faker->randomElement(['reutilisable', 'non_reutilisable', 'rebut']),
            'quantity'            => 0,
            'weight'              => $weight,
            'value'               => $weight * $this->faker->numberBetween(400, 800),
            'reason'              => $this->faker->randomElement(['Bord abîmé', 'Mauvaise coupe', 'Défaut couleur']),
        ];
    }
}
