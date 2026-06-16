<?php

namespace Database\Seeders;

use App\Modules\Production\Models\Coil;
use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\CoilConsumptionService;
use Illuminate\Database\Seeder;

/**
 * Complète les consommations matière des OF « en cours » qui n'en ont pas.
 * (Les OF terminés sont déjà alimentés par ProductionOrderSeeder.)
 */
class ProductionConsumptionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }
        app()->instance('current_company', $company);

        $consume = app(CoilConsumptionService::class);
        $orders  = ProductionOrder::where('company_id', $company->id)
            ->where('status', 'en_cours')->doesntHave('consumptions')->get();

        $n = 0;
        foreach ($orders as $order) {
            $need = random_int(80, 200);
            $coil = Coil::where('company_id', $company->id)->where('status', '!=', 'epuisee')
                ->where('remaining_weight', '>=', $need)->inRandomOrder()->first();
            if ($coil) {
                $consume->consume($order, $coil, $need, round($need / 4, 2));
                $n++;
            }
        }

        $this->command?->info("  ✓ {$n} consommations matière (complément)");
    }
}
