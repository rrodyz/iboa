<?php
namespace Database\Factories;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\RoutingOperation;
use Illuminate\Database\Eloquent\Factories\Factory;
class RoutingOperationFactory extends Factory {
    protected $model = RoutingOperation::class;
    public function definition(): array {
        return [
            'routing_id' => Routing::factory(),
            'sequence' => $this->faker->numberBetween(10, 90),
            'name' => $this->faker->randomElement(['Découpe', 'Profilage', 'Pliage', 'Soudure', 'Peinture']),
            'setup_minutes' => $this->faker->numberBetween(5, 30),
            'run_minutes_per_unit' => $this->faker->randomFloat(2, 0.5, 5),
        ];
    }
}
