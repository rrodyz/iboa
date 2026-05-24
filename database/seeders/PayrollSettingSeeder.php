<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayrollSetting;
use Illuminate\Database\Seeder;

/**
 * [RH-PRO] Initialise les paramètres de paie par défaut (Burkina Faso).
 */
class PayrollSettingSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command->warn('Aucune entreprise trouvée. Paramètres de paie non créés.');
            return;
        }

        PayrollSetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                // CNSS — taux légaux Burkina Faso 2024
                'cnss_employee_rate' => 5.50,
                'cnss_employer_rate' => 16.00,
                'cnss_ceiling'       => 650_000,
                'cnss_at_rate'       => 3.50,

                // Heures supplémentaires
                'work_days_month' => 26,
                'work_hours_day'  => 8,
                'hs_rate_25'      => 25.00,
                'hs_rate_50'      => 50.00,
                'hs_rate_nuit'    => 75.00,

                // Quotient familial IUTS
                'nb_parts_max'    => 5,
                'parts_per_child' => 0.5,

                // Barème IUTS mensuel (par part — Burkina Faso)
                'iuts_brackets' => [
                    [25_000,          0],
                    [40_000,         12],
                    [60_000,         17],
                    [80_000,         22],
                    [120_000,        27],
                    [9_999_999_999,  33],
                ],

                'bulletin_prefix' => 'BUL',
                'currency_code'   => 'FCFA',
                'country_code'    => 'BF',
                'notes'           => 'Paramètres initiaux Burkina Faso — à réviser chaque année selon les textes légaux.',
            ]
        );

        $this->command->info("✓ Paramètres de paie initialisés pour : {$company->name}");
    }
}
