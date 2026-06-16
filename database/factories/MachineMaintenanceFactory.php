<?php
namespace Database\Factories;
use App\Models\Company;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Database\Eloquent\Factories\Factory;
class MachineMaintenanceFactory extends Factory {
    protected $model = MachineMaintenance::class;
    public function definition(): array {
        return [
            'company_id' => Company::query()->value('id') ?? Company::factory(),
            'machine_id' => ProductionMachine::query()->value('id') ?? ProductionMachine::factory(),
            'type' => $this->faker->randomElement(['preventive','corrective']),
            'title' => $this->faker->sentence(3),
            'status' => 'planifie',
            'planned_at' => now()->addDays($this->faker->numberBetween(1, 30)),
            'downtime_minutes' => 0,
            'cost' => 0,
        ];
    }
}
