<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Database\Seeder;

class ProductionMachineSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        $machines = [
            ['code' => 'MAC-PROF', 'name' => 'Profileuse tôle bac',   'type' => 'profilage', 'hourly_cost' => 8_000],
            ['code' => 'MAC-CISA', 'name' => 'Cisaille automatique',   'type' => 'decoupe',   'hourly_cost' => 6_000],
            ['code' => 'MAC-PLIE', 'name' => 'Plieuse industrielle',   'type' => 'mixte',     'hourly_cost' => 7_000],
            ['code' => 'MAC-DECO', 'name' => 'Ligne de découpe',       'type' => 'decoupe',   'hourly_cost' => 5_500],
            ['code' => 'MAC-FINI', 'name' => 'Machine de finition',    'type' => 'mixte',     'hourly_cost' => 4_500],
        ];

        foreach ($machines as $m) {
            ProductionMachine::firstOrCreate(
                ['company_id' => $company->id, 'code' => $m['code']],
                $m + ['company_id' => $company->id, 'status' => 'active', 'is_active' => true],
            );
        }

        $this->command?->info('  ✓ 5 machines de production');
    }
}
