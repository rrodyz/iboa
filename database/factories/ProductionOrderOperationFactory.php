<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOrderOperation;
use Illuminate\Database\Eloquent\Factories\Factory;
class ProductionOrderOperationFactory extends Factory {
    protected $model = ProductionOrderOperation::class;
    public function definition(): array {
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'sequence' => $this->faker->numberBetween(10, 90),
            'name' => $this->faker->randomElement(['Découpe', 'Profilage', 'Soudure']),
            'planned_minutes' => $this->faker->numberBetween(30, 300),
            'real_minutes' => 0,
            'status' => 'pending',
        ];
    }
}
