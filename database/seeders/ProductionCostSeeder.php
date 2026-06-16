<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionCostService;
use Illuminate\Database\Seeder;

/**
 * Calcule le coût de revient des OF lancés/en cours/terminés sans coût.
 */
class ProductionCostSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }
        app()->instance('current_company', $company);

        $costing = app(ProductionCostService::class);
        $orders  = ProductionOrder::where('company_id', $company->id)
            ->whereIn('status', ['lance', 'en_cours', 'termine'])->doesntHave('cost')->get();

        foreach ($orders as $order) {
            $costing->compute($order, ['overhead_rate' => 5]);
        }

        $this->command?->info("  ✓ {$orders->count()} coûts de revient (complément)");
    }
}
