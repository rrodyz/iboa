<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
class ProductionBatchFactory extends Factory {
    protected $model = ProductionBatch::class;
    public function definition(): array {
        static $c = 1;
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'production_order_id' => ProductionOrder::factory(),
            'batch_number' => 'LOT-' . str_pad((string) $c++, 5, '0', STR_PAD_LEFT),
            'quantity' => $this->faker->numberBetween(5, 100),
            'status' => 'en_cours',
            'produced_at' => now(),
        ];
    }
}
