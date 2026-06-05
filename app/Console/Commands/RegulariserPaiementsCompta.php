<?php

namespace App\Console\Commands;

use App\Models\ClientPayment;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Régularise les anomalies comptables liées aux paiements clients.
 *
 * Deux types d'anomalies détectés :
 *
 *  TYPE A — Paiement actif (non supprimé) sans journal_entry_id :
 *    Sous-cas A1 : une JournalEntry orpheline existe (même référence) →
 *                  on relie simplement journal_entry_id sur le paiement.
 *    Sous-cas A2 : aucune écriture → appel AccountingService::postClientPayment().
 *
 *  TYPE B — Paiement soft-deleté dont la JournalEntry orpheline N'A PAS été
 *    contre-passée (reversed_by_entry_id IS NULL) →
 *    appel AccountingService::reverseEntry() pour annuler l'impact comptable.
 *
 * Origine du double bug (commit 91ed768) :
 *  1. postClientPayment() ne liait pas journal_entry_id → corrigé c2dd76d (24/05).
 *  2. La suppression d'un paiement ne contre-passait pas l'écriture associée →
 *     corrigé dans ClientPaymentController::destroy() (cf. ce commit).
 */
class RegulariserPaiementsCompta extends Command
{
    protected $signature = 'compta:regulariser-paiements
                            {--dry-run : Affiche ce qui serait fait sans modifier la base}
                            {--company= : Limiter à une company_id spécifique}
                            {--skip-deleted : Ne pas traiter les paiements supprimés (type B)}';

    protected $description = 'Régularise les écritures comptables manquantes ou orphelines pour les paiements clients';

    public function handle(AccountingService $accounting): int
    {
        $dryRun      = $this->option('dry-run');
        $companyId   = $this->option('company');
        $skipDeleted = $this->option('skip-deleted');

        $this->info($dryRun ? '[DRY-RUN] Simulation — aucune modification effectuée' : 'Régularisation en cours…');
        $this->newLine();

        $linked  = 0;
        $created = 0;
        $reversed = 0;
        $skipped = 0;

        // ── TYPE A : paiements actifs confirmés sans journal_entry_id ─────────
        $this->line('<comment>── TYPE A : paiements actifs sans écriture ──</comment>');

        $activePayments = ClientPayment::with(['client', 'company'])
            ->whereNull('journal_entry_id')
            ->where('status', 'confirme')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get();

        if ($activePayments->isEmpty()) {
            $this->line('   Aucun paiement actif sans écriture.');
        }

        foreach ($activePayments as $payment) {
            $this->line("   → {$payment->number}  {$payment->amount} XOF  ({$payment->payment_date})");

            $orphan = JournalEntry::where('company_id', $payment->company_id)
                ->where('reference', $payment->number)
                ->whereNull('reversed_by_entry_id')
                ->whereNull('deleted_at')
                ->first();

            if ($orphan) {
                $this->line("     ✓ JE orpheline trouvée : {$orphan->number} (id={$orphan->id}) — reliaison");
                if (!$dryRun) {
                    $payment->updateQuietly(['journal_entry_id' => $orphan->id]);
                }
                $linked++;
            } else {
                $this->line("     ⚠ Aucune JE orpheline — création d'une nouvelle écriture");
                if (!$dryRun) {
                    try {
                        DB::transaction(function () use ($accounting, $payment) {
                            $accounting->postClientPayment($payment->fresh(['client', 'company']));
                        });
                        $created++;
                        $this->line('     ✓ Écriture créée et liée');
                    } catch (\Throwable $e) {
                        $this->error("     ✗ Échec : {$e->getMessage()}");
                        $skipped++;
                    }
                } else {
                    $created++;
                }
            }
        }

        // ── TYPE B : paiements supprimés avec JE orpheline non contre-passée ──
        if (!$skipDeleted) {
            $this->newLine();
            $this->line('<comment>── TYPE B : paiements supprimés avec JE non contre-passée ──</comment>');

            $deletedPayments = ClientPayment::onlyTrashed()
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->get();

            $toReverse = $deletedPayments->filter(function (ClientPayment $payment) {
                $orphan = JournalEntry::where('company_id', $payment->company_id)
                    ->where('reference', $payment->number)
                    ->whereNull('reversed_by_entry_id')
                    ->whereNull('deleted_at')
                    ->first();
                return $orphan !== null;
            });

            if ($toReverse->isEmpty()) {
                $this->line('   Aucune JE orpheline de paiement supprimé.');
            }

            foreach ($toReverse as $payment) {
                $orphan = JournalEntry::where('company_id', $payment->company_id)
                    ->where('reference', $payment->number)
                    ->whereNull('reversed_by_entry_id')
                    ->whereNull('deleted_at')
                    ->first();

                $this->line("   → {$payment->number}  {$payment->amount} XOF  (supprimé le {$payment->deleted_at})");
                $this->line("     JE à contre-passer : {$orphan->number} (id={$orphan->id})");

                if (!$dryRun) {
                    try {
                        DB::transaction(function () use ($accounting, $orphan, $payment) {
                            $accounting->reverseEntry(
                                $orphan,
                                "Annulation paiement supprimé {$payment->number}"
                            );
                        });
                        $reversed++;
                        $this->line('     ✓ Contre-passation créée');
                    } catch (\Throwable $e) {
                        $this->error("     ✗ Échec : {$e->getMessage()}");
                        $skipped++;
                    }
                } else {
                    $reversed++;
                }
            }
        }

        // ── Résumé ────────────────────────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['Type', 'Action', 'Nombre'],
            [
                ['A', 'Reliaisons (JE existante reliée)',      $linked],
                ['A', 'Créations  (nouvelle écriture)',        $created],
                ['B', 'Contre-passations (JE orpheline)',      $reversed],
                ['-', 'Erreurs / ignorés',                     $skipped],
            ]
        );

        if ($dryRun) {
            $this->warn('Mode dry-run : aucune modification effectuée. Relancez sans --dry-run pour appliquer.');
        }

        return self::SUCCESS;
    }
}
