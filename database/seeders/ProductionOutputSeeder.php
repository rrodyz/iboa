<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\Warehouse;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Database\Seeder;

/**
 * Complète les sorties produits finis (+ entrée stock) des OF « en cours »
 * sans sortie. Les OF terminés sont déjà alimentés par ProductionOrderSeeder.
 */
class ProductionOutputSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }
        app()->instance('current_company', $company);

        $stock     = app(ProductionStockService::class);
        $warehouse = Warehouse::where('company_id', $company->id)->orderByDesc('is_default')->value('id');

        $orders = ProductionOrder::where('company_id', $company->id)
            ->where('status', 'en_cours')->doesntHave('outputs')->get();

        $n = 0;
        foreach ($orders as $order) {
            $qty = max(1, (int) round((float) $order->quantity_requested * 0.9));
            $stock->recordOutput($order, [
                'warehouse_id' => $warehouse,
                'length'       => 6,
                'quantity'     => $qty,
                'unit_cost'    => random_int(2_500, 4_500),
            ]);
            $n++;
        }

        $this->command?->info("  ✓ {$n} sorties produits finis (complément)");
    }
}
