<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Database\Eloquent\Factories\Factory;
class QualityInspectionFactory extends Factory {
    protected $model = QualityInspection::class;
    public function definition(): array {
        static $c = 1;
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'type' => $this->faker->randomElement(['reception','en_cours','produit_fini']),
            'reference' => 'CQ-' . str_pad((string) $c++, 5, '0', STR_PAD_LEFT),
            'inspected_at' => now(),
            'status' => 'conforme',
            'quantity_checked' => $this->faker->numberBetween(10, 100),
            'quantity_rejected' => 0,
        ];
    }
}
