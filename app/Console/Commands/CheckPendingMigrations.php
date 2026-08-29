<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * [P7.2 — Phase 8] Gate de déploiement, lecture seule.
 *
 * Cause racine de l'incident gross_material_cost (2026-08-28) : une
 * migration committée mais jamais exécutée sur iboa_erp. Aucun test
 * PHPUnit/Pest ne peut détecter ce type de dérive (RefreshDatabase lance
 * migrate:fresh avant chaque suite, donc la base de test est toujours à
 * jour par construction — voir docs/PROCEDURE-MIGRATION-DEPLOIEMENT.md).
 *
 * Cette commande n'exécute AUCUNE migration : elle interroge
 * `migrate:status` et échoue (exit 1) si au moins une ligne est « Pending »,
 * pour servir de gate avant tout déploiement.
 */
class CheckPendingMigrations extends Command
{
    protected $signature = 'a3:check-pending-migrations';

    protected $description = 'Échoue (exit 1) si une migration committée n\'a pas été appliquée à la base — lecture seule, n\'exécute jamais de migration';

    public function handle(): int
    {
        $status = Artisan::call('migrate:status', ['--pending' => true]);
        $output = Artisan::output();

        $pendingLines = collect(explode(PHP_EOL, $output))
            ->filter(fn ($line) => str_contains($line, 'Pending'))
            ->values();

        if ($pendingLines->isEmpty()) {
            $this->info('OK — aucune migration en attente.');

            return self::SUCCESS;
        }

        $this->error(sprintf('%d migration(s) en attente — déploiement à bloquer :', $pendingLines->count()));
        $pendingLines->each(fn ($line) => $this->line($line));
        $this->newLine();
        $this->line('Exécuter : php artisan migrate --force, puis relancer cette commande avant de continuer.');

        return self::FAILURE;
    }
}
