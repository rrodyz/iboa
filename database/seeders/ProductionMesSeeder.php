<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Reception;
use App\Modules\Production\Models\BillOfMaterial;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionMachine;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\Routing;
use App\Modules\Production\Models\WorkCenter;
use App\Modules\Production\Services\BatchService;
use App\Modules\Production\Services\LaborService;
use App\Modules\Production\Services\RoutingService;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Données de démonstration MES : centres de travail, gammes, work orders,
 * lots, pointage, maintenance, qualité, coûts standard.
 * Idempotent : ne fait rien si des centres de travail existent déjà.
 */
class ProductionMesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }
        app()->instance('current_company', $company);

        if (WorkCenter::where('company_id', $company->id)->exists()) {
            $this->command?->info('  ✓ Données MES déjà présentes — seed ignoré.');

            return;
        }

        $machines = ProductionMachine::where('company_id', $company->id)->get();
        $boms     = BillOfMaterial::where('company_id', $company->id)->get();
        $employees = Employee::pluck('id')->all();

        // 1. Centres de travail (1 par machine)
        $centers = collect();
        foreach ($machines as $m) {
            $centers->push(WorkCenter::create([
                'company_id' => $company->id, 'machine_id' => $m->id,
                'code' => 'CT-' . $m->code, 'name' => 'Centre ' . $m->name,
                'capacity_hours_per_day' => 8, 'cost_per_hour' => $m->hourly_cost ?: 5000,
                'efficiency_rate' => rand(80, 98), 'is_active' => true,
            ]));
            // fréquence maintenance préventive
            $m->update(['maintenance_frequency_days' => rand(30, 90)]);
        }

        // 2. Coûts standard sur les nomenclatures + gammes
        foreach ($boms as $bom) {
            $bom->update([
                'std_material_cost' => rand(3000, 8000),
                'std_labor_cost'    => rand(300, 1200),
                'std_machine_cost'  => rand(200, 800),
                'std_overhead_cost' => rand(100, 400),
            ]);
            $routing = Routing::create([
                'company_id' => $company->id, 'bill_of_material_id' => $bom->id,
                'code' => 'GAM-' . $bom->id, 'name' => 'Gamme ' . $bom->name, 'is_active' => true,
            ]);
            $seq = 10;
            foreach (['Découpe', 'Profilage', 'Finition'] as $opName) {
                $routing->operations()->create([
                    'work_center_id' => $centers->random()->id, 'sequence' => $seq,
                    'name' => $opName, 'setup_minutes' => rand(5, 20), 'run_minutes_per_unit' => rand(1, 4),
                ]);
                $seq += 10;
            }
        }

        // 3. Work Orders + pointage + lots sur les OF actifs/terminés
        $routingSvc = app(RoutingService::class);
        $batchSvc   = app(BatchService::class);
        $laborSvc   = app(LaborService::class);
        $ofs = ProductionOrder::where('company_id', $company->id)
            ->whereIn('status', ['en_cours', 'termine'])
            ->whereNotNull('bill_of_material_id')
            ->limit(40)->get();

        foreach ($ofs as $of) {
            try {
                $routingSvc->generateWorkOrders($of);
                // termine certaines opérations
                foreach ($of->operations()->get() as $i => $op) {
                    if ($of->status === 'termine' || $i === 0) {
                        $routingSvc->start($op);
                        $routingSvc->finish($op, rand(20, 180));
                    }
                }
            } catch (\Throwable $e) {
                // pas de gamme/déjà fait — ignore
            }

            // pointage temps
            if ($employees) {
                try {
                    $laborSvc->log($of, ['employee_id' => $employees[array_rand($employees)], 'hours' => rand(2, 8), 'hourly_cost' => rand(800, 2000)]);
                } catch (\Throwable $e) {
                }
            }

            // lot pour les terminés
            if ($of->status === 'termine') {
                try {
                    $batchSvc->createForOrder($of);
                } catch (\Throwable $e) {
                }
            }
        }

        // 4. Maintenance machines (préventive passée + corrective)
        foreach ($machines as $m) {
            MachineMaintenance::create([
                'company_id' => $company->id, 'machine_id' => $m->id, 'type' => 'preventive',
                'title' => 'Graissage & contrôle', 'status' => 'termine',
                'planned_at' => Carbon::now()->subDays(rand(20, 60)),
                'started_at' => Carbon::now()->subDays(rand(20, 60)),
                'ended_at' => Carbon::now()->subDays(rand(5, 19)),
                'downtime_minutes' => rand(60, 240), 'cost' => rand(20000, 100000),
            ]);
            if (rand(0, 1)) {
                MachineMaintenance::create([
                    'company_id' => $company->id, 'machine_id' => $m->id, 'type' => 'corrective',
                    'title' => 'Panne ' . ['moteur', 'roulement', 'capteur'][rand(0, 2)], 'status' => 'termine',
                    'started_at' => Carbon::now()->subDays(rand(1, 15)),
                    'ended_at' => Carbon::now()->subDays(rand(0, 1)),
                    'downtime_minutes' => rand(120, 480), 'cost' => rand(50000, 300000),
                    'operator_id' => $employees ? $employees[array_rand($employees)] : null,
                ]);
            }
        }

        // 5. Contrôles qualité + non-conformités
        $receptions = Reception::where('company_id', $company->id)->limit(10)->pluck('id')->all();
        $products   = Product::where('is_stockable', true)->limit(10)->pluck('id')->all();
        $i = 1;
        for ($k = 1; $k <= 15; $k++) {
            $verdict = ['conforme', 'conforme', 'conforme', 'partiel', 'non_conforme'][array_rand([0, 1, 2, 3, 4])];
            $insp = QualityInspection::create([
                'company_id' => $company->id,
                'type' => ['reception', 'en_cours', 'produit_fini'][array_rand([0, 1, 2])],
                'reception_id' => $receptions ? $receptions[array_rand($receptions)] : null,
                'product_id' => $products ? $products[array_rand($products)] : null,
                'controller_id' => $employees ? $employees[array_rand($employees)] : null,
                'reference' => 'CQ-' . str_pad((string) $i++, 5, '0', STR_PAD_LEFT),
                'inspected_at' => Carbon::now()->subDays(rand(0, 30)),
                'status' => $verdict,
                'quantity_checked' => rand(20, 200),
                'quantity_rejected' => $verdict === 'conforme' ? 0 : rand(1, 15),
            ]);
            if ($verdict === 'non_conforme') {
                NonConformity::create([
                    'company_id' => $company->id, 'quality_inspection_id' => $insp->id,
                    'reference' => 'NC-' . str_pad((string) $insp->id, 5, '0', STR_PAD_LEFT),
                    'title' => 'Défaut ' . ['épaisseur', 'longueur', 'couleur', 'visuel'][rand(0, 3)],
                    'severity' => ['mineure', 'majeure', 'critique'][rand(0, 2)],
                    'status' => ['ouverte', 'en_cours', 'cloturee'][rand(0, 2)],
                    'corrective_action' => 'Reprise + contrôle renforcé',
                    'responsible_id' => $employees ? $employees[array_rand($employees)] : null,
                    'due_date' => Carbon::now()->addDays(rand(3, 15)),
                ]);
            }
        }

        $this->command?->info('  ✓ MES : ' . $centers->count() . ' centres, ' . $boms->count() . ' gammes, work orders, lots, maintenance, qualité.');
    }
}
