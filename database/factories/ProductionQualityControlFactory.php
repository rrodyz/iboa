<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionQualityControlFactory extends Factory
{
    protected $model = ProductionQualityControl::class;

    public function definition(): array
    {
        return [
            'company_id'          => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'thickness_ok'        => true,
            'length_ok'           => true,
            'color_ok'            => true,
            'visual_ok'           => true,
            'status'              => 'conforme',
            'rejected_quantity'   => 0,
            'controlled_at'       => now(),
        ];
    }

    public function nonConforme(): static
    {
        return $this->state(fn () => [
            'visual_ok'         => false,
            'status'            => 'non_conforme',
            'rejected_quantity' => $this->faker->numberBetween(1, 10),
            'reason'            => 'Défaut visuel',
        ]);
    }
}
