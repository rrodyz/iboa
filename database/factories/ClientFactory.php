<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'code'        => 'CLI-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'type'        => 'entreprise',
            'name'        => $this->faker->company(),
            'trade_name'  => null,
            'email'       => $this->faker->companyEmail(),
            'phone'       => '+226 ' . $this->faker->numerify('## ## ## ##'),
            'city'        => $this->faker->randomElement(['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou']),
            'country'     => 'Burkina Faso',
            'credit_limit'=> $this->faker->randomElement([500_000, 1_000_000, 2_000_000, 5_000_000]),
            'payment_days'=> $this->faker->randomElement([30, 45, 60]),
            'balance'     => 0,
            'is_active'   => true,
        ];
    }
}
