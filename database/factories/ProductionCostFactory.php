<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionCostFactory extends Factory
{
    protected $model = ProductionCost::class;

    public function definition(): array
    {
        $material = $this->faker->numberBetween(20_000, 200_000);
        $labor    = $this->faker->numberBetween(2_000, 20_000);
        $machine  = $this->faker->numberBetween(2_000, 20_000);
        $overhead = (int) round(($material + $labor + $machine) * 0.05);
        $total    = $material + $labor + $machine + $overhead;
        $meters   = $this->faker->numberBetween(50, 500);

        return [
            'company_id'          => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'material_cost'       => $material,
            'labor_cost'          => $labor,
            'machine_cost'        => $machine,
            'overhead_cost'       => $overhead,
            'total_cost'          => $total,
            'cost_per_meter'      => round($total / $meters, 2),
            'cost_per_unit'       => round($total / max(1, (int) ($meters / 6)), 2),
            'margin'              => $this->faker->numberBetween(-50_000, 100_000),
        ];
    }
}
