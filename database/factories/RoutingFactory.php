<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Production\Models\Routing;
use Illuminate\Database\Eloquent\Factories\Factory;
class RoutingFactory extends Factory {
    protected $model = Routing::class;
    public function definition(): array {
        static $c = 1;
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'code' => 'GAM-' . str_pad((string) $c++, 3, '0', STR_PAD_LEFT),
            'name' => 'Gamme ' . $this->faker->randomElement(['Tôle bac', 'Charpente', 'Portail']),
            'is_active' => true,
        ];
    }
}
