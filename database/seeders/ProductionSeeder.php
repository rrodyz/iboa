<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrateur du jeu de données Production / Fabrication tôles bac.
 *
 *   php artisan db:seed --class=ProductionSeeder
 *
 * Crée un dataset réaliste de bout en bout : machines, lignes, bobines,
 * nomenclatures, 100 OF à statuts variés, et — pour les OF produits —
 * consommations matière, sorties produits finis (entrée stock), chutes,
 * coûts de revient et contrôles qualité, le tout via les services métier.
 *
 * Idempotent (skip si déjà présent), transactionnel, multi-société.
 * NON branché sur DatabaseSeeder — jamais exécuté automatiquement.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command?->warn('Aucune société — seed production annulé.');

            return;
        }
        app()->instance('current_company', $company);

        $this->command?->info('Seed Production — fabrication tôles bac :');

        DB::transaction(function () {
            $this->call([
                ProductionMachineSeeder::class,
                ProductionLineSeeder::class,
                CoilSeeder::class,
                BillOfMaterialSeeder::class,
                ProductionOrderSeeder::class,      // crée OF + conso/sorties/chutes/coûts/QC cohérents
                // Compléments idempotents (no-op si déjà couverts par ProductionOrderSeeder)
                ProductionConsumptionSeeder::class,
                ProductionOutputSeeder::class,
                ProductionWasteSeeder::class,
                ProductionQualityControlSeeder::class,
                ProductionCostSeeder::class,
                // MES : centres, gammes, work orders, lots, maintenance, qualité, coûts standard
                ProductionMesSeeder::class,
            ]);
        });

        $this->command?->info('Seed Production terminé.');
    }
}
