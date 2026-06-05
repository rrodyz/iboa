<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  POINT D'ENTRÉE DU SEEDING — architecture ERP production-compatible.
 *
 *  Sépare strictement deux catégories de données :
 *
 *    • ReferenceDataSeeder  → données de RÉFÉRENCE (rôles, permissions, plan
 *      comptable SYSCOHADA, journaux, taxes, paramètres de paie).
 *      Exécuté DANS TOUS LES ENVIRONNEMENTS, y compris la production.
 *      Idempotent : ré-exécutable sans effet de bord.
 *
 *    • DemoDataSeeder       → données de DÉMONSTRATION (sociétés fictives,
 *      clients/fournisseurs/produits de test, factures et écritures de test,
 *      utilisateurs de démo).
 *      Exécuté UNIQUEMENT hors production. JAMAIS sur un environnement
 *      `production`, pour ne pas polluer une base réelle.
 *
 *  Commandes :
 *    php artisan db:seed                                  # selon l'environnement
 *    php artisan db:seed --class=ReferenceDataSeeder      # référence seule (prod)
 *    php artisan db:seed --class=DemoDataSeeder           # démo seule (dev)
 *
 *  ⚠️  Garde-fou : la démo est bloquée si APP_ENV=production, même si on appelle
 *      DatabaseSeeder explicitement. Pour forcer la démo en local, viser
 *      directement --class=DemoDataSeeder.
 * ════════════════════════════════════════════════════════════════════════════
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Données de référence — TOUJOURS (prod incluse).
        $this->call(ReferenceDataSeeder::class);

        // 2. Données de démonstration — JAMAIS en production.
        if (app()->environment('production')) {
            $this->command->warn(
                'Environnement production détecté : DemoDataSeeder ignoré '.
                '(seules les données de référence ont été chargées).'
            );

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
