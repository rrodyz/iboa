<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricule'     => 'EMP-' . fake()->unique()->numerify('####'),
            'last_name'     => fake()->lastName(),
            'first_name'    => fake()->firstName(),
            'gender'        => fake()->randomElement(['M', 'F']),
            'email'         => fake()->unique()->safeEmail(),
            'phone'         => fake()->phoneNumber(),
            'job_title'     => fake()->jobTitle(),
            'status'        => 'actif',
            'family_status' => 'celibataire',
            'nb_children'   => 0,
            'nationality'   => 'Burkinabe',
            'hiring_date'   => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'payment_mode'  => 'virement',
            // company_id doit être fourni explicitement (pas de CompanyFactory imbriqué
            // pour éviter les conflits avec la company courante du test)
        ];
    }
}
