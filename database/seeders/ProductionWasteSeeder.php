<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Services\ProductionStockService;
use Illuminate\Database\Seeder;

/**
 * Ajoute des pertes/chutes aux OF produits (en cours / terminés) qui n'en ont pas.
 */
class ProductionWasteSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }
        app()->instance('current_company', $company);

        $stock  = app(ProductionStockService::class);
        $orders = ProductionOrder::where('company_id', $company->id)
            ->whereIn('status', ['en_cours', 'termine'])->doesntHave('wastes')
            ->inRandomOrder()->limit(20)->get();

        $types = ['reutilisable', 'non_reutilisable', 'rebut'];
        $n = 0;
        foreach ($orders as $order) {
            $stock->recordWaste($order, [
                'type'        => $types[array_rand($types)],
                'weight'      => random_int(5, 30),
                'machine_id'  => $order->productionLine?->machine_id,
                'reason'      => ['Bord abîmé', 'Mauvaise coupe', 'Défaut couleur'][array_rand([0, 1, 2])],
            ]);
            $n++;
        }

        $this->command?->info("  ✓ {$n} pertes / chutes (complément)");
    }
}
