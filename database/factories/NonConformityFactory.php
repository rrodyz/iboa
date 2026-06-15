<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Quality\Models\NonConformity;
use Illuminate\Database\Eloquent\Factories\Factory;
class NonConformityFactory extends Factory {
    protected $model = NonConformity::class;
    public function definition(): array {
        static $c = 1;
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'reference' => 'NC-' . str_pad((string) $c++, 5, '0', STR_PAD_LEFT),
            'title' => $this->faker->sentence(4),
            'severity' => $this->faker->randomElement(['mineure','majeure','critique']),
            'status' => 'ouverte',
        ];
    }
}
