<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TreasuryApprovalThreshold;
use Illuminate\Database\Seeder;

/**
 * [TRESO] Seuils d'approbation décaissements par défaut (Burkina Faso, FCFA).
 * Adaptez les rôles/montants par société dans Paramétrage.
 */
class TreasuryApprovalThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Validation Comptable', 'min' => 0,        'max' => 500000,  'role' => 'comptable'],
            ['name' => 'Validation Directeur', 'min' => 500001,   'max' => 5000000, 'role' => 'directeur'],
            ['name' => 'Validation DG',        'min' => 5000001,  'max' => null,    'role' => 'super_admin'],
        ];

        foreach (Company::all() as $company) {
            foreach ($defaults as $i => $d) {
                TreasuryApprovalThreshold::firstOrCreate(
                    ['company_id' => $company->id, 'min_amount' => $d['min']],
                    [
                        'name'                => $d['name'],
                        'max_amount'          => $d['max'],
                        'required_role'       => $d['role'],
                        'required_permission' => 'treasury.validate',
                        'is_active'           => true,
                        'sort_order'          => $i,
                    ]
                );
            }
        }
    }
}
