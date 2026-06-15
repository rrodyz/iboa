<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionMachine;
use Illuminate\Database\Seeder;

class ProductionLineSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        $machines = ProductionMachine::where('company_id', $company->id)->pluck('id')->all();

        $lines = [
            ['code' => 'LIG-025', 'name' => 'Ligne Tôle Bac 0,25 mm'],
            ['code' => 'LIG-030', 'name' => 'Ligne Tôle Bac 0,30 mm'],
            ['code' => 'LIG-035', 'name' => 'Ligne Tôle Bac 0,35 mm'],
        ];

        foreach ($lines as $i => $l) {
            ProductionLine::firstOrCreate(
                ['company_id' => $company->id, 'code' => $l['code']],
                $l + [
                    'company_id' => $company->id,
                    'machine_id' => $machines[$i] ?? ($machines[0] ?? null),
                    'is_active'  => true,
                ],
            );
        }

        $this->command?->info('  ✓ 3 lignes de production');
    }
}
