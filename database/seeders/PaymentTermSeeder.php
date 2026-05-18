<?php

namespace Database\Seeders;

use App\Models\PaymentTerm;
use Illuminate\Database\Seeder;

class PaymentTermSeeder extends Seeder
{
    /**
     * Standard OHADA / West-African payment terms.
     * Use updateOrCreate so running the seeder twice is idempotent.
     */
    public function run(): void
    {
        $terms = [
            ['name' => 'Comptant',             'days' => 0,  'end_of_month' => false, 'additional_days' => 0],
            ['name' => '15 jours nets',        'days' => 15, 'end_of_month' => false, 'additional_days' => 0],
            ['name' => '30 jours nets',        'days' => 30, 'end_of_month' => false, 'additional_days' => 0],
            ['name' => '45 jours nets',        'days' => 45, 'end_of_month' => false, 'additional_days' => 0],
            ['name' => '60 jours nets',        'days' => 60, 'end_of_month' => false, 'additional_days' => 0],
            ['name' => '30 jours fin de mois', 'days' => 30, 'end_of_month' => true,  'additional_days' => 0],
            ['name' => '60 jours fin de mois', 'days' => 60, 'end_of_month' => true,  'additional_days' => 0],
            ['name' => '30 jours fin de mois + 15 jours', 'days' => 30, 'end_of_month' => true, 'additional_days' => 15],
        ];

        foreach ($terms as $term) {
            PaymentTerm::updateOrCreate(
                ['name' => $term['name']],
                array_merge($term, ['is_active' => true])
            );
        }
    }
}
