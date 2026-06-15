<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionQualityControl;
use Illuminate\Database\Seeder;

/**
 * Ajoute des contrôles qualité aux OF produits sans contrôle.
 */
class ProductionQualityControlSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        $employees = Employee::pluck('id')->all();
        $orders    = ProductionOrder::where('company_id', $company->id)
            ->whereIn('status', ['en_cours', 'termine'])->doesntHave('qualityControls')
            ->inRandomOrder()->limit(25)->get();

        $verdicts = ['conforme', 'conforme', 'conforme', 'a_reprendre', 'non_conforme'];
        $n = 0;
        foreach ($orders as $order) {
            $verdict = $verdicts[array_rand($verdicts)];
            ProductionQualityControl::create([
                'company_id'          => $company->id,
                'production_order_id' => $order->id,
                'thickness_ok'        => $verdict === 'conforme',
                'length_ok'           => $verdict !== 'non_conforme',
                'color_ok'            => $verdict === 'conforme',
                'visual_ok'           => $verdict === 'conforme',
                'status'              => $verdict,
                'rejected_quantity'   => $verdict === 'non_conforme' ? random_int(1, 10) : 0,
                'reason'              => $verdict === 'conforme' ? null : 'Écart détecté au contrôle',
                'controller_id'       => $employees ? $employees[array_rand($employees)] : null,
                'controlled_at'       => now()->subDays(random_int(1, 40)),
            ]);
            $n++;
        }

        $this->command?->info("  ✓ {$n} contrôles qualité (complément)");
    }
}
