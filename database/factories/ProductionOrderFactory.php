<?php

namespace Database\Factories;

use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;

    public function definition(): array
    {
        static $counter = 1;
        $company = Company::query()->first() ?? Company::factory()->create();

        return [
            'company_id'         => $company->id,
            'fiscal_year_id'     => $company->current_fiscal_year_id,
            'number'             => 'OF-2026-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'sheet_type'         => 'bac',
            'thickness'          => $this->faker->randomElement([0.40, 0.50]),
            'color'              => $this->faker->randomElement(['Rouge', 'Bleu', 'Galva']),
            'quantity_requested' => $this->faker->numberBetween(10, 200),
            'quantity_produced'  => 0,
            'status'             => 'brouillon',
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'en_cours', 'launched_at' => now()]);
    }

    public function finished(): static
    {
        return $this->state(fn () => ['status' => 'termine', 'launched_at' => now()->subDay(), 'finished_at' => now()]);
    }
}
